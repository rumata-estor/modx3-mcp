#!/usr/bin/env python3
"""Static safety guard: protected HTTP CAS v2 requires explicit test interlocks."""
from pathlib import Path
root=Path(__file__).resolve().parents[1]
api=root/'core/components/modxmcp/endpoint/api.common.php'
model=root/'core/components/modxmcp/model/modxmcp.class.php'
legacy=root/'core/components/modxmcp/legacy/modx2/modxmcp.class.php'
for source in (api,model,legacy):
    body=source.read_text(encoding='utf-8')
    assert 'chunk-cas-pilot' not in body, f'unsafe CAS pilot wired into {source}'
    assert 'ModxMcpChunkCasPilot' not in body, f'unsafe CAS pilot dispatch: {source}'
    if source in (model,legacy):
        assert 'assertExternalAtomicPreconditionReady()' in body, f'CAS safety interlock absent: {source}'
for source in (model,legacy):
    body=source.read_text(encoding='utf-8')
    start=body.index('public static function assertExternalAtomicPreconditionReady()')
    # A new reviewed CAS processor precedes the unchanged v2 processor.
    # Check ONLY the unconditional old static guard, not its new neighbor.
    reviewed=body.index('public function processProtectedReviewedCasV1(',start)
    end=body.index('public function processProtectedChunkCasV2(',reviewed)
    gate=body[start:reviewed]
    assert 'throw new ModxMCPClientException' in gate, f'CAS write interlock softened: {source}'
    assert 'if (' not in gate and 'getOption(' not in gate, f'conditional CAS interlock: {source}'
    bridge=body[end:body.index('public function processRequest(',end)]
    assert 'processProtectedReviewedCasV1(' in body[reviewed:end]
    assert "getenv('MODXMCP_CAS_V2_TEST_ENABLE')" not in bridge
    assert 'isExternalAuthorizationRequired($this->modx)' in bridge
    assert 'resolveServiceUser()' in bridge
    assert 'ModxMcpChunkCasProcessorV2::executeUnconnected' in bridge
# Explicit project rule: no new MODX audit or nonce tables.
assert not (root/'core/components/modxmcp/endpoint/permit-nonce-ledger-db.php').exists()
assert not (root/'tests/ci-nonce-ledger-db.php').exists()
processor=root/'core/components/modxmcp/endpoint/chunk-cas-processor-v2.php'
for source in (processor,root/'tests/ci-protected-cas-processor-v2.php',
               root/'tests/live-chunk-cas-innodb.php'):
    body=source.read_text(encoding='utf-8')
    assert 'CREATE TABLE' not in body and 'DROP TABLE' not in body
    assert 'ALTER TABLE' not in body and 'auditTable' not in body
    assert 'ModxMcpPermitNonceLedgerDb' not in body
assert 'ModxMcpPermitNonceLedger $ledger' in processor.read_text(encoding='utf-8')
apiBody=api.read_text(encoding='utf-8')
assert "getenv('MODXMCP_CAS_V2_TEST_ENABLE')" not in apiBody
assert any(call in apiBody for call in (
    'modxMCP::assertExternalAuthorizedWriteShape($action, $type, $data)',
    'modxMCP::assertExternalAuthorizedWriteShape($action,$type,$data)'))
assert 'modxmcpExecuteSignedChunkCasV2($modx,$mcp,$rawInput)' in apiBody
assert 'modxMCP::assertExternalAtomicPreconditionReady();' not in apiBody
assert 'if (modxMCP::isExternalAuthorizationRequired($modx))' in apiBody
assert 'ModxMcpChunkCasProcessorV2' not in apiBody
http=(root/'core/components/modxmcp/endpoint/protected-api.php').read_text(encoding='utf-8')
assert "getenv('MODXMCP_CAS_V2_TEST_ENABLE')" not in http
assert "'modxmcp.external_authorization_required', null, '0'" in http
assert "'modxmcp.permit_site_id'" in http
assert "'modxmcp.permit_contour'" in http
assert "$trusted['contour'] !== 'test'" in http
assert 'modxmcpProtectedRuntimeConfig($modx)' in http
print('CAS_V2_HTTP_TEST_GATE_FAIL_CLOSED_OK')
