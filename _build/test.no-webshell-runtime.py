#!/usr/bin/env python3
"""Fail-closed source hygiene for deployable MODX MCP PHP/Node code.

This is a signature-based release guard, NOT a proof of no malware.
Test-only process spawning and historical vendor files are outside its scope.
"""
from __future__ import annotations

from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
ROOTS = (
    ROOT / "core/components/modxmcp",
    ROOT / "assets/components/modxmcp",
    ROOT / "client",
)
PHP_UNSAFE = re.compile(
    r"(?i)\b(?:eval|shell_exec|passthru|popen|proc_open|create_function|"
    r"unserialize|assert|system|exec|gzinflate|gzuncompress|str_rot13)\s*\("
)
JS_UNSAFE = re.compile(
    r"\b(?:eval\s*\(|new\s+Function\s*\(|"
    r"require\s*\(\s*['\"]vm['\"]\s*\)|"
    r"child_process\.exec\s*\(|vm\.runInNewContext\s*\()"
)
NODE_PROCESS_IMPORT = re.compile(r"require\s*\(\s*['\"]child_process['\"]\s*\)")
NODE_PROCESS_INVOCATION = re.compile(
    r"\b(?:spawn|spawnSync|exec|execSync|execFile|execFileSync|fork)\s*\("
)
ENCODED_PHP = re.compile(r"(?i)\bbase64_decode\s*\(")
SECRET = re.compile(
    r"(?:ghp_[A-Za-z0-9]{25,}|github_pat_[A-Za-z0-9_]{25,}|"
    r"-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----)"
)
malware = []
checked = 0
for base in ROOTS:
    assert base.is_dir()
    for file in sorted(base.rglob("*")):
        if not file.is_file() or file.suffix.lower() not in (".php", ".js", ".mjs"):
            continue
        if any(name in ("node_modules", "vendor", "__pycache__") for name in file.parts):
            continue
        checked += 1
        text = file.read_text(encoding="utf-8", errors="replace")
        relative = file.relative_to(ROOT).as_posix()
        if SECRET.search(text):
            malware.append((relative, "possible source-embedded secret"))
        if file.suffix.lower() == ".php":
            if PHP_UNSAFE.search(text):
                malware.append((relative, "dynamic OS/PHP execution primitive"))
            for match in ENCODED_PHP.finditer(text):
                # The only reviewed decoding is strict decoding of a signed
                # transport header. It is never passed to a PHP evaluator.
                if not (
                    relative == "core/components/modxmcp/endpoint/protected-api.php"
                    and text[match.start():].startswith("base64_decode($wire, true)")
                ):
                    malware.append((relative, "unreviewed base64 payload decoder"))
        else:
            if JS_UNSAFE.search(text):
                malware.append((relative, "dynamic Node execution primitive"))
            if NODE_PROCESS_IMPORT.search(text) or NODE_PROCESS_INVOCATION.search(text):
                # The single historical local audit hook is operator-configured.
                # It uses spawnSync without a shell and supplies no command
                # arguments. Other Node process launch sites are forbidden.
                trusted_hook = (
                    relative == "client/index.js"
                    and text.count('const { spawnSync } = require("child_process");') == 1
                    and len(NODE_PROCESS_IMPORT.findall(text)) == 1
                    and len(NODE_PROCESS_INVOCATION.findall(text)) == 1
                    and 'spawnSync(AUDIT_HOOK, [], {' in text
                    and 'timeout: 5000' in text
                    and 'maxBuffer: 1024 * 1024' in text
                    and not re.search(r'\bshell\s*:\s*true\b', text)
                )
                if not trusted_hook:
                    malware.append((relative, "unreviewed Node OS process launch"))
        if re.search(r"(?i)\b(?:include|require)(?:_once)?\s*\(\s*['\"]https?://", text):
            malware.append((relative, "remote PHP loader"))
        if re.search(r"(?i)\b(?:curl_exec|file_get_contents)\s*\(\s*['\"]https?://", text):
            malware.append((relative, "hardcoded remote executable content source"))
assert checked > 200, "source scope unexpectedly shrank"
assert not malware, malware
print(f"MODXMCP_RELEASE_WEB_SHELL_SIGNATURE_GUARD_OK sources={checked}")
