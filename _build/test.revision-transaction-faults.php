<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Проверка «revision-transaction-faults»; тестовый код не запускается на production.
 * EN: «revision-transaction-faults» regression test; not executed in production.
 */
/** Offline transaction-failure injection: no MODX install, no database or filesystem writes. */
namespace ModxMcp\Tools {
    // StateSupport is the actual implementation under test.
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/StateSupport.php';
}
namespace {
class TxFakeModx {
    public $beginCalls=0, $commitCalls=0, $rollbackCalls=0, $logs=array();
    public $failBegin=false, $failCommit=false;
    public function beginTransaction() {$this->beginCalls++;return !$this->failBegin;}
    public function commit() {$this->commitCalls++;return !$this->failCommit;}
    public function rollback() {$this->rollbackCalls++;return true;}
    public function log($level,$message) {$this->logs[]=$message;}
}
class TxFakeContext {
    private $modx;
    public function __construct($modx) {$this->modx=$modx;}
    public function modx() {return $this->modx;}
}
function txRequire($condition,$label) {if(!$condition)throw new \RuntimeException('TX_TEST_FAIL: '.$label);}
function txExpectThrow($fn,$label) {
    try { $fn(); } catch(\RuntimeException $e) {return;}
    throw new \RuntimeException('TX_TEST_FAIL: expected rejection '.$label);
}
$checks=0;
$m=new TxFakeModx();$c=new TxFakeContext($m);$fired=0;
$result=\ModxMcp\Tools\StateSupport::withRevisionTransaction($c,function()use($c,&$fired){
    txRequire(\ModxMcp\Tools\StateSupport::inRevisionTransaction($c),'transaction active');
    \ModxMcp\Tools\StateSupport::afterRevisionCommit($c,function()use(&$fired){$fired++;});
    txRequire($fired===0,'no premature callbacks');
    return 'saved';
});
txRequire($result==='saved'&&$m->commitCalls===1&&$m->rollbackCalls===0&&$fired===1,'successful commit');$checks++;
txRequire(!\ModxMcp\Tools\StateSupport::inRevisionTransaction($c),'success cleanup');$checks++;
$m=new TxFakeModx();$c=new TxFakeContext($m);$fired=0;
txExpectThrow(function()use($c,&$fired){
    \ModxMcp\Tools\StateSupport::withRevisionTransaction($c,function()use($c,&$fired){
        \ModxMcp\Tools\StateSupport::afterRevisionCommit($c,function()use(&$fired){$fired++;});
        throw new \RuntimeException('injected rollback');
    });
},'callback failure');
txRequire($m->rollbackCalls===1&&$m->commitCalls===0&&$fired===0,'rollback skips side effects');$checks++;
txRequire(!\ModxMcp\Tools\StateSupport::inRevisionTransaction($c),'rollback cleanup');$checks++;
$m=new TxFakeModx();$m->failBegin=true;$c=new TxFakeContext($m);$called=false;
txExpectThrow(function()use($c,&$called){
    \ModxMcp\Tools\StateSupport::withRevisionTransaction($c,function()use(&$called){$called=true;});
},'begin failure');
txRequire(!$called&&$m->commitCalls===0&&$m->rollbackCalls===0,'failed begin blocks write');$checks++;
$m=new TxFakeModx();$m->failCommit=true;$c=new TxFakeContext($m);$fired=0;
txExpectThrow(function()use($c,&$fired){
    \ModxMcp\Tools\StateSupport::withRevisionTransaction($c,function()use($c,&$fired){
        \ModxMcp\Tools\StateSupport::afterRevisionCommit($c,function()use(&$fired){$fired++;});
    });
},'commit failure');
txRequire($m->commitCalls===1&&$m->rollbackCalls===1&&$fired===0,'failed commit skips callbacks');$checks++;
$m=new TxFakeModx();$c=new TxFakeContext($m);
\ModxMcp\Tools\StateSupport::withRevisionTransaction($c,function()use($c){
    txExpectThrow(function()use($c){
        \ModxMcp\Tools\StateSupport::withRevisionTransaction($c,function(){});
    },'nested transaction');
});
txRequire($m->beginCalls===1&&$m->commitCalls===1,'nested begin blocked');$checks++;
$m=new TxFakeModx();$c=new TxFakeContext($m);$fired=0;
\ModxMcp\Tools\StateSupport::withRevisionTransaction($c,function()use($c,&$fired){
    \ModxMcp\Tools\StateSupport::afterRevisionCommit($c,function(){throw new \RuntimeException('after commit fault');});
    \ModxMcp\Tools\StateSupport::afterRevisionCommit($c,function()use(&$fired){$fired++;});
});
txRequire($fired===1&&$m->commitCalls===1&&$m->rollbackCalls===0&&count($m->logs)===1,'after commit fault isolated');$checks++;
echo 'TX_REVISION_FAULT_MATRIX_OK checks='.$checks.PHP_EOL;
}
