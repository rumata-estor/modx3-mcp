#!/usr/bin/env python3
"""Full HTTP boundary test, disposable MODX2/3 CI ONLY.

A localhost HTTP PHP server exercises the shipped api.php wrapper, JSON
parser, token gate and signed-permit header. Business operations are ONLY
performed through HTTP MODX MCP. The private Ed25519 key is only in RUNNER_TEMP
and is destroyed with the CI job; no production credentials or hosts.
"""
import base64
import json
import os
from pathlib import Path
import secrets
import socket
import subprocess
import time
from urllib import error, request

root = Path(__file__).resolve().parents[1]
tmp = Path(os.environ.get("RUNNER_TEMP", ""))
config = Path(os.environ.get("MODX_CONFIG_CORE", ""))
assert os.environ.get("MODXMCP_DISPOSABLE_CI") == "1"
assert str(config).startswith("/home/runner/") and config.is_file()
assert str(tmp).startswith("/home/runner/") and tmp.is_dir()
site_root = tmp / "modx-site"
assert (site_root / "assets/components/modxmcp/api.php").is_file()
controller = root / "_build/test.ci-ed25519-controller.cjs"
fixture = root / "_build/test.ci-protected-http-settings.php"
router = root / "_build/test.ci-protected-http-router.php"
private = tmp / ("http-only-" + secrets.token_hex(6) + ".pem")
public_file = tmp / ("http-pub-" + secrets.token_hex(6) + ".txt")
token_file = tmp / ("http-token-" + secrets.token_hex(6) + ".txt")
platform = os.environ.get("BUILD_PLATFORM", "")
assert platform in ("modx2", "modx3")
site = "ci-http-" + platform
token = ""
checked = 0
server = None
log_handle = None

def run(argv, input_data=None):
    result = subprocess.run(
        argv, input=input_data, text=True, capture_output=True,
        cwd=str(root), check=False, timeout=40,
    )
    if result.returncode:
        # Avoid dumping secrets from request stdin.
        raise RuntimeError(
            f"Fixture process exited {result.returncode}: "
            + result.stderr[-500:]
        )
    return result.stdout.strip()

def expect(ok, what):
    global checked
    if not ok:
        raise RuntimeError("PROTECTED_HTTP_CI_FAILURE " + what)
    checked += 1

def protected_call(action, data, element_type=""):
    body = json.dumps({
        "action": action, "type": element_type, "data": data
    }, separators=(",", ":"), ensure_ascii=False).encode("utf-8")
    scope = element_type or data.get("type") or "none"
    signed = run(
        ["node", str(controller), "sign", str(private),
         site, action, scope],
        body.decode("utf-8"),
    )
    permit = json.loads(signed)
    return body, permit

def post(url, body, permit=None, authorized=True):
    headers = {"Content-Type": "application/json; charset=utf-8"}
    if authorized:
        headers["X-MCP-Token"] = token
    if permit is not None:
        headers["X-MODXMCP-PERMIT"] = base64.b64encode(
            json.dumps(permit, separators=(",", ":")).encode()
        ).decode("ascii")
    req = request.Request(url, data=body, headers=headers, method="POST")
    try:
        with request.urlopen(req, timeout=15) as resp:
            status = resp.status
            raw = resp.read()
    except error.HTTPError as exc:
        status = exc.code
        raw = exc.read()
    try:
        reply = json.loads(raw.decode("utf-8"))
    except Exception:
        raise RuntimeError(f"Invalid JSON from HTTP MCP, status={status}")
    return status, reply

def read(url, action, data=None):
    body = json.dumps({"action": action, "type": "", "data": data or {}},
                      separators=(",", ":")).encode()
    status, result = post(url, body)
    expect(status == 200 and result.get("success") is True,
           "MCP read failed: " + action)
    return result["data"]

def deny(url, body, permit, description, code=403):
    status, result = post(url, body, permit)
    expect(status == code and result.get("success") is False,
           f"{description}: expected HTTP {code}, got {status}")

