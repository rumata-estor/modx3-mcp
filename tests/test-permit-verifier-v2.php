<?php
/* No live MODX, HTTP or site credentials: offline v2 CAS verifier tests. */
require_once __DIR__ . '/../core/components/modxmcp/endpoint/permit-verifier-v2.php';
$home=getenv('HOME');
if (!is_string($home) || !is_dir($home)) { throw new RuntimeException('private HOME required'); }
$directory=$home.'/.modxmcp-cas-permit-test-'.bin2hex(random_bytes(8));
if (!mkdir($directory,0700)) { throw new RuntimeException('private nonce dir failed'); }
function mustRejectCasVerify($fn,$label) {
    try { $fn(); } catch (Exception $e) { return; }
    throw new RuntimeException('CAS verifier accepted: '.$label);
}
try {
    $before="OLD α\n";
    $after='NEW β';
    $raw='{"action":"update_element","type":"chunk","data":{"id":7,"content":"NEW β"}}';
    $baseline=array('element_type'=>'chunk','id'=>7,'storage_field'=>'snippet',
                    'static'=>false,'before_content'=>$before,
                    'before_sha256'=>hash('sha256',$before),
                    'after_sha256'=>hash('sha256',$after));
    $site='modx2-alex-palochkin';$task='TASK-20261104-140000-abcd';
    $snapshot='cb91c5bfb77c43f8924002b1b0d5dd721abcfda9bd82cd27624d65ef4a9695fc';
    if (ModxMcpCasPermitVerifierV2::observationDigest($site,$task,$baseline)!==$snapshot) {
        throw new RuntimeException('PHP/Python observation snapshot canonicalization drift');
    }
    $lineSeparators=$baseline;
    $lineSeparators['before_content']="line\u{2028}separator\u{2029}body";
    $lineSeparators['before_sha256']=hash('sha256',$lineSeparators['before_content']);
    if (ModxMcpCasPermitVerifierV2::observationDigest($site,$task,$lineSeparators)
        !== '6e33b67128e54c2d74baf7a85639908df0a555ca879be0e359bb49ea21a0ad02') {
        throw new RuntimeException('PHP/Python U+2028/U+2029 canonicalization drift');
    }
    $claims=array('version'=>'2','audience'=>'modx-test-two','site_id'=>$site,
        'contour'=>'test','task_id'=>$task,'plan_digest'=>str_repeat('a',64),
        'snapshot_digest'=>$snapshot,'operation_id'=>'op-1',
        'action'=>'update_element','element_type'=>'chunk','chunk_id'=>'7',
        'before_sha256'=>$baseline['before_sha256'],
        'after_sha256'=>$baseline['after_sha256'],
        'request_digest'=>hash('sha256',$raw),'nonce'=>str_repeat('f',32),
        'expires_at'=>'1794000000');
    $key=str_repeat('K',32);
    $mac=ModxMcpCasPermitWireV2::sign($claims,$key);
    if ($mac!=='c05c0970dd4ddc4af540a3de3b5cbb05f38c85ace0cd630b787160fa395ddd8e') {
        throw new RuntimeException('PHP/Python full CAS envelope signature drift');
    }
    $permit=array('claims'=>$claims,'mac'=>$mac,'baseline'=>$baseline);
    $trusted=array('audience'=>'modx-test-two','site_id'=>$site,'contour'=>'test');
    $ledger=new ModxMcpPermitNonceLedger($directory);
    $clock=1793999960;
    mustRejectCasVerify(function() use ($permit,$raw,$trusted,$key,$ledger,$clock) {
        ModxMcpCasPermitVerifierV2::authorize($permit,$raw.' ', $trusted,$key,$ledger,$clock);
    },'altered exact HTTP bytes');
    mustRejectCasVerify(function() use ($permit,$raw,$trusted,$key,$ledger,$clock) {
        ModxMcpCasPermitVerifierV2::authorize($permit,$raw,
            array('audience'=>'other','site_id'=>$trusted['site_id'],'contour'=>'test'),
            $key,$ledger,$clock);
    },'different audience');
    mustRejectCasVerify(function() use ($permit,$raw,$trusted,$key,$ledger,$clock) {
        ModxMcpCasPermitVerifierV2::authorize($permit,$raw,$trusted,$key,$ledger,$clock+61);
    },'expired CAS permit');
    $tampered=$permit;
    $tampered['baseline']['before_content']='FORGED';
    mustRejectCasVerify(function() use ($tampered,$raw,$trusted,$key,$ledger,$clock) {
        ModxMcpCasPermitVerifierV2::authorize($tampered,$raw,$trusted,$key,$ledger,$clock);
    },'untrusted original bytes');
    $tampered=$permit;
    $tampered['baseline']['static']=true;
    mustRejectCasVerify(function() use ($tampered,$raw,$trusted,$key,$ledger,$clock) {
        ModxMcpCasPermitVerifierV2::authorize($tampered,$raw,$trusted,$key,$ledger,$clock);
    },'file-backed chunk');
    $tampered=$permit;
    $tampered['claims']['chunk_id']='8';
    mustRejectCasVerify(function() use ($tampered,$raw,$trusted,$key,$ledger,$clock) {
        ModxMcpCasPermitVerifierV2::authorize($tampered,$raw,$trusted,$key,$ledger,$clock);
    },'different signed ID');
    // A signed raw request digest does NOT make ambiguous JSON acceptable.
    foreach (array(
        '{"action":"update_element","action":"update_element","type":"chunk","data":{"id":7,"content":"NEW β"}}',
        '{"action":"update_element","type":"chunk","data":{"id":7,"id":7,"content":"NEW β"}}',
        '{"action":"update_element","type":"chunk","data":{"id":7,"content":"OLD","content":"NEW β"}}',
        '{"type":"chunk","action":"update_element","data":{"id":7,"content":"NEW β"}}',
        $raw.' ',
    ) as $ambiguous) {
        $alternate=$permit;
        $alternate['claims']['request_digest']=hash('sha256',$ambiguous);
        $alternate['mac']=ModxMcpCasPermitWireV2::sign($alternate['claims'],$key);
        mustRejectCasVerify(function() use ($alternate,$ambiguous,$trusted,$key,$ledger,$clock) {
            ModxMcpCasPermitVerifierV2::authorize($alternate,$ambiguous,
                $trusted,$key,$ledger,$clock);
        },'signed but noncanonical JSON body');
    }
    $approved=ModxMcpCasPermitVerifierV2::authorize(
        $permit,$raw,$trusted,$key,$ledger,$clock);
    if ($approved!==array('chunk_id'=>7,'before_content'=>$before,'after_content'=>$after)) {
        throw new RuntimeException('CAS authorization did not return exact SQL input');
    }
    mustRejectCasVerify(function() use ($permit,$raw,$trusted,$key,$ledger,$clock) {
        ModxMcpCasPermitVerifierV2::authorize($permit,$raw,$trusted,$key,$ledger,$clock);
    },'replayed nonce');
} finally {
    foreach (glob($directory.'/*.used') as $file) { unlink($file); }
    rmdir($directory);
}
echo "MODX_CAS_PERMIT_VERIFIER_V2_OK\n";
