#!/usr/bin/env python3
# MODX MCP SOURCE / ИСХОДНИК MODX MCP
# RU: На одноразовом дереве проверяем гонку симлинка при чтении компонентов.
# EN: Verify concurrent symlink swaps cannot escape a pinned component root.
import json
import os
from pathlib import Path
import subprocess
import sys
import tempfile
import threading

HELPER = Path(__file__).resolve().parents[1] / (
    'core/components/modxmcp/src/Security/component_read_guard.py'
)

with tempfile.TemporaryDirectory(prefix='mcp-component-race-') as tmp:
    top = Path(tmp)
    component = top / 'components'
    private = top / 'private'
    component.mkdir()
    private.mkdir()
    (private / 'secret.txt').write_text('UNREADABLE_PRIVATE_MARKER', encoding='utf8')
    victim = component / 'target.txt'
    victim.write_text('ALLOWED_COMPONENT_CONTENT', encoding='utf8')
    stop = threading.Event()
    races = [0]

    def adversary():
        while not stop.is_set():
            staged = component / 'stage-symlink'
            try:
                staged.symlink_to(private / 'secret.txt')
                os.replace(staged, victim)
                staged.write_text('ALLOWED_COMPONENT_CONTENT', encoding='utf8')
                os.replace(staged, victim)
                races[0] += 1
            except FileExistsError:
                try:
                    staged.unlink()
                except FileNotFoundError:
                    pass

    attack = threading.Thread(target=adversary, daemon=True)
    attack.start()
    observed = {'safe': 0, 'refused': 0}
    try:
        for _ in range(45):
            cp = subprocess.run(
                [sys.executable, str(HELPER), str(component), 'target.txt', '0', '256'],
                capture_output=True, text=True, timeout=5
            )
            if 'UNREADABLE_PRIVATE_MARKER' in cp.stdout:
                raise RuntimeError('COMPONENT_SYMLINK_RACE_SECRET_LEAK')
            if cp.returncode == 0:
                row = json.loads(cp.stdout)
                if row['content'] != 'ALLOWED_COMPONENT_CONTENT':
                    raise RuntimeError('COMPONENT_SYMLINK_RACE_UNEXPECTED_CONTENT')
                observed['safe'] += 1
            elif cp.returncode == 3:
                observed['refused'] += 1
            else:
                raise RuntimeError('COMPONENT_SYMLINK_RACE_BAD_EXIT ' + str(cp.returncode))
    finally:
        stop.set()
        attack.join(timeout=4)
    if sum(observed.values()) != 45:
        raise RuntimeError('COMPONENT_SYMLINK_RACE_MISSING_RESULT')
    print('COMPONENT_SYMLINK_RACE_BOUNDARY_OK', observed, 'swaps', races[0])
