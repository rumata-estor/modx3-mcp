#!/usr/bin/env python3
# MODX MCP SOURCE / ИСХОДНИК MODX MCP
# RU: Одноразовая межрепозиторная проверка Executor и реального MCP; без production.
# EN: Disposable cross-repo Executor/native MCP integration, never production.
"""Disposable native MODX MCP <-> real Site Agent Executor interop.

The real MODX 2/3 dispatcher commits a uniquely named unpublished CI chunk,
then a subprocess drops the response. The real Site Agent ExecutionStore and
ModxDriver preflight must block with UNKNOWN and must not retry or compensate.
This is not an HTTP/Telegram approval-chain test.
"""
from pathlib import Path
import json
import os
import secrets
import subprocess
import sys
import tempfile

if (
    os.environ.get("MODXMCP_DISPOSABLE_CI") != "1"
    or not os.environ.get("MODX_CONFIG_CORE", "").startswith("/home/runner/")
    or not Path(os.environ.get("MODX_CONFIG_CORE", "")).is_file()
    or not os.environ.get("RUNNER_TEMP", "").startswith("/home/runner/")
):
    raise SystemExit("Refusing real interop outside disposable GitHub CI.")

agent_root = Path(os.environ["RUNNER_TEMP"]) / "site-agent-runtime"
if not (agent_root / "runtime/siteagent/executor.py").is_file():
    raise SystemExit("Pinned Site Agent source is not installed in CI.")
sys.path.insert(0, str(agent_root / "runtime"))
from siteagent.drivers.modx import ModxDriver
from siteagent.executor import ExecutionError, ExecutionStore

worker = Path(__file__).with_name("test.ci-siteagent-mcp-worker.php")
task_id = "TASK-20261123-120000-abcd"
name = "CI_AGENT_CROSS_" + secrets.token_hex(6)
content = "DISPOSABLE_AGENT_MCP_COMMIT_" + secrets.token_hex(6)
calls = {"create": 0, "cleanup": 0}


def assert_ci(condition, message):
    if not condition:
        raise RuntimeError("SITEAGENT_CROSS_MCP_E2E_FAIL " + message)


def call_native(mode, **kwargs):
    result = subprocess.run(
        ["/usr/bin/env", "php", str(worker), mode],
        input=json.dumps({"name": name, **kwargs}),
        text=True, capture_output=True, timeout=25,
        env=dict(os.environ),
    )
    if mode == "create_lost":
        assert_ci(result.returncode == 86,
                  "native dispatcher did not commit before response loss")
        assert_ci(result.stdout.strip() == "",
                  "test expected a genuinely lost response")
        return None
    assert_ci(result.returncode == 0,
              "native read/cleanup failed code=" + str(result.returncode))
    try:
        return json.loads(result.stdout.strip())
    except json.JSONDecodeError as exc:
        raise RuntimeError("invalid read-only MCP worker response") from exc


def exact_elements():
    listed = call_native("list")
    assert_ci(isinstance(listed, list), "MCP list_elements not an array")
    return [row for row in listed
            if isinstance(row, dict) and row.get("name") == name]


class TaskState:
    def __init__(self):
        self.task = {"id": task_id, "status": "running"}
        self.transitions = []

    def get(self, requested):
        assert_ci(requested == task_id, "unexpected task ID")
        return dict(self.task)

    def transition(self, requested, new_status, **kwargs):
        assert_ci(requested == task_id, "unexpected task transition")
        self.transitions.append((self.task["status"], new_status))
        self.task["status"] = new_status
        self.task["blocker"] = kwargs.get("reason")
        return dict(self.task)


class FrozenChangeSet:
    def __init__(self, revision):
        self.document = {
            "site_id": "ci-disposable-modx",
            "task_id": task_id,
            "revision": 1,
            "state": "sealed",
            "source_fresh": True,
            "ready_for_execution": True,
            "payload": {
                "summary": "CI native MCP response-loss create",
                "observed_site_revision": revision,
                "read_set": [],
                "mutations": [{
                    "id": "create-ci-chunk",
                    "target_index": 0,
                    "action": "create",
                    "kind": "chunk",
                    "before": {"exists": False},
                    "after": {"name": name, "content": content},
                    "fields": ["name", "content"],
                    "dependencies": [],
                    "rationale": "one-time response-loss E2E only",
                    "risk": {"level": "low", "reasons": []},
                    "target": {
                        "index": 0,
                        "kind": "chunk",
                        "status": "new_target",
                        "id": None,
                        "name": name,
                    },
                }],
                "verification": [{
                    "id": "verify-ci-chunk", "type": "cms_read",
                    "target": "chunk " + name,
                    "expectation": "native MCP content matches",
                    "required": True,
                }],
                "rollback": {
                    "strategy": "compensating_actions",
                    "steps": ["delete test chunk"],
                    "automatic_on_failure": True,
                    "reason": "disposable cleanup",
                },
                "backup": {
                    "required": False, "strategy": "none",
                    "scope": [], "reason": "new disposable test chunk",
                },
                "risk_level": "low",
                "approval_required": False,
                "scope_fingerprint": "ci-cross-native-" + name,
                "payload_fingerprint": "ci-cross-native-" + name,
            },
        }

    def get(self, requested):
        assert_ci(requested == task_id, "unexpected ChangeSet ID")
        return self.document