try:
    pub = run(["node", str(controller), "generate", str(private)])
    expect(len(pub) == 64, "public key generated")
    with socket.socket() as sock:
        sock.bind(("127.0.0.1", 0))
        port = sock.getsockname()[1]
    url = f"http://127.0.0.1:{port}/assets/components/modxmcp/api.php"
    serverlog = tmp / ("ci-http-" + secrets.token_hex(6) + ".log")
    log_handle = serverlog.open("wb")
    server = subprocess.Popen(
        ["php", "-S", f"127.0.0.1:{port}", "-t", str(site_root)],
        cwd=str(site_root), stdout=log_handle, stderr=subprocess.STDOUT,
        env={**os.environ,
             "MODX_CONFIG_CORE": str(config),
             "MODX_DOCUMENT_ROOT": str(site_root)},
    )
    for _ in range(80):
        if server.poll() is not None:
            raise RuntimeError("PHP test webserver terminated")
        try:
            status, result = post(
                url, b'{"action":"get_site_state","type":"","data":{}}',
            )
            if status == 403 and "HTTPS required" in str(result.get("error", "")):
                break
        except error.URLError:
            time.sleep(0.1)
    else:
        raise RuntimeError("Expected fail-closed HTTP instead of HTTPS")
    expect(status == 403, "plaintext HTTP blocked by default")
    # A client must not be able to bypass TLS validation with an arbitrary
    # proxy header when modxmcp.trust_proxy_https is disabled.
    forwarded = request.Request(
        url, data=b'{"action":"get_site_state","type":"","data":{}}',
        headers={"X-Forwarded-Proto": "https"}, method="POST")
    try:
        with request.urlopen(forwarded, timeout=10) as response:
            spoof_status=response.status
    except error.HTTPError as exc:
        spoof_status=exc.code
    expect(spoof_status==403, "forged X-Forwarded-Proto cannot bypass HTTPS")

    # For functional requests, a CI-only PHP router models an upstream TLS
    # terminator by setting server-side HTTPS=on. The installed requirement
    # for HTTPS itself stays ENABLED; this is not a real TLS certificate test.
    server.terminate()
    server.wait(timeout=10)
    server = None
    log_handle.close()
    log_handle = serverlog.open("ab")
    public_file.write_text(pub)
    public_file.chmod(0o600)
    run(["php", str(fixture), "enable", str(public_file), str(token_file)])
    token = token_file.read_text().strip()
    expect(len(token)==64, "existing API token preserved in private fixture")
    server = subprocess.Popen(
        ["php", "-S", f"127.0.0.1:{port}", "-t", str(site_root), str(router)],
        cwd=str(site_root), stdout=log_handle, stderr=subprocess.STDOUT,
        env={**os.environ,
             "MODX_CONFIG_CORE": str(config),
             "MODX_DOCUMENT_ROOT": str(site_root)},
    )
    for _ in range(70):
        if server.poll() is not None:
            raise RuntimeError("HTTP test router terminated")
        try:
            # GET response from the common wrapper may return version.
            with request.urlopen(url, timeout=2) as resp:
                if resp.status == 200:
                    break
        except error.URLError:
            time.sleep(0.1)
    else:
        raise RuntimeError("Disposable protected router did not start")
    status, result = post(
        url, b'{"action":"get_site_state","type":"","data":{}}',
        authorized=False,
    )
    expect(status == 401, "API token required")
    state = read(url, "get_site_state")
    revision = int(state["site_revision"])
    expect(state.get("cas_required") is True, "mandatory CAS advertised")

    label = "CI HTTP Ed25519 " + secrets.token_hex(5)
    create = {
        "type": "resource", "name": label,
        "alias": "ci-http-" + secrets.token_hex(5),
        "content": "BEFORE", "template": 0, "parent": 0,
        "published": 0, "hidemenu": 1,
        "_runtime_preconditions": {
            "observed_site_revision": revision,
            "strict_site_revision": True, "read_set": []
        }
    }
    body, signed = protected_call("create_element", create)
    deny(url, body, None, "missing Ed25519 permit")
    deny(url, body, {"foo": "bar"}, "malformed permit")
    invalid = json.loads(json.dumps(signed))
    invalid["signature"] = "0" * 128
    deny(url, body, invalid, "forged Ed25519 signature")
    invalid = json.loads(json.dumps(signed))
    invalid["claims"]["site_id"] = "ci-http-ffffffffffff"
    deny(url, body, invalid, "wrong signed site")
    invalid = json.loads(json.dumps(signed))
    invalid["claims"]["contour"] = "production"
    deny(url, body, invalid, "cross-contour permit")
    deny(url, body + b" ", signed, "changed exact body")

    # Explicit no-token check with an otherwise valid Ed25519 permit.
    status, reply = post(url, body, signed, authorized=False)
    expect(status == 401, "signed request still needs independent API token")
    expect(int(read(url, "get_site_state")["site_revision"]) == revision,
           "denied HTTP writes did not increase site revision")
    status, reply = post(url, body, signed)
    expect(status == 200 and reply.get("success") is True,
           "valid signed HTTP creation accepted")
    rid = int(reply["data"]["id"])
    expect(rid > 0, "resource ID from HTTP creation")
    status, reply = post(url, body, signed)
    expect(status == 400 and reply.get("success") is False,
           "nonce replay denied after success")

    row = read(url, "get_element", {"type": "resource", "id": rid})
    expect(int(row["published"]) == 0 and int(row["hidemenu"]) == 1,
           "MCP HTTP readback of hidden resource")
    revision = int(read(url, "get_site_state")["site_revision"])
    update = {
        "type": "resource", "id": rid, "content": "AFTER",
        "_runtime_preconditions": {
            "observed_site_revision": revision,
            "strict_site_revision": True, "read_set": []
        }
    }
    update_body, update_signed = protected_call("update_element", update)
    status, reply = post(url, update_body, update_signed)
    expect(status == 200 and reply.get("success") is True,
           "valid signed HTTP update accepted")
    row = read(url, "get_element", {"type": "resource", "id": rid})
    expect(row.get("content") == "AFTER", "HTTP update actually persisted")
    stale_body, stale_signed = protected_call("update_element", update)
    deny(url, stale_body, stale_signed, "stale CAS with valid permit",code=400)

    revision = int(read(url, "get_site_state")["site_revision"])
    deleting = {
        "type": "resource", "id": rid, "dry_run": False,
        "_runtime_preconditions": {
            "observed_site_revision": revision,
            "strict_site_revision": True, "read_set": []
        }
    }
    delete_body, delete_signed = protected_call("delete_element", deleting)
    status, reply = post(url, delete_body, delete_signed)
    expect(status == 200 and reply.get("success") is True,
           "valid signed HTTP soft-delete accepted")
    row = read(url, "get_element", {"type": "resource", "id": rid})
    expect(bool(row.get("deleted")), "HTTP MCP resource soft-deleted")

    print(f"PROTECTED_ED25519_HTTP_E2E_OK checks={checked} "
          "https_required=yes api_token=yes "
          "permit_signature=yes replay_denied=yes stale_denied=yes "
          "mutation_lifecycle=yes", flush=True)
finally:
    # These operations only manipulate disposable CI security fixture.
    if server is not None:
        server.terminate()
        try:
            server.wait(timeout=10)
        except subprocess.TimeoutExpired:
            server.kill()
            server.wait(timeout=10)
    if log_handle is not None:
        log_handle.close()
    if token_file.is_file():
        try:
            run(["php", str(fixture), "disable", str(public_file), str(token_file)])
        except Exception as exc:
            # The disposable database will be destroyed by CI, but report
            # any cleanup failures explicitly.
            print("PROTECTED_HTTP_FIXTURE_CLEANUP_FAILED " + str(exc), flush=True)
    for ephemeral in (private,public_file,token_file):
        try:
            if ephemeral.is_file():
                ephemeral.unlink()
        except OSError:
            pass
