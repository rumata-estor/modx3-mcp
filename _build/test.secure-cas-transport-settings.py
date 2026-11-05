"""No new tables. Default-off signed CAS settings in MODX2/3 installers.

All six configuration keys must be seeded without enabling protected HTTP
before an operator provisions private key files and a trusted Executor.
Existing user settings must be preserved across package upgrades.
"""
from pathlib import Path
import re

root=Path(__file__).resolve().parents[1]
pairs={
    'modxmcp.external_authorization_required':('0','combo-boolean'),
    'modxmcp.permit_audience':('', 'textfield'),
    'modxmcp.permit_site_id':('', 'textfield'),
    'modxmcp.permit_contour':('', 'textfield'),
    'modxmcp.permit_key_file':('', 'textfield'),
    'modxmcp.permit_nonce_directory':('', 'textfield'),
}
files=[
 root/'_build/data/transport.settings.php',
 root/'_build/install.headless.php',
 root/'_build/platform/modx2/overlay/_build/install.headless.php',
]
for path in files:
    src=path.read_text()
    for key,(default,xtype) in pairs.items():
        lines=[line.strip() for line in src.splitlines() if
               line.lstrip().startswith(("array('"+key+"'", "'"+key+"' => array("))]
        assert len(lines)==1,(path,key,lines)
        line=lines[0]
        assert "'"+xtype+"'" in line,(path,key,xtype)
        assert "'modxmcp:security'" in line,(path,key)
        if default=='0':
            assert re.search(r"(?<![a-z])0\s*,",line),(path,key)
        else:
            assert "'', 'textfield'" in line,(path,key)
    # No accidental duplicate or missing keys for the complete transport
    if path.name=='transport.settings.php':
        assert src.count("    array('modxmcp.")==22
    else:
        assert len(re.findall(r"^    'modxmcp\.[a-z_]+\s*'\s*=>",src,re.M))==22,path

builder=(root/'_build/build.transport.php').read_text()
assert 'xPDOTransport::UPDATE_OBJECT => false' in builder, 'package upgrades must preserve administrator settings'
assert 'xPDOTransport::PRESERVE_KEYS => true' in builder
for p in (
    root/'_build/smoke.endpoint.php',
    root/'_build/platform/modx2/overlay/_build/smoke.endpoint.php',
):
    s=p.read_text()
    assert 'count($settings) !== 22' in s
    assert 'count($settings) > 22' in s
    assert 'count($restore) > 22' in s
for p in (
    root/'_build/install.transport.php',
    root/'_build/platform/modx2/overlay/_build/install.transport.php',
):
    s=p.read_text()
    assert ('$expectedSettings = 22' in s or '$countSettings===22' in s),p
for p in (
    root/'core/components/modxmcp/endpoint/chunk-cas-processor-v2.php',
    root/'tests/ci-protected-cas-processor-v2.php',
):
    body=p.read_text()
    for prohibited in ('CREATE TABLE','DROP TABLE','ALTER TABLE','auditTable'):
        assert prohibited not in body,(p,prohibited)
print('DEFAULT_OFF_SIGNED_CAS_TRANSPORT_SETTINGS_OK',len(files),'platform installers')
