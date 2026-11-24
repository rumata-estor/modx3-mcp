#!/usr/bin/env python3
# MODX MCP SOURCE / ИСХОДНИК MODX MCP
# RU: В CI меняем симлинки конкурентно, читает только штатный PHP.
# EN: CI-only adversarial swaps; the runtime being tested is PHP-only.
"""Best-effort hostile writer regression, NOT proof of atomic no-TOCTOU.

Python orchestrates a CI race only. The installed MODX component does not
ship or call Python. A transient swap may remain undetectable in portable PHP.
"""
import json
import os
from pathlib import Path
import subprocess
import tempfile
import threading

READER = Path(__file__).resolve().parents[1] / (
    'core/components/modxmcp/src/Tools/FilesystemSupport.php'
)
PHP_DRIVER = r"""<?php
class ModxMCPClientException extends RuntimeException {}
require_once $argv[1];
class RaceModx {
    public function getOption($name, $options=null, $default=null) {
        return $name === 'modxmcp.max_read_bytes' ? 262144 : $default;
    }
}
try {
    $data = ModxMcp\Tools\FilesystemSupport::readComponentFileChecked(
        new RaceModx(), $argv[2], $argv[3], array('bytes'=>256)
    );
    echo json_encode($data);
} catch (Throwable $e) {
    fwrite(STDERR, 'EXPECTED_COMPONENT_READ_REFUSAL');
    exit(3);
}
"""

with tempfile.TemporaryDirectory(prefix='mcp-component-php-race-') as tmp:
    top = Path(tmp)
    component = top / 'components'
    private = top / 'private'
    component.mkdir()
    private.mkdir()
    driver = top / 'driver.php'
    driver.write_text(PHP_DRIVER, encoding='utf8')
    (private / 'secret.txt').write_text('UNREADABLE_PRIVATE_MARKER', encoding='utf8')
    victim = component / 'target.txt'
    victim.write_text('ALLOWED_COMPONENT_CONTENT', encoding='utf8')
    stop = threading.Event()
    swaps = [0]

    def adversary():
        while not stop.is_set():
            staged = component / 'swap-file'
            try:
                staged.symlink_to(private / 'secret.txt')
                os.replace(staged, victim)
                staged.write_text('ALLOWED_COMPONENT_CONTENT', encoding='utf8')
                os.replace(staged, victim)
                swaps[0] += 1
            except FileExistsError:
                try:
                    staged.unlink()
                except FileNotFoundError:
                    pass

    attacker = threading.Thread(target=adversary, daemon=True)
    attacker.start()
    seen = {'safe': 0, 'refused': 0}
    try:
        for _ in range(60):
            cp = subprocess.run(
                ['php', str(driver), str(READER), str(component), 'target.txt'],
                capture_output=True, text=True, timeout=6
            )
            if 'UNREADABLE_PRIVATE_MARKER' in cp.stdout:
                raise RuntimeError('COMPONENT_PHP_SYMLINK_RACE_SECRET_LEAK')
            if cp.returncode == 0:
                result = json.loads(cp.stdout)
                if result['content'] != 'ALLOWED_COMPONENT_CONTENT':
                    raise RuntimeError('COMPONENT_PHP_SYMLINK_RACE_UNEXPECTED_CONTENT')
                seen['safe'] += 1
            elif cp.returncode == 3:
                seen['refused'] += 1
            else:
                raise RuntimeError('COMPONENT_PHP_SYMLINK_RACE_BAD_EXIT '
                                   + str(cp.returncode) + ' ' + cp.stderr[:200])
    finally:
        stop.set()
        attacker.join(timeout=5)
    if sum(seen.values()) != 60 or swaps[0] == 0:
        raise RuntimeError('COMPONENT_PHP_SYMLINK_RACE_INCOMPLETE')
    print('COMPONENT_PHP_SYMLINK_RACE_BEST_EFFORT_OK', seen, 'swaps', swaps[0])
