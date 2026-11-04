<?php
/* Cross-language fixed-vector v2 CAS wire test; no MODX or real credentials. */
require_once __DIR__ . '/../core/components/modxmcp/endpoint/permit-wire-v2.php';
$claims=array(
    'version'=>'2','audience'=>'modx-test-two','site_id'=>'modx2-alex-palochkin',
    'contour'=>'test','task_id'=>'TASK-20261104-140000-abcd',
    'plan_digest'=>str_repeat('a',64),'snapshot_digest'=>str_repeat('b',64),
    'operation_id'=>'op-1','action'=>'update_element','element_type'=>'chunk',
    'chunk_id'=>'7','before_sha256'=>str_repeat('c',64),
    'after_sha256'=>str_repeat('d',64),'request_digest'=>str_repeat('e',64),
    'nonce'=>str_repeat('f',32),'expires_at'=>'1794000000',
);
$key=str_repeat('K',32);
$expected='4280c07835f7a91c6f40c626195cc384f024b9a763f665d992eb14ea695c4622';
if (ModxMcpCasPermitWireV2::sign($claims,$key)!==$expected ||
    !ModxMcpCasPermitWireV2::verify($claims,$expected,$key) ||
    ModxMcpCasPermitWireV2::verify($claims,$expected,str_repeat('Z',32))) {
    throw new RuntimeException('Python/PHP v2 CAS wire fingerprint mismatch');
}
function mustDenyCasWire($fn) {
    try { $fn(); } catch (InvalidArgumentException $e) { return; }
    throw new RuntimeException('unclassified v2 CAS claim admitted');
}
foreach (array(
    array('version','1'),array('action','delete_element'),
    array('element_type','snippet'),array('chunk_id','0'),
    array('chunk_id','07'),array('chunk_id','2147483648'),
    array('before_sha256',str_repeat('X',64)),
    array('nonce',str_repeat('A',32)),array('expires_at','0')
) as $case) {
    $bad=$claims;
    $bad[$case[0]]=$case[1];
    mustDenyCasWire(function() use ($bad) { ModxMcpCasPermitWireV2::encode($bad); });
}
$extra=$claims;
$extra['untrusted']='value';
mustDenyCasWire(function() use ($extra) { ModxMcpCasPermitWireV2::encode($extra); });
echo "MODX_CAS_PERMIT_WIRE_V2_OK\n";
