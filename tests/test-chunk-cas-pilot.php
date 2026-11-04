<?php
/* Mock SQL CAS: signed v2 permit -> nonce -> exact conditional update. */
require_once __DIR__ . '/../core/components/modxmcp/endpoint/chunk-cas-pilot.php';
$home=getenv('HOME');
if (!is_string($home) || !is_dir($home)) { throw new RuntimeException('private HOME required'); }
$dir=$home.'/.modxmcp-cas-pilot-'.bin2hex(random_bytes(8));
if (!mkdir($dir,0700)) { throw new RuntimeException('cannot create private nonce directory'); }
class MockPilotStatement {
    public $values;
    public $count;
    public function __construct($count) { $this->count=$count; }
    public function execute($values) { $this->values=$values; return true; }
    public function rowCount() { return $this->count; }
}
class MockPilotDatabase {
    public $sql;
    public $statement;
    public $prepares=0;
    public function __construct($count) { $this->statement=new MockPilotStatement($count); }
    public function prepare($sql) { ++$this->prepares; $this->sql=$sql; return $this->statement; }
}
function mustRejectPilot($fn,$label) {
    try { $fn(); } catch (Exception $e) { return; }
    throw new RuntimeException('unsafe offline CAS pilot accepted: '.$label);
}
try {
    $site='modx2-alex-palochkin';$task='TASK-20261104-140000-abcd';
    $before='ORIGINAL';$after='DESIRED';
    $raw='{"action":"update_element","type":"chunk","data":{"id":7,"content":"DESIRED"}}';
    $base=array('element_type'=>'chunk','id'=>7,'storage_field'=>'snippet',
        'static'=>false,'before_content'=>$before,
        'before_sha256'=>hash('sha256',$before),
        'after_sha256'=>hash('sha256',$after));
    $claims=array('version'=>'2','audience'=>'modx-test-two','site_id'=>$site,
        'contour'=>'test','task_id'=>$task,'plan_digest'=>str_repeat('a',64),
        'snapshot_digest'=>ModxMcpCasPermitVerifierV2::observationDigest($site,$task,$base),
        'operation_id'=>'op-1','action'=>'update_element','element_type'=>'chunk',
        'chunk_id'=>'7','before_sha256'=>$base['before_sha256'],
        'after_sha256'=>$base['after_sha256'],
        'request_digest'=>hash('sha256',$raw),'nonce'=>str_repeat('e',32),
        'expires_at'=>'1794000000');
    $key=str_repeat('K',32);
    $permit=array('claims'=>$claims,'baseline'=>$base,
                  'mac'=>ModxMcpCasPermitWireV2::sign($claims,$key));
    $trusted=array('site_id'=>$site,'audience'=>'modx-test-two','contour'=>'test');
    $clock=1793999960;
    $ledger=new ModxMcpPermitNonceLedger($dir);
    $database=new MockPilotDatabase(1);
    $invalid=$permit;
    $invalid['baseline']['before_content']='FORGED';
    mustRejectPilot(function() use ($invalid,$raw,$trusted,$key,$ledger,$database,$clock) {
        ModxMcpChunkCasPilot::executeUnconnected($invalid,$raw,$trusted,$key,
            $ledger,$database,'modx_site_htmlsnippets',$clock);
    },'forged original state');
    if ($database->prepares!==0) { throw new RuntimeException('SQL reached before authorization'); }
    if (!ModxMcpChunkCasPilot::executeUnconnected(
        $permit,$raw,$trusted,$key,$ledger,$database,'modx_site_htmlsnippets',$clock)) {
        throw new RuntimeException('valid synthetic CAS refused');
    }
    if ($database->prepares!==1 ||
        strpos($database->sql,'BINARY `snippet` = BINARY :before')===false ||
        $database->statement->values!==array(':after'=>$after,':id'=>7,':before'=>$before)) {
        throw new RuntimeException('CAS did not bind one conditional update');
    }
    mustRejectPilot(function() use ($permit,$raw,$trusted,$key,$ledger,$database,$clock) {
        ModxMcpChunkCasPilot::executeUnconnected($permit,$raw,$trusted,$key,
            $ledger,$database,'modx_site_htmlsnippets',$clock);
    },'replayed exact permission');
    if ($database->prepares!==1) { throw new RuntimeException('replay hit SQL'); }
    $failed=$permit;
    $failed['claims']['nonce']=str_repeat('d',32);
    $failed['mac']=ModxMcpCasPermitWireV2::sign($failed['claims'],$key);
    $conflict=new MockPilotDatabase(0);
    mustRejectPilot(function() use ($failed,$raw,$trusted,$key,$ledger,$conflict,$clock) {
        ModxMcpChunkCasPilot::executeUnconnected($failed,$raw,$trusted,$key,
            $ledger,$conflict,'modx_site_htmlsnippets',$clock);
    },'CAS stale original data');
    if ($conflict->prepares!==1) { throw new RuntimeException('CAS conflict not attempted'); }
    mustRejectPilot(function() use ($failed,$raw,$trusted,$key,$ledger,$conflict,$clock) {
        ModxMcpChunkCasPilot::executeUnconnected($failed,$raw,$trusted,$key,
            $ledger,$conflict,'modx_site_htmlsnippets',$clock);
    },'CAS conflict nonce replay');
    if ($conflict->prepares!==1) { throw new RuntimeException('CAS conflict was retried'); }
} finally {
    foreach (glob($dir.'/*.used') as $file) { unlink($file); }
    rmdir($dir);
}
echo "MODX_CAS_PILOT_UNCONNECTED_OK\n";
