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
class ModxMCPClientException extends \RuntimeException {}
require_once __DIR__.'/../core/components/modxmcp/src/Security/MutationOutcomeGuard.php';
class TxFakeModx {
    public $beginCalls=0, $commitCalls=0, $rollbackCalls=0, $logs=array();
    public $failBegin=false, $failCommit=false, $throwCommit=false;
    public $failRollback=false, $throwRollback=false;
    public function beginTransaction() {$this->beginCalls++;return !$this->failBegin;}
    public function commit() {
        $this->commitCalls++;
        if ($this->throwCommit) throw new \RuntimeException('SECRET_DB_COMMIT_FAULT');
        return !$this->failCommit;
    }
    public function rollback() {
        $this->rollbackCalls++;
        if ($this->throwRollback) throw new \RuntimeException('SECRET_DB_ROLLBACK_FAULT');
        return !$this->failRollback;
    }
    public function log($level,$message) {$this->logs[]=$message;}
}
class TxFakeContext {
    private $modx;
    public function __construct($modx) {$this->modx=$modx;}
    public function modx() {return $this->modx;}
}
function txRequire($condition,$label) {if(!$condition)throw new \RuntimeException('TX_TEST_FAIL: '.$label);}
function txExpectThrow($fn,$label) {
    try { $fn(); } catch(\RuntimeException $e) {return $e;}
    throw new \RuntimeException('TX_TEST_FAIL: expected rejection '.$label);
}
function txUnknown($error,$label) {
    txRequire($error instanceof ModxMCPClientException,$label.' dedicated exception');
    txRequire(strpos($error->getMessage(),'MUTATION_OUTCOME_UNCERTAIN:')===0,
        $label.' machine-readable fail-closed status');
    txRequire(strpos($error->getMessage(),'SECRET_')===false
        && $error->getPrevious()===null,$label.' redacts native faults');
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
// COMMIT response may fail after the DB server has already committed.
$commitError=txExpectThrow(function()use($c,&$fired){
    \ModxMcp\Tools\StateSupport::withRevisionTransaction($c,function()use($c,&$fired){
        \ModxMcp\Tools\StateSupport::afterRevisionCommit($c,function()use(&$fired){$fired++;});
    });
},'commit failure');
txUnknown($commitError,'commit false');$checks++;
txRequire($m->commitCalls===1&&$m->rollbackCalls===1&&$fired===0,'failed commit skips callbacks');$checks++;

$m=new TxFakeModx();$m->throwCommit=true;$c=new TxFakeContext($m);$writes=0;
$commitError=txExpectThrow(function()use($c,&$writes){
    \ModxMcp\Tools\StateSupport::withRevisionTransaction($c,function()use(&$writes){
        $writes++;
        return 'possibly committed';
    });
},'commit exception after possible write');
txUnknown($commitError,'commit exception');$checks++;
txRequire($writes===1&&$m->commitCalls===1&&$m->rollbackCalls===1,
    'commit exception must not replay write');$checks++;

$m=new TxFakeModx();$m->failRollback=true;$c=new TxFakeContext($m);
$rollbackError=txExpectThrow(function()use($c){
    \ModxMcp\Tools\StateSupport::withRevisionTransaction($c,function(){
        throw new \RuntimeException('SECRET_CALLBACK_AFTER_WRITE');
    });
},'failed rollback');
txUnknown($rollbackError,'rollback false');$checks++;
txRequire($m->rollbackCalls===1&&$m->commitCalls===0,
    'rollback failure not followed by commit or replay');$checks++;

$m=new TxFakeModx();$m->throwRollback=true;$c=new TxFakeContext($m);
$rollbackError=txExpectThrow(function()use($c){
    \ModxMcp\Tools\StateSupport::withRevisionTransaction($c,function(){
        throw new \RuntimeException('SECRET_CALLBACK_AFTER_WRITE');
    });
},'rollback exception');
txUnknown($rollbackError,'rollback exception');$checks++;
txRequire($m->rollbackCalls===1&&$m->commitCalls===0,
    'throwing rollback not followed by commit or replay');$checks++;
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
// DB deadlock may throw PDOException, or xPDO::execute can return false.
// Even a confirmed DB rollback cannot undo a plugin or filesystem effect.
foreach (array(
    new \RuntimeException('SENSITIVE_NATIVE_EXECUTE_FALSE'),
    new \PDOException('SQLSTATE[40001]: SENSITIVE_NATIVE_DEADLOCK'),
) as $fault) {
    $m=new TxFakeModx();$c=new TxFakeContext($m);
    $err=txExpectThrow(function()use($c,$fault){
        \ModxMcp\Tools\StateSupport::withRevisionTransaction($c,function()use($fault){
            throw $fault;
        });
    },'unknown native transaction error');
    txRequire($err instanceof ModxMCPClientException
        && strpos($err->getMessage(),'MUTATION_OUTCOME_UNCERTAIN:')===0
        && strpos($err->getMessage(),'SENSITIVE_NATIVE')===false
        && $err->getPrevious()===null
        && $m->rollbackCalls===1 && $m->commitCalls===0,
        'deadlock/native failure sanitized and never replayed');
    $checks++;
}
$m=new TxFakeModx();$c=new TxFakeContext($m);
$err=txExpectThrow(function()use($c){
    \ModxMcp\Tools\StateSupport::withRevisionTransaction($c,function(){
        throw new ModxMCPClientException('STALE_STATE: target was changed');
    });
},'deterministic CAS refusal');
txRequire($err instanceof ModxMCPClientException
    && strpos($err->getMessage(),'STALE_STATE:')===0
    && $m->rollbackCalls===1, 'CAS preflight refusal preserved');
$checks++;
echo 'TX_REVISION_FAULT_MATRIX_OK checks='.$checks.PHP_EOL;
}
