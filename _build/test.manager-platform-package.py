#!/usr/bin/env python3
"""Verify that both platform-staged transport inputs contain functional manager UI."""
from pathlib import Path
import tempfile,subprocess,sys
R=Path(__file__).resolve().parents[1]
with tempfile.TemporaryDirectory() as d:
    for plat in ('modx2','modx3'):
        dest=Path(d)/plat
        subprocess.run([sys.executable,str(R/'_build/prepare-release.py'),'--platform',plat,'--output',str(dest)],check=True,stdout=subprocess.DEVNULL)
        core=dest/'core/components/modxmcp'; assets=dest/'assets/components/modxmcp'
        for rel in ['templates/home.tpl','templates/graph.tpl','controllers/index.class.php','controllers/graph.class.php','processors/mgr/gettoken3.class.php','processors/mgr/savesettings3.class.php','processors/mgr/savegroups.class.php']:
            assert (core/rel).is_file(), (plat,rel)
        for rel in ['css/dashboard.css','css/graph.css','js/home.js','js/graph.js']:
            assert (assets/rel).is_file(), (plat,rel)
        for lang in ('ru', 'en', 'es'):
            lex=(core/'lexicon'/lang/'default.inc.php').read_text()
            assert "'modxmcp_graph_heading'" in lex, (plat, lang, 'graph headings')
            assert "'modxmcp_cmp_cap_access'" in lex, (plat, lang, 'capability labels')
        dashboard=(core/'templates/home.tpl').read_text();graph=(core/'templates/graph.tpl').read_text()
        assert '{$g.heading}' in graph and '{$g.canvas_help}' in graph
        assert (assets/'js/graph.js').read_text().find('TXT.loading_graph') >= 0
        assert 'token_full' not in dashboard and 'mcp-layout' in dashboard
        assert 'g-page-header' in graph and 'g-stage' in graph
        for rel in ['controllers/index.class.php','controllers/graph.class.php']:
            content=(core/rel).read_text()
            assert 'css/dashboard.css?v=' in content if 'index.' in rel else 'css/graph.css?v=' in content
            assert (r'use MODX\Revolution\modExtraManagerController;' in content) == (plat=='modx3')
        for rel in ['processors/mgr/gettoken3.class.php','processors/mgr/savesettings3.class.php']:
            content=(core/rel).read_text()
            assert "hasPermission('settings')" in content and "isAuthenticated('mgr')" in content
            assert ('extends modProcessor' in content) == (plat=='modx2')
            assert ('extends Processor' in content) == (plat=='modx3')
        print('MANAGER_PLATFORM_STAGE_OK',plat,'dashboard, graph, 2 processors, CSS/JS')
