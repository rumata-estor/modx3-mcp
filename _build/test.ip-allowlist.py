#!/usr/bin/env python3
# MODX MCP SOURCE / ИСХОДНИК MODX MCP
# RU: Проверка «ip-allowlist»; тестовый код не запускается на production.
# EN: «ip-allowlist» regression test; not executed in production.
"""Offline IPv4 CIDR allowlist regression; isolated fake MODX, no database."""
import json
import os
from pathlib import Path
import subprocess

ROOT = Path(__file__).resolve().parents[1]
PHP = os.environ.get("PHP_BINARY", "php")
FIXTURE = ROOT / "_build/test.ip-allowlist-fixture.php"
CASES = [
    ("malformed_mask", "192.0.2.0/bad", "127.0.0.1", False),
    ("empty_mask", "192.0.2.0/", "127.0.0.1", False),
    ("suffix_mask", "192.0.2.0/0evil", "127.0.0.1", False),
    ("negative_mask", "192.0.2.0/-1", "127.0.0.1", False),
    ("hex_mask", "192.0.2.0/0x", "127.0.0.1", False),
    ("oversized_mask", "192.0.2.0/33", "127.0.0.1", False),
    ("valid_exact", "127.0.0.1/32", "127.0.0.1", True),
    ("valid_subnet", "127.0.0.0/24", "127.0.0.1", True),
    ("unlisted_peer", "192.0.2.0/24", "127.0.0.1", False),
    ("exact_ip", "127.0.0.1", "127.0.0.1", True),
]
for name, mask, peer, expected in CASES:
    run = subprocess.run(
        [PHP, str(FIXTURE), mask, peer, "nonempty-test-token"],
        capture_output=True, text=True, cwd=ROOT, timeout=10, check=False,
    )
    try:
        data = json.loads(run.stdout)
    except ValueError as error:
        raise AssertionError(f"{name}: invalid JSON: {run.stderr[-200:]}") from error
    # An allowed peer proceeds past the IP gate and reaches JSON request validation.
    allowed = not str(data.get("error", "")).startswith("Forbidden: client IP")
    assert run.returncode == 0 and allowed == expected, (
        f"{name}: expected={expected} actual={allowed}; stderr={run.stderr[-200:]}"
    )
print(f"IP_ALLOWLIST_CIDR_MATRIX_OK checks={len(CASES)}")
