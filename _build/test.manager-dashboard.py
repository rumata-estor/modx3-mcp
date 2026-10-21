#!/usr/bin/env python3
"""Static regression checks for the MODX manager dashboard (no credentials)."""
from pathlib import Path
import re
root=Path(__file__).resolve().parents[1]
tpl=(root/'core/components/modxmcp/templates/home.tpl').read_text()
css=(root/'assets/components/modxmcp/css/dashboard.css').read_text()
js=(root/'assets/components/modxmcp/js/home.js').read_text()
controller=(root/'core/components/modxmcp/controllers/index.class.php').read_text()
gettoken=(root/'core/components/modxmcp/processors/mgr/gettoken3.class.php').read_text()
settings=(root/'core/components/modxmcp/processors/mgr/savesettings3.class.php').read_text()
assert 'token_full' not in tpl, 'Never inject unmasked API token into HTML'
assert 'token_preview' in tpl
assert 'mcp-layout' in tpl and 'grid-template-columns:minmax(0,1fr) minmax(0,1fr)' in css
assert '@media(max-width:1030px)' in css and '@media(max-width:630px)' in css
assert 'overflow-y:scroll' in css and 'scrollbar-gutter:stable' in css
assert 'fitDashboardScroll' in js and "window.addEventListener('resize', fitDashboardScroll)" in js
assert 'tabindex="0"' in tpl
for key in ['mcp-show-token','mcp-copy-token','mcp-copy-endpoint','mcp-save-settings','modxmcp-savegroups','modxmcp-regenerate']:
    assert f'id="{key}"' in tpl and key in js, key
assert "request('gettoken3'" in js
assert "'savesettings3'" in js
assert "'savegroups'" in js
assert "request('regeneratetoken3'" in js
assert 'data-preview' in tpl and 'setTimeout(hideToken, 60000)' in js
assert "hasPermission('settings')" in gettoken and "isAuthenticated('mgr')" in gettoken
assert 'Cache-Control: no-store, private' in gettoken
assert 'token' in gettoken and 'token' not in settings
assert "hasPermission('settings')" in settings and "isAuthenticated('mgr')" in settings
assert "array('auto_static', 'audit_log')" in settings and "'modxmcp.' . $name" in settings
assert "'css/dashboard.css?v='" in controller and "'js/home.js?v='" in controller
labels={m.group(1) for m in re.finditer(r'\$l\.([a-z][a-z0-9_]*)',tpl)}
for lang in ['ru','en','es','de','fr','uk']:
    lex=(root/f'core/components/modxmcp/lexicon/{lang}/default.inc.php').read_text()
    for key in labels:
        assert f"'modxmcp_cmp_{key}'" in lex, (lang,key)
    for key in labels:
        assert f"'{key}'" in controller, key
print('MODXMCP_MANAGER_DASHBOARD_CONTRACT_OK',len(labels),'labels, responsive UI, ACL checks, no token in HTML')
