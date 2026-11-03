<?php
/* Offline smoke test of the actual protected-HTTP adapter; no MODX instance. */
require_once __DIR__ . '/../core/components/modxmcp/endpoint/protected-api.php';
$home = getenv('HOME');
if (!is_string($home) || $home === '' || !is_dir($home)) {
    throw new RuntimeException('private HOME required');
}
$dir = $home . '/.modxmcp-http-permit-' . bin2hex(random_bytes(8));
mkdir($dir, 0700);
mkdir($dir . '/used', 0700);
$key = str_repeat('K', 32);
file_put_contents($dir . '/signing-key', bin2hex($key));
chmod($dir . '/signing-key', 0600);
class MockModxPermits {
    private $values;
    public function __construct($values) { $this->values = $values; }
    public function getOption($key, $options = null, $default = null) {
        return array_key_exists($key, $this->values) ? $this->values[$key] : $default;
    }
}
$cfg = array(
    'modxmcp.permit_audience' => 'modx-site-one',
    'modxmcp.permit_site_id' => 'site-one',
    'modxmcp.permit_contour' => 'test',
    'modxmcp.permit_key_file' => $dir . '/signing-key',
    'modxmcp.permit_nonce_directory' => $dir . '/used',
);
$modx = new MockModxPermits($cfg);
$raw = '{"action":"update_element","type":"chunk","data":{"id":7,"content":"new"}}';
$claims = array(
    'version'=>'1', 'audience'=>'modx-site-one', 'site_id'=>'site-one',
    'contour'=>'test', 'task_id'=>'TASK-20261103-100003-abcd',
    'plan_digest'=>str_repeat('a',64), 'operation_id'=>'op-1',
    'action'=>'update_element', 'target_digest'=>str_repeat('b',64),
    'parameters_digest'=>str_repeat('c',64),
    'request_digest'=>hash('sha256',$raw),
    'nonce'=>bin2hex(random_bytes(16)), 'expires_at'=>(string)(time()+40),
);
$permit=array('claims'=>$claims,'mac'=>ModxMcpPermitWire::sign($claims,$key));
$_SERVER['DOCUMENT_ROOT']='/var/www/public';
$_SERVER['HTTP_X_SITEAGENT_PERMIT']=base64_encode(json_encode($permit));
function mustDeny($fn, $case) {
    try { $fn(); } catch (RuntimeException $e) { return; }
    throw new RuntimeException('failed to deny: ' . $case);
}
try {
    mustDeny(function() use ($modx,$raw) {
        modxmcpVerifyProtectedHttpRequest($modx,$raw.' ','update_element');
    },'tampered HTTP body');
    mustDeny(function() use ($modx,$raw) {
        modxmcpVerifyProtectedHttpRequest($modx,$raw,'delete_element');
    },'other action');
    $decision=modxmcpVerifyProtectedHttpRequest($modx,$raw,'update_element');
    if (!($decision instanceof ModxMcpPermitVerifier) ||
        !$decision->allowsAction('update_element')) {
        throw new RuntimeException('valid signed request refused');
    }
    mustDeny(function() use ($modx,$raw) {
        modxmcpVerifyProtectedHttpRequest($modx,$raw,'update_element');
    },'replayed request');
    unset($_SERVER['HTTP_X_SITEAGENT_PERMIT']);
    mustDeny(function() use ($modx,$raw) {
        modxmcpVerifyProtectedHttpRequest($modx,$raw,'update_element');
    },'missing signature header');
    chmod($dir . '/signing-key', 0644);
    $_SERVER['HTTP_X_SITEAGENT_PERMIT'] = base64_encode(json_encode($permit));
    mustDeny(function() use ($modx,$raw) {
        modxmcpVerifyProtectedHttpRequest($modx,$raw,'update_element');
    },'unprotected signing key');
    chmod($dir . '/signing-key', 0600);
} finally {
    foreach (glob($dir.'/used/*.used') as $path) { unlink($path); }
    unlink($dir.'/signing-key');
    rmdir($dir.'/used');
    rmdir($dir);
}
echo "MODX_PROTECTED_HTTP_OK\n";
