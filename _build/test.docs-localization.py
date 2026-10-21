#!/usr/bin/env python3
"""Check nine-language MODX MCP documentation without network dependencies."""
from pathlib import Path
from urllib.parse import unquote
import re

ROOT=Path(__file__).resolve().parent.parent
LANGS=('en','ru','es','de','fr','uk','kk','nl','pl')
NEW_LANGS=('es','de','fr','uk','kk','nl','pl')
PAGES=('INSTALL','SECURITY','SETTINGS','CAPABILITIES','API','BUILD-ARCHITECTURE','VALIDATION')
RELEASES=('modx2mcp-1.2.4','modx3mcp-1.2.4')
SETTINGS=('modxmcp.enabled','modxmcp.api_token','modxmcp.service_user_id',
'modxmcp.require_https','modxmcp.trust_proxy_https','modxmcp.allowed_ips',
'modxmcp.disabled_groups','modxmcp.allow_run_processor','modxmcp.allow_root_filesystem_read',
'modxmcp.component_code_roots','modxmcp.max_payload_bytes','modxmcp.max_read_bytes',
'modxmcp.auto_static','modxmcp.audit_log','modxmcp.debug','modxmcp.core_path')
LINK=re.compile(r'(?<!!)\[[^\]\n]+\]\(([^)\n]+)\)')
checked=set()

def filename(name,lang):
    return name+('' if lang=='en' else '.'+lang)+'.md'

def validate(path):
    assert path.is_file(),f'Missing translated file: {path}'
    src=path.read_text(encoding='utf-8')
    assert any(line.startswith('# ') for line in src.splitlines()[:12]),f'Heading missing: {path}'
    assert src.count('&#96;')==0,f'Broken Markdown escapes: {path}'
    assert src.count(chr(96)*3)%2==0,f'Unclosed fenced block: {path}'
    for dest in LINK.findall(src):
        url=dest.split(' ',1)[0].strip('<>')
        if url.startswith(('https://','http://','mailto:','tel:','#')):
            continue
        local=unquote(url.split('#',1)[0].split('?',1)[0])
        if not local:continue
        target=(path.parent/local).resolve()
        assert target.is_relative_to(ROOT),f'Link escapes repository: {path}:{url}'
        assert target.exists(),f'Broken link: {path.relative_to(ROOT)} -> {url}'
    checked.add(path)

for lang in LANGS:
    readme=ROOT/filename('README',lang)
    validate(readme)
    for page in PAGES:
        validate(ROOT/'docs'/filename(page,lang))
    for release in RELEASES:
        validate(ROOT/'docs'/'extras'/filename(release,lang))
for lang in NEW_LANGS:
    readme=(ROOT/filename('README',lang)).read_text()
    assert 'v1.2.4' in readme and 'MODX_MCP_TOKEN' in readme,lang
    assert 'docs/LANGUAGES.md' not in readme or (ROOT/'docs/LANGUAGES.md').exists()
    settings=(ROOT/'docs'/filename('SETTINGS',lang)).read_text()
    assert all(chr(96)+key+chr(96) in settings for key in SETTINGS),lang
    assert 'v1.2.3' not in readme,lang
hub=ROOT/'docs/LANGUAGES.md'
validate(hub)
text=hub.read_text()
assert text.count('README.')>=8
assert 'not yet fully translated' in text
rows=[line for line in text.splitlines() if line.startswith('|')]
assert len(rows)==11, ('hub table rows',len(rows))
assert all(len(line.split('|'))==10 for line in rows), 'hub table columns'
print('DOCS_NINE_LANGUAGES_OK',len(checked),'files; 7 sections + 2 platform release notes per language')
