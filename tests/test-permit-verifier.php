<?php
/* Isolated authorized-write envelope negative tests; no live site. */
require_once __DIR__ . '/../core/components/modxmcp/endpoint/permit-verifier.php';
$home = getenv('HOME');
if (!$home || !is_dir($home)) { throw new RuntimeException('private HOME required'); }
$dir = $home . '/.modxmcp-verifier-' . bin2hex(random_bytes(8));
mkdir($dir, 0700);
try {
    $ledger = new ModxMcpPermitNonceLedger($dir);
    $raw = '{"action":"update_element","type":"chunk","data":{"id":7,"content":"new"}}';
    $trusted = array('audience' => 'modx-site-one', 'site_id' => 'site-one', 'contour' => 'test');
    $claims = array(
        'version'=>'1', 'audience'=>'modx-site-one', 'site_id'=>'site-one',
        'contour'=>'test', 'task_id'=>'TASK-20261103-100003-abcd',
        'plan_digest'=>str_repeat('a',64), 'operation_id'=>'op-1',
        'action'=>'update_element', 'target_digest'=>str_repeat('b',64),
        'parameters_digest'=>str_repeat('c',64),
        'request_digest'=>hash('sha256',$raw),
        'nonce'=>bin2hex(random_bytes(16)), 'expires_at'=>(string)(time()+40)
    );
    $key=str_repeat('K',32);
    $permit=array('claims'=>$claims,'mac'=>ModxMcpPermitWire::sign($claims,$key));
    function denied($fn, $label) {
        try { $fn(); } catch (RuntimeException $e) { return; }
        throw new RuntimeException('unauthorized request accepted: '.$label);
    }
    denied(function () use ($permit,$raw,$trusted,$key,$ledger) {
        ModxMcpPermitVerifier::authorize($permit,$raw,'delete_element',$trusted,$key,$ledger);
    }, 'changed action');
    denied(function () use ($permit,$raw,$trusted,$key,$ledger) {
        ModxMcpPermitVerifier::authorize($permit,$raw.' ' ,'update_element',$trusted,$key,$ledger);
    }, 'changed raw body');
    denied(function () use ($permit,$raw,$trusted,$key,$ledger) {
        ModxMcpPermitVerifier::authorize($permit,$raw,'update_element',
            array('audience'=>'other','site_id'=>'site-one','contour'=>'test'),$key,$ledger);
    }, 'wrong audience');
    denied(function () use ($permit,$raw,$trusted,$key,$ledger) {
        ModxMcpPermitVerifier::authorize($permit,$raw,'update_element',
            array('audience'=>'modx-site-one','site_id'=>'other','contour'=>'test'),$key,$ledger);
    }, 'wrong site identity');
    denied(function () use ($permit,$raw,$trusted,$key,$ledger) {
        ModxMcpPermitVerifier::authorize($permit,$raw,'update_element',
            array('audience'=>'modx-site-one','site_id'=>'site-one','contour'=>'production'),$key,$ledger);
    }, 'wrong contour');
    $tampered = $permit;
    $tampered['mac'] = str_repeat('0',64);
    denied(function () use ($tampered,$raw,$trusted,$key,$ledger) {
        ModxMcpPermitVerifier::authorize($tampered,$raw,'update_element',$trusted,$key,$ledger);
    }, 'tampered signature');
    $invalid = $permit;
    $invalid['claims']['request_digest'] = str_repeat('a',64);
    denied(function () use ($invalid,$raw,$trusted,$key,$ledger) {
        ModxMcpPermitVerifier::authorize($invalid,$raw,'update_element',$trusted,$key,$ledger);
    }, 'tampered claim with stale signature');
    denied(function () use ($permit,$raw,$trusted,$key,$ledger) {
        ModxMcpPermitVerifier::authorize($permit,$raw,'update_element',$trusted,str_repeat('Z',32),$ledger);
    }, 'wrong key');
    denied(function () use ($permit,$raw,$trusted,$key,$ledger) {
        ModxMcpPermitVerifier::authorize($permit,$raw,'update_element',$trusted,$key,$ledger,time()+50);
    }, 'expired permit');
    $ok=ModxMcpPermitVerifier::authorize($permit,$raw,'update_element',$trusted,$key,$ledger);
    if (!($ok instanceof ModxMcpPermitVerifier) || !$ok->allowsAction('update_element') ||
        $ok->allowsAction('delete_element')) {
        throw new RuntimeException('authorization decision not bound to action');
    }
    denied(function () use ($permit,$raw,$trusted,$key,$ledger) {
        ModxMcpPermitVerifier::authorize($permit,$raw,'update_element',$trusted,$key,$ledger);
    }, 'replayed permit');
} finally {
    foreach (glob($dir.'/*.used') as $file) { unlink($file); }
    rmdir($dir);
}
echo "MODX_PERMIT_VERIFIER_OK\n";
