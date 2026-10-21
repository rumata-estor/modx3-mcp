#!/usr/bin/env python3
"""Check that localized essential guides retain English functional coverage."""
from pathlib import Path
import re

ROOT=Path(__file__).resolve().parent.parent
LOCALES=('es','de','fr','uk','kk','nl','pl')
REFERENCE=(ROOT/'docs/CAPABILITIES.md').read_text(encoding='utf-8')
TOOLS=re.findall(r'^\| \u0060(modx_[a-z0-9_]+)\u0060 \|',REFERENCE,re.M)
assert len(TOOLS)==192 and len(set(TOOLS))==192
SETTINGS=re.findall(r'^\| \u0060(modxmcp\.[a-z0-9_]+)\u0060 \|',
                    (ROOT/'docs/SETTINGS.md').read_text(encoding='utf-8'),re.M)
assert len(SETTINGS)==16 and len(set(SETTINGS))==16
for lang in LOCALES:
    base=ROOT/'docs'
    files={name:(base/f'{name}.{lang}.md').read_text(encoding='utf-8')
           for name in ('INSTALL','SECURITY','SETTINGS','CAPABILITIES')}
    localized=re.findall(r'^\| \u0060(modx_[a-z0-9_]+)\u0060 \|',files['CAPABILITIES'],re.M)
    assert localized==TOOLS,(lang,'tool count/order mismatch',len(localized))
    api=(base/f'API.{lang}.md').read_text(encoding='utf-8')
    pairs=re.findall(r'^\| \[(modx_[a-z0-9_]+)\]\(API.md#(modx_[a-z0-9_]+)\) \|',api,re.M)
    assert len(pairs)==192 and [a for a,b in pairs]==TOOLS and all(a==b for a,b in pairs),(lang,'localized API directory missing tools or schema links')

    settings=re.findall(r'^\| \u0060(modxmcp\.[a-z0-9_]+)\u0060 \|',files['SETTINGS'],re.M)
    assert settings==SETTINGS,(lang,'settings mismatch')
    # Readers need complete advice, not the short launch summaries.
    assert files['INSTALL'].count('\n## ')>=7,(lang,'install sections')
    assert files['SECURITY'].count('\n## ')>=6,(lang,'security sections')
    assert len(files['INSTALL'].splitlines())>=85,(lang,'install too brief')
    assert len(files['SECURITY'].splitlines())>=60,(lang,'security too brief')
    assert len(files['CAPABILITIES'].splitlines())>=250,(lang,'capabilities too brief')
    assert all('v1.2.4' in files[page] or '1.2.4' in files[page]
               for page in ('INSTALL','CAPABILITIES')),(lang,'version')
    for required in ('modxmcp.api_token','modxmcp.disabled_groups','modxmcp.require_https'):
        assert required in files['SECURITY'],(lang,'security key',required)
    for required in ('MODX_MCP_SITE_URL','MODX_MCP_TOKEN','X-MCP-Token'):
        assert required in files['INSTALL'],(lang,'install configuration',required)
    assert 'dry_run' in files['CAPABILITIES'],(lang,'dangerous actions safeguard')
    assert 'modx_update_element_property' in files['CAPABILITIES'],(lang,'CAS tool')
print('ESSENTIAL_TRANSLATION_COVERAGE_OK',len(LOCALES),'languages x 192 tools x 16 settings')
