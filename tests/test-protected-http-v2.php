<?php
/** Offline test of the test-only signed v2 HTTP bridge, no CMS or network. */
require_once __DIR__.'/../core/components/modxmcp/endpoint/protected-api.php';
require_once __DIR__.'/../core/components/modxmcp/endpoint/permit-verifier-v2.php';
$home=getenv('HOME');
if (!is_string($home) || $home==='') {
    throw new RuntimeException('private HOME required');
}
$dir=$home.'/.modxmcp-http-v2-'.bin2hex(random_bytes(8));
mkdir($dir,0700);
mkdir($dir.'/used',0700);
$key=str_repeat('K',32);
file_put_contents($dir.'/signing-key',bin2hex($key));
chmod($dir.'/signing-key',0600);
$_SERVER['DOCUMENT_ROOT']='/var/www/public';
$site='modx3-alex-palochkin';
$task='TASK-20261104-140000-abcd';
$before='before';
$after='after';
$raw='{"action":"update_element","type":"chunk","data":{"id":7,"content":"after"}}';
$baseline=array('element_type'=>'chunk','id'=>7,'storage_field'=>'snippet',
                'static'=>false,'before_content'=>$before,
                'before_sha256'=>hash('sha256',$before),
                'after_sha256'=>hash('sha256',$after));
$claims=array('version'=>'2','audience'=>'test-v2','site_id'=>$site,
    'contour'=>'test','task_id'=>$task,'plan_digest'=>str_repeat('a',64),
    'snapshot_digest'=>ModxMcpCasPermitVerifierV2::observationDigest($site,$task,$baseline),
    'operation_id'=>'op-1','action'=>'update_element','element_type'=>'chunk',
    'chunk_id'=>'7','before_sha256'=>hash('sha256',$before),
    'after_sha256'=>hash('sha256',$after),'request_digest'=>hash('sha256',$raw),
    'nonce'=>bin2hex(random_bytes(16)),'expires_at'=>(string)(time()+40));
$permit=array('claims'=>$claims,'baseline'=>$baseline,
              'mac'=>ModxMcpCasPermitWireV2::sign($claims,$key));
class MockModxV2Config {
    private $settings;
    public function __construct($settings) { $this->settings=$settings; }
    public function getOption($key,$options=null,$default=null) {
        return isset($this->settings[$key]) ? $this->settings[$key] : $default;
    }
}
class MockMcpV2Writer {
    public $calls=0;
    public function processProtectedChunkCasV2($permit,$raw,$trusted,$key,$ledger) {
        ++$this->calls;
        return ModxMcpCasPermitVerifierV2::authorize($permit,$raw,$trusted,$key,$ledger);
    }
}
$settings=array(
    'modxmcp.external_authorization_required'=>'1',
    'modxmcp.permit_audience'=>'test-v2',
    'modxmcp.permit_site_id'=>$site,
    'modxmcp.permit_contour'=>'test',
    'modxmcp.permit_key_file'=>$dir.'/signing-key',
    'modxmcp.permit_nonce_directory'=>$dir.'/used',
);
$modx=new MockModxV2Config($settings);
$writer=new MockMcpV2Writer();
function failClosedV2($call,$message) {
    try { $call(); }
    catch (Exception $e) { return; }
    throw new RuntimeException('v2 protected HTTP admitted '.$message);
}
try {
    $_SERVER['HTTP_X_SITEAGENT_PERMIT']=base64_encode(json_encode($permit));
    $disabledSite=new MockModxV2Config(array_merge($settings,array(
        'modxmcp.external_authorization_required'=>'0',
    )));
    failClosedV2(function() use($disabledSite,$writer,$raw) {
        modxmcpExecuteSignedChunkCasV2($disabledSite,$writer,$raw);
    },'request without active external authorization');
    if ($writer->calls!==0 || count(glob($dir.'/used/*.used'))!==0) {
        throw new RuntimeException('disabled v2 HTTP bridge reached write-point');
    }

    $wrongVersion=$permit;
    $wrongVersion['claims']['version']='1';
    $_SERVER['HTTP_X_SITEAGENT_PERMIT']=base64_encode(json_encode($wrongVersion));
    failClosedV2(function() use($modx,$writer,$raw) {
        modxmcpExecuteSignedChunkCasV2($modx,$writer,$raw);
    },'v1 downgrade');

    $wrongSite=new MockModxV2Config(array_merge($settings,array(
        'modxmcp.permit_site_id'=>'not-allowed',
    )));
    $_SERVER['HTTP_X_SITEAGENT_PERMIT']=base64_encode(json_encode($permit));
    failClosedV2(function() use($wrongSite,$writer,$raw) {
        modxmcpExecuteSignedChunkCasV2($wrongSite,$writer,$raw);
    },'unapproved site');

    failClosedV2(function() use($modx,$writer,$raw) {
        modxmcpExecuteSignedChunkCasV2($modx,$writer,$raw.' ');
    },'changed HTTP body');
    if (count(glob($dir.'/used/*.used'))!==0) {
        throw new RuntimeException('rejected envelope consumed a nonce');
    }

    $result=modxmcpExecuteSignedChunkCasV2($modx,$writer,$raw);
    if ($result!==array('chunk_id'=>7,'before_content'=>'before',
                         'after_content'=>'after')) {
        throw new RuntimeException('v2 HTTP bridge changed signed scope');
    }
    failClosedV2(function() use($modx,$writer,$raw) {
        modxmcpExecuteSignedChunkCasV2($modx,$writer,$raw);
    },'replayed permit');
    echo "CAS_V2_HTTP_BRIDGE_TEST_ONLY_OK\n";
} finally {
    unset($_SERVER['HTTP_X_SITEAGENT_PERMIT']);
    foreach (glob($dir.'/used/*.used') as $path) { unlink($path); }
    unlink($dir.'/signing-key');
    rmdir($dir.'/used');
    rmdir($dir);
}
