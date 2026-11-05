#!/usr/bin/env python3
"""Functional regression: spoofed X-Forwarded-Proto may not bypass MODX2 HTTPS.

Runs a literal copy of the legacy endpoint against a tiny throwaway MODX
fixture. No token, database, network, or production CMS is touched.
"""
from __future__ import annotations

import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile

ROOT = Path(__file__).resolve().parents[1]
ENDPOINT = ROOT / "core/components/modxmcp/legacy/modx2/api.php"
PHP = os.environ.get("PHP") or shutil.which("php")
if not PHP:
    raise SystemExit("php interpreter is required")

MODX_STUB = """<?php
class modX {
    const LOG_LEVEL_ERROR = 3;
    public function initialize($context) {}
    public function getService($name, $className) { return null; }
    public function setLogLevel($level) {}
    public function getOption($key, $unused = null, $default = null) {
        if ($key === 'modxmcp.enabled') { return true; }
        if ($key === 'modxmcp.require_https') { return true; }
        if ($key === 'modxmcp.trust_proxy_https') {
            return getenv('TEST_TRUST_PROXY') === '1';
        }
        if ($key === 'modxmcp.api_token') { return 'synthetic-test-token'; }
        return $default;
    }
}
"""

RUNNER = """<?php
$_SERVER['REQUEST_METHOD']='POST';
$_SERVER['SERVER_PORT']='80';
unset($_SERVER['HTTPS']);
if (getenv('TEST_FORWARDED') === '1') {
    $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
}
if (getenv('TEST_DIRECT') === '1') {
    $_SERVER['HTTPS'] = 'on';
}
require __DIR__.'/assets/components/modxmcp/api.php';
"""

with tempfile.TemporaryDirectory(prefix="modx2-https-boundary-") as directory:
    root = Path(directory)
    # The legacy source is staged as assets/components/modxmcp/api.php.
    endpoint = root / "assets/components/modxmcp/api.php"
    endpoint.parent.mkdir(parents=True)
    shutil.copyfile(ENDPOINT, endpoint)
    model = root / "core/model/modx/modx.class.php"
    model.parent.mkdir(parents=True)
    model.write_text(MODX_STUB)
    (root / "config.core.php").write_text(
        "<?php define('MODX_CORE_PATH', " + json.dumps(str(root / "core") + "/") + ");"
    )
    launch = root / "run.php"
    launch.write_text(RUNNER)

    def check(trust: bool, forwarded: bool, direct: bool, expected: str):
        env = {**os.environ,
               "TEST_TRUST_PROXY": "1" if trust else "0",
               "TEST_FORWARDED": "1" if forwarded else "0",
               "TEST_DIRECT": "1" if direct else "0"}
        result = subprocess.run(
            (PHP, "-d", "display_errors=0", str(launch)),
            env=env, capture_output=True, text=True, timeout=8
        )
        assert result.returncode == 0, result.stderr
        assert expected in result.stdout, (trust, forwarded, direct, result.stdout)

    check(False, True, False, "HTTPS required")
    check(False, False, False, "HTTPS required")
    check(True, True, False, "Unauthorized")
    check(False, True, True, "Unauthorized")

print("MODX2_HTTPS_SPOOFED_PROXY_DENIED_OK")
