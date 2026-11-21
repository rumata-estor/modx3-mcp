#!/usr/bin/env python3
# MODX MCP SOURCE / ИСХОДНИК MODX MCP
# RU: Проверка «manager-graph-dashboard»; тестовый код не запускается на production.
# EN: «manager-graph-dashboard» regression test; not executed in production.
"""Guard contract and responsive manager graph shell without touching MODX data."""
from pathlib import Path
import re
root=Path(__file__).resolve().parents[1]
ui=(root/'core/components/modxmcp/templates/graph.tpl').read_text()
css=(root/'assets/components/modxmcp/css/graph.css').read_text()
js=(root/'assets/components/modxmcp/js/graph.js').read_text()
ctrl=(root/'core/components/modxmcp/controllers/graph.class.php').read_text()
assert 'id="mcpg" tabindex="0"' in ui
assert 'g-page-header' in ui and 'g-back' in ui and '{$settings_url}' in ui
assert 'g-body' in ui and '<aside class="g-side"' in ui
assert 'g-stage' in ui and 'g-info' in ui
assert 'css/graph.css?v=' in ctrl and 'js/graph.js?v=' in ctrl
assert 'overflow-y:scroll' in css and 'scrollbar-gutter:stable' in css
assert '@media(max-width:1120px)' in css and '@media(max-width:670px)' in css
assert 'function fitShell()' in js and 'getComputedStyle(host)' in js
for id in ['mcpg','g-q','g-res','g-reload','g-fit','g-reset','g-stat','g-types',
           'g-hide-vendor','g-hide-orphans','g-only-issues','g-res-nodes','g-spread',
           'g-nmiss','g-miss','g-norph','g-orph','g-orph-note','g-canvas',
           'g-crumb','g-overlay','g-legend','g-info']:
    assert 'id="'+id+'"' in ui, id
assert ui.count('data-lay="')==3
assert ui.count('<div') >= ui.count('</div>')-1
print('MODXMCP_GRAPH_DASHBOARD_CONTRACT_OK')
