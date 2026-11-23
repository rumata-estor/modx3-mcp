#!/usr/bin/env python3
# MODX MCP SOURCE / ИСХОДНИК MODX MCP
# RU: Два независимых контейнера PHP-FPM через HTTP/nginx + единый MariaDB.
# EN: Two independent network/filesystem PHP-FPM nodes and one MariaDB writer.
"""Never run outside disposable GitHub Actions and the temporary MODX site."""
import concurrent.futures
import json
import os
from pathlib import Path
import secrets
import subprocess
import sys
import time
import urllib.error
import urllib.request

if (
    os.environ.get("MODXMCP_DISPOSABLE_CI") != "1"
    or not os.environ.get("RUNNER_TEMP", "").startswith("/home/runner/")
    or not os.environ.get("MCP_FPM_SHARED", "").startswith("/home/runner/")
):
    raise SystemExit("Refusing non-disposable PHP-FPM cross-node test")
shared = Path(os.environ["MCP_FPM_SHARED"]).resolve()
if not shared.is_dir() or not (shared / "CI_MARKER").is_file():
    raise SystemExit("CI shared directory/marker missing")
url = "http://127.0.0.1:18931/"
timeout = 50

def check(condition, message):
    if not condition:
        raise RuntimeError("FPM_MULTINODE_FAILED " + message)

def rpc(node, data, seconds=50):
    req = urllib.request.Request(
        url + node,
        json.dumps(data, separators=(",", ":")).encode(),
        {"Content-Type": "application/json"},
        method="POST",
    )
    with urllib.request.urlopen(req, timeout=seconds) as response:
        raw = response.read(65536)
    out = json.loads(raw)
    check(isinstance(out, dict) and out.get("node") == node,
          "untrusted or mismatched FPM response")
    return out

def wait_file(path, seconds=18):
    until = time.monotonic() + seconds
    while not path.exists() and time.monotonic() < until:
        time.sleep(0.02)
    check(path.exists(), "barrier " + str(path.name))

def revision():
    result = rpc("b", {"op": "state"})
    check(result["status"] == "success", "read current site revision")
    return int(result["state"]["site_revision"])

def two_requests(pool, operation, base):
    futures = {
        node: pool.submit(rpc, node, dict(base, op=operation))
        for node in ("a", "b")
    }
    wait_file(shared / (operation + "-ready-a"))
    wait_file(shared / (operation + "-ready-b"))
    (shared / (operation + "-go")).write_text("go")
    results = {node: futures[node].result(timeout=timeout) for node in ("a","b")}
    statuses = sorted(result.get("status") for result in results.values())
    check(statuses == ["stale", "success"],
          operation + " two distinct FPM hosts yielded " + repr(results))
    return results

created_id = 0
try:
    with concurrent.futures.ThreadPoolExecutor(max_workers=3) as pool:
        a = rpc("a", {"op": "state"})
        b = rpc("b", {"op": "state"})
        check(a["status"] == b["status"] == "success", "both HTTP workers available")
        check(a["hostname"] != b["hostname"],
              "PHP-FPM hosts must be different containers")
        check(int(a["state"]["site_revision"]) == int(b["state"]["site_revision"]),
              "common MariaDB writer does not have a consistent revision")
        print("FPM_TWO_SEPARATE_CONTAINERS_ONE_DB_WRITER_OK", flush=True)

        first = revision()
        fixture = "MCP_FPM_" + secrets.token_hex(7)
        winners = two_requests(pool, "create", {"revision": first,"name": fixture})
        winner = next(node for node in winners if winners[node]["status"] == "success")
        created_id = int(winners[winner]["result"].get("id",0))
        check(created_id > 0, "one published FPM success must return an ID")
        check(revision() == first + 1, "exactly one CAS revision increment")
        read = rpc("b",{"op":"read","id":created_id})
        check(read["status"] == "success", "independent get_element readback")
        check(read["result"].get("name") == fixture + "_" + winner,
              "the winning node's chunk was persisted")
        print("FPM_TWO_CONTAINERS_SINGLE_CAS_WINNER_OK", flush=True)

        observed = rpc("b", {
            "op": "state",
            "read_set": [{"role":"target","type":"chunk","id":created_id}],
        })
        check(observed["status"]=="success" and observed["state"].get("read_set"),
              "fingerprint readback available")
        changes = two_requests(pool,"update",{
            "revision":int(observed["state"]["site_revision"]),
            "id":created_id,
            "read_set":observed["state"]["read_set"],
        })
        writer = next(node for node in changes if changes[node]["status"]=="success")
        read = rpc("a",{"op":"read","id":created_id})
        check(read["status"]=="success"
              and read["result"].get("snippet",read["result"].get("content"))
              == "fpm-changed-" + writer,"same-target update readback")
        check(revision() == first + 2, "two contested rounds made exactly two writes")
        print("FPM_TWO_CONTAINERS_SAME_TARGET_CAS_FINGERPRINT_OK", flush=True)

        before_crash = revision()
        pending = pool.submit(rpc,"a",{"op":"hold"},50)
        wait_file(shared / "hold-ready")
        blocked = rpc("b",{"op":"timeout"},20)
        check(blocked["status"] == "lock-denied"
              and blocked.get("error_prefix","").startswith("CAS_LOCK_UNAVAILABLE:"),
              "second FPM must time out without executing a callback")
        check(not pending.done(), "first container stopped holding lock too early")
        print("FPM_TWO_CONTAINERS_LOCK_TIMEOUT_FAILS_CLOSED_OK", flush=True)
        subprocess.run(
            ["docker","kill","-s","KILL","mcp-fpm-a"],check=True,
            stdout=subprocess.PIPE,stderr=subprocess.PIPE,timeout=15)
        try:
            result = pending.result(timeout=12)
            check(result.get("status") != "success",
                  "killed PHP-FPM operation reported success")
        except (urllib.error.HTTPError,urllib.error.URLError,TimeoutError,
                ConnectionError,ValueError):
            pass  # Client sees no confirmed success after peer death.
        probe = rpc("b",{"op":"probe"})
        check(probe["status"]=="success" and probe["result"]=="lock-acquired",
              "independent FPM failed to reacquire named DB lock after kill")
        check(revision()==before_crash,
              "uncommitted CAS revision survived container crash")
        print("FPM_CONTAINER_SIGKILL_RELEASES_DB_LOCK_AND_ROLLS_BACK_OK",flush=True)
finally:
    if created_id > 0:
        deleted = rpc("b",{"op":"delete","id":created_id})
        check(deleted["status"]=="success",
              "could not delete isolated test chunk through MCP")
print("FPM_REAL_TWO_CONTAINER_CAS_MATRIX_OK",flush=True)
