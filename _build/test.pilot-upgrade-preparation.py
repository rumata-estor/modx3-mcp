#!/usr/bin/env python3
"""Source-only safety gate for a distinct, temporary CAS v2 test transport."""
from pathlib import Path
import re
import subprocess
import sys
from tempfile import TemporaryDirectory

root=Path(__file__).resolve().parents[1]
originals={
  'modx3':root/'_build/build.config.php',
  'modx2':root/'_build/platform/modx2/overlay/_build/build.config.php',
}
saved={k:p.read_bytes() for k,p in originals.items()}
with TemporaryDirectory(prefix='modxmcp-pilot-upgrade-') as temp:
    for platform in ('modx2','modx3'):
        for option,expected_v,expected_release in (
            ([], '1.2.5','pl'),
            (['--pilot-test-upgrade'],'1.2.6','dev'),
            (['--release-126-pl'],'1.2.6','pl'),
        ):
            stage=Path(temp)/(platform+'-'+expected_release)
            subprocess.run([sys.executable,str(root/'_build/prepare-release.py'),
                '--platform',platform,'--output',str(stage),*option],
                check=True,capture_output=True,text=True)
            cfg=(stage/'_build/build.config.php').read_text()
            model=(stage/'core/components/modxmcp/model/modxmcp.class.php').read_text()
            assert re.search(
                r"define\('PKG_VERSION', '"+re.escape(expected_v)+r"'\);",
                cfg,
            ),(platform,expected_v)
            assert "define('PKG_RELEASE', '"+expected_release+"');" in cfg
            assert "const VERSION = '"+expected_v+"';" in model
            marker=stage/'PILOT_TEST_PACKAGE_DO_NOT_PUBLISH'
            assert marker.is_file()==(expected_release=='dev')
            assert "define('PKG_NAMESPACE', 'modxmcp');" in cfg
    for platform,source in originals.items():
        assert source.read_bytes()==saved[platform], 'source release metadata was edited'
assert all(b"define('PKG_VERSION', '1.2.5');" in b for b in saved.values())
print('DISTINCT_CAS_PILOT_TEST_TRANSPORT_STAGING_OK')
