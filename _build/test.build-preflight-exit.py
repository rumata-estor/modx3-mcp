#!/usr/bin/env python3
# MODX MCP SOURCE / ИСХОДНИК MODX MCP
# RU: Сборка без config.core.php обязана завершиться ненулевым кодом.
# EN: Both transport builders must exit nonzero when MODX bootstrap is missing.
import os
import pathlib
import shutil
import subprocess
import tempfile

root = pathlib.Path(__file__).resolve().parents[1]
builders = {
    'modx3': root / '_build/build.transport.php',
    'modx2': root / '_build/platform/modx2/build.transport.php',
}
for variant, script in builders.items():
    with tempfile.TemporaryDirectory(prefix='modxmcp-preflight-') as tmp:
        folder = pathlib.Path(tmp) / 'src' / '_build'
        folder.mkdir(parents=True)
        shutil.copy2(script, folder / 'build.transport.php')
        shutil.copy2(root / '_build/build.config.php', folder / 'build.config.php')
        env = os.environ.copy()
        env['MODX_CONFIG_CORE'] = str(folder / 'missing-config.core.php')
        env.pop('MODX_DOCUMENT_ROOT', None)
        result = subprocess.run(
            ['php', str(folder / 'build.transport.php')],
            cwd=str(folder), env=env, capture_output=True, text=True,
            timeout=10, check=False,
        )
        if result.returncode != 2 or 'cannot find config.core.php' not in (
            result.stderr + result.stdout
        ):
            raise AssertionError(
                f'{variant}: missing config must exit 2, got '
                f'{result.returncode}; output={result.stdout!r} {result.stderr!r}'
            )
        print(f'BUILD_PREFLIGHT_MISSING_CONFIG_OK platform={variant} exit=2')