class NativeLostResponseDriver(ModxDriver):
    """Actual ModxDriver CAS read/preflight; native MCP mutation transport.

    Only the execution transport is replaced with a CI-specific PHP process,
    since no production Site Agent Node bridge is installed on GitHub Actions.
    """
    def _call(self, site_id, tool, args=None, *, timeout=90):
        if tool == "modx_get_site_state":
            return call_native("site_state")
        if tool == "modx_list_elements":
            return exact_elements()
        if tool == "modx_get_system_setting":
            # The isolated runner never enables auto_static.
            assert_ci((args or {}).get("key") == "modxmcp.auto_static",
                      "unexpected setting request")
            return {"value": "0"}
        if tool == "modx_read_error_log":
            # A deliberate transport drop never reaches the health phase.
            return {"lines": [], "total": 0}
        raise RuntimeError("Unexpected native bridge tool: " + tool)

    def execute_changeset_mutation(
        self, site_id, mutation, *, approved, work_dir,
        timeout=90, preconditions=None
    ):
        assert_ci(mutation["action"] == "create"
                  and mutation["kind"] == "chunk",
                  "unexpected action at native MCP boundary")
        assert_ci(calls["create"] == 0, "automatic write replay was attempted")
        calls["create"] += 1
        expected = (preconditions or {}).get("observed_site_revision")
        assert_ci(isinstance(expected, int), "missing CAS from Site Agent")
        call_native(
            "create_lost", content=mutation["after"]["content"],
            observed_site_revision=expected,
        )
        # Native MODX wrote successfully, but the caller never got the
        # response. This is the actual transport-unknown path at Executor.
        raise TimeoutError("MCP_RESPONSE_LOST_AFTER_COMMIT")

    def rollback_changeset_mutation(self, *args, **kwargs):
        calls["cleanup"] += 1
        raise RuntimeError("Automatic rollback is forbidden in this test")


def main():
    os.environ["SITE_PROJECT_LOCK_TOKEN"] = "ci-owned-site-lock"
    with tempfile.TemporaryDirectory(prefix="ci-siteagent-", dir=os.environ["RUNNER_TEMP"]) as tmp:
        root = Path(tmp)
        os.environ["SITEAGENT_HOME"] = tmp
        before = call_native("site_state")
        assert_ci(before.get("atomic_preconditions") is True,
                  "server does not advertise atomic CAS")
        assert_ci(exact_elements() == [], "test identity was not unique")
        cfg = {
            "site_id": "ci-disposable-modx",
            "mode": "work",
            "cms": {"driver": "modx"},
            "paths": {
                "manager_root": str(root / "manager"),
                "local_root": str(root / "workspace"),
            },
            "task_manager": {"enabled": True},
            "resolver": {"enabled": True},
            "decisions": {"enabled": True},
            "changesets": {"enabled": True},
            "executor": {"enabled": True},
        }
        (root / "workspace").mkdir()
        store = ExecutionStore("ci-disposable-modx", cfg)
        store.task_store = TaskState()
        store.changesets = FrozenChangeSet(int(before["site_revision"]))
        store.driver = NativeLostResponseDriver()
        try:
            store.run_locked(task_id)
            assert_ci(False, "lost response reported successful mutation")
        except ExecutionError as err:
            assert_ci("unknown outcome" in str(err).lower()
                      or "MUTATION_OUTCOME_UNCERTAIN" in str(err),
                      "wrong fail-closed error class")
        record = store.get(task_id)
        assert_ci(record.get("outcome_status") == "unknown",
                  "Site Agent did not persist UNKNOWN")
        assert_ci(record.get("state") == "failed"
                  and record.get("rollback", {}).get("state")
                  == "not_attempted_uncertain",
                  "Site Agent incorrectly marked success or rollback")
        assert_ci(store.task_store.task["status"] == "blocked",
                  "durable task not blocked")
        assert_ci(calls == {"create": 1, "cleanup": 0},
                  "automatic compensation or duplicate write occurred")

        listed = exact_elements()
        assert_ci(len(listed) == 1, "native MODX did not persist exactly one chunk")
        element_id = int(listed[0]["id"])
        loaded = call_native("read", id=element_id)
        assert_ci(loaded.get("name") == name
                  and loaded.get("content") == content,
                  "independent real MODX MCP readback mismatched")
        after = call_native("site_state")
        assert_ci(int(after["site_revision"]) == int(before["site_revision"]) + 1,
                  "unknown mutation advanced CAS more than once")
        for action in ("verify", "rollback_locked", "prepare", "run_locked"):
            try:
                if action == "verify":
                    store.verify(task_id)
                elif action == "rollback_locked":
                    store.rollback_locked(task_id, reason="CI not authorized")
                elif action == "prepare":
                    store.prepare(task_id)
                else:
                    store.run_locked(task_id)
                assert_ci(False, "unsafe " + action + " succeeded")
            except ExecutionError:
                pass
        assert_ci(calls == {"create": 1, "cleanup": 0},
                  "retry or rollback mutated a second time")
        print("SITEAGENT_REAL_MODX_MCP_RESPONSE_LOSS_OK"
              " committed=1 replay=0 automatic_rollback=0"
              " blocked=1 platform=" + os.getenv("BUILD_PLATFORM", "unknown"))
    return 0


if __name__ == "__main__":
    try:
        main()
    finally:
        try:
            for row in exact_elements():
                call_native("cleanup", id=int(row["id"]))
            assert_ci(exact_elements() == [], "disposable MCP cleanup incomplete")
        except Exception as exc:
            print("SITEAGENT_CROSS_MCP_CLEANUP_FAILED:", type(exc).__name__,
                  file=sys.stderr)
            raise
