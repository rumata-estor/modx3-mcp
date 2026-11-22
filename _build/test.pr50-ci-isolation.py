#!/usr/bin/env python3
# MODX MCP SOURCE / ИСХОДНИК MODX MCP
# RU: Нельзя запускать CI-сценарий Controller v4 на реальных сайтах.
# EN: Controller v4 integration fixture must reject non-disposable environments.
import os
from pathlib import Path
import subprocess
import sys

root = Path(__file__).resolve().parents[1]
php_script = root / '_build/test.ci-protected-v4-proxy-settings.php'
python_script = root / '_build/test.ci-siteagent-v4-real-tls.py'
for optimized in (False, True):
    env = dict(os.environ)
    env.pop('MODXMCP_DISPOSABLE_CI', None)
    env.pop('SITEAGENT_V4_ROOT', None)
    cmd = [sys.executable] + (['-O'] if optimized else []) + [str(python_script)]
    run = subprocess.run(cmd, env=env, capture_output=True, text=True, timeout=15)
    if run.returncode == 0:
        raise RuntimeError('CI_V4_UNSAFE_START_WITHOUT_DISPOSABLE_FLAG')
    if 'Refusing Controller v4 E2E outside disposable CI.' not in run.stderr:
        raise RuntimeError('CI_V4_MISSING_EXPLICIT_FAIL_CLOSED: ' + run.stderr[-400:])
env = dict(os.environ)
env.pop('MODXMCP_DISPOSABLE_CI', None)
for action in ('enable', 'rotate-key'):
    run = subprocess.run(
        ['php', str(php_script), action, 'modx2-alex-palochkin'],
        env=env, capture_output=True, text=True, timeout=15,
    )
    if run.returncode != 3 or 'Refusing protected v4 settings outside disposable CI.' not in run.stderr:
        raise RuntimeError('CI_V4_UNSAFE_POLICY_SETUP_' + action)
print('CI_PR50_PRIVATE_E2E_ISOLATION_OK checks=4')
