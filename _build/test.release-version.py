# MODX MCP SOURCE / ИСХОДНИК MODX MCP
# RU: Проверка идентичности версии в подготовленных пакетах.
# EN: Verify candidate release identity across staged packages.
from pathlib import Path
import subprocess
import tempfile
import json
import sys
from release_version import load_release

root = Path(__file__).resolve().parents[1]
version, release, node = load_release()
with tempfile.TemporaryDirectory() as td:
    temp = Path(td)
    for platform in ('modx2', 'modx3'):
        out = temp / platform
        subprocess.run([sys.executable, str(root / '_build/prepare-release.py'),
                        '--platform', platform, '--output', str(out), '--candidate'], check=True)
        config = (out / '_build/build.config.php').read_text()
        model = (out / 'core/components/modxmcp/model/modxmcp.class.php').read_text()
        assert f"define('PKG_VERSION', '{version}');" in config
        assert f"define('PKG_RELEASE', '{release}');" in config
        assert f"define('PKG_NAME', '{platform.upper()}MCP');" in config
        assert f"const VERSION = '{version}';" in model
        assert not (out / 'core/components/modxmcp/legacy').exists()
    probe = temp / 'release.json'
    for v, r, n in [('1.2.8','beta6','1.2.8-beta.6'), ('1.2.9','rc2','1.2.9-rc.2'), ('2.0.0','pl','2.0.0')]:
        probe.write_text(json.dumps({'version': v, 'release': r}))
        assert load_release(probe) == (v, r, n)
    for v, r in [('1.2','beta5'), ('1.2.8','beta0'), ('1.2.8','beta5\nX=bad')]:
        probe.write_text(json.dumps({'version': v, 'release': r}))
        try:
            load_release(probe)
        except ValueError:
            continue
        raise AssertionError('Invalid release accepted')
print('RELEASE_VERSION_OK', version, release, node)
