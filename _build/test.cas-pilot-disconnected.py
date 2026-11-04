#!/usr/bin/env python3
"""Static safety guard: experimental CAS must remain unreachable by endpoint."""
from pathlib import Path
root=Path(__file__).resolve().parents[1]
api=root/'core/components/modxmcp/endpoint/api.common.php'
model=root/'core/components/modxmcp/model/modxmcp.class.php'
legacy=root/'core/components/modxmcp/legacy/modx2/modxmcp.class.php'
for source in (api,model,legacy):
    body=source.read_text(encoding='utf-8')
    assert 'chunk-cas-pilot' not in body, f'unsafe CAS pilot wired into {source}'
    assert 'ModxMcpChunkCasPilot' not in body, f'unsafe CAS pilot dispatch: {source}'
    assert 'assertExternalAtomicPreconditionReady()' in body, f'CAS safety interlock absent: {source}'
for source in (model,legacy):
    body=source.read_text(encoding='utf-8')
    start=body.index('public static function assertExternalAtomicPreconditionReady()')
    end=body.index('public function processRequest(',start)
    gate=body[start:end]
    assert 'throw new ModxMCPClientException' in gate, f'CAS write interlock softened: {source}'
    assert 'if (' not in gate and 'getOption(' not in gate, f'conditional CAS interlock: {source}'
print('CAS_PILOT_ENDPOINT_DISCONNECTED_OK')
