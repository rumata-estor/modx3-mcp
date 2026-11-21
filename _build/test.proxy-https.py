#!/usr/bin/env python3
# MODX MCP SOURCE / ИСХОДНИК MODX MCP
# RU: Проверка «proxy-https»; тестовый код не запускается на production.
# EN: «proxy-https» regression test; not executed in production.
"""Offline fail-closed reverse-proxy HTTPS policy matrix; no MODX/database."""
import json
import os
from pathlib import Path
import subprocess

ROOT = Path(__file__).resolve().parents[1]
FIXTURE = ROOT / "_build/test.proxy-https-fixture.php"
PHP = os.environ.get("PHP_BINARY", "php")
CASES = [
    ("proxy_trust_disabled", 0, "127.0.0.1", "127.0.0.1", "https", False),
    ("missing_trusted_proxies", 1, "", "127.0.0.1", "https", False),
    ("untrusted_socket_peer", 1, "192.0.2.1", "127.0.0.1", "https", False),
    ("trusted_socket_peer", 1, "127.0.0.1", "127.0.0.1", "https", True),
    ("forwarded_http", 1, "127.0.0.1", "127.0.0.1", "http", False),
    ("forwarded_multiple_values", 1, "127.0.0.1", "127.0.0.1", "https,http", False),
    ("reject_cidr_proxy_entry", 1, "127.0.0.1/8", "127.0.0.1", "https", False),
    ("trusted_ipv6_peer", 1, "::1", "::1", "https", True),
    ("untrusted_ipv6_peer", 1, "::2", "::1", "https", False),
    ("case_insensitive_https", 1, "127.0.0.1", "127.0.0.1", "HTTPS", True),
    ("reject_hostname_entry", 1, "localhost", "127.0.0.1", "https", False),
    ("reject_fake_ip", 1, "999.999.999.999", "127.0.0.1", "https", False),
    ("deny_unlisted_peer_in_csv", 1, "192.0.2.1,192.0.2.2", "127.0.0.1", "https", False),
    ("allow_listed_peer_in_csv", 1, "192.0.2.1,127.0.0.1", "127.0.0.1", "https", True),
]
for name, trust, allowlist, peer, header, expected in CASES:
    result = subprocess.run(
        [PHP, str(FIXTURE), str(trust), allowlist, peer, header],
        cwd=str(ROOT), capture_output=True, text=True, check=False, timeout=10,
    )
    try:
        payload = json.loads(result.stdout)
    except ValueError as error:
        raise AssertionError(f"{name}: non-JSON response: {result.stderr[-200:]}") from error
    accepted = payload.get("component") == "modxMCP"
    assert result.returncode == 0 and accepted == expected, (
        f"{name}: expected accepted={expected}, got {accepted}, "
        f"stderr={result.stderr[-200:]}"
    )
print(f"PROXY_HTTPS_NEGATIVE_MATRIX_OK checks={len(CASES)}")
