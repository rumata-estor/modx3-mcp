<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Пакетная замена не обещает откат после записи первого элемента.
 * EN: Batch replacement must flag partial outcomes after any attempted write.
 */
namespace ModxMcp\Tools {
    interface ToolInterface {
        public function name();
        public function group();
        public function isMutation();
        public function supports($context);
        public function execute($context,array $data);
    }
    class SearchSupport {
        public static $failureBeforeWrites=false;
        public static function search($ctx,array $data){
            if(self::$failureBeforeWrites)throw new \RuntimeException('search fault');
            return array('results'=>array(
                array('type'=>'chunk','id'=>1,'name'=>'first'),
                array('type'=>'chunk','id'=>2,'name'=>'second'),
            ));
        }
    }
    class ElementContentSupport {
        public static $failWrite=0;
        public static $changed=array();
        public static function map($ctx,$type){return array('class'=>'FakeChunk','field'=>'snippet');}
        public static function read($ctx,$element,$map){
            return array($element->content,false,null);
        }
        public static function write($ctx,$element,$type,$map,$static,$path,$content) {
            if(self::$failWrite===$element->id) {
                throw new \RuntimeException('injected second element failure');
            }
            self::$changed[$element->id]=$content;
        }
    }
    class ElementMutationSupport {
        public static $refreshes=0;
        public static function transaction($ctx,$callback){return $callback();}
        public static function refreshCache($ctx){self::$refreshes++;}
    }
    class AuditSupport {
        public static function log($ctx,$name,$type,$info){}
    }
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/ReplaceAcrossTool.php';
}
namespace {
class ModxMCPClientException extends \RuntimeException {}
class ReplacementFixture {
    public $id;public $content='BEFORE needle AFTER';
    public function __construct($id){$this->id=$id;}
}
class BatchFaultModx {
    private $objects;
    public function __construct() {
        $this->objects=array(1=>new ReplacementFixture(1),2=>new ReplacementFixture(2));
    }
    public function getObject($class,$id,$cache=null){
        return $class==='FakeChunk'?($this->objects[$id]??null):null;
    }
}
class BatchFaultContext {
    private $modx;
    public function __construct(){$this->modx=new BatchFaultModx();}
    public function modx(){return $this->modx;}
    public function platform(){return true;}
}
function batchCheck($ok,$label) {
    if(!$ok)throw new \RuntimeException('REPLACE_ACROSS_FAULT_TEST_FAILED: '.$label);
}
$ctx=new BatchFaultContext();
$tool=new \ModxMcp\Tools\ReplaceAcrossTool();
$arg=array('find'=>'needle','replacement'=>'NEW','types'=>array('chunk'));
\ModxMcp\Tools\ElementContentSupport::$failWrite=2;
$uncertain=false;
try {$tool->execute($ctx,$arg);}
catch(ModxMCPClientException $e) {
    $uncertain=strpos($e->getMessage(),'MUTATION_OUTCOME_UNCERTAIN:')===0
        && strpos($e->getMessage(),'2 element writes')!==false;
}
batchCheck($uncertain,'second failure must be uncertain');
batchCheck((\ModxMcp\Tools\ElementContentSupport::$changed[1]??null)==='BEFORE NEW AFTER',
    'first element mutated before second failure');
batchCheck(!isset(\ModxMcp\Tools\ElementContentSupport::$changed[2]),
    'second element should not report successful write');
batchCheck(\ModxMcp\Tools\ElementMutationSupport::$refreshes===1,
    'partial failure must attempt to invalidate stale cache');
\ModxMcp\Tools\ElementContentSupport::$changed=array();
\ModxMcp\Tools\ElementContentSupport::$failWrite=1;
$firstUncertain=false;
try{$tool->execute($ctx,$arg);}
catch(ModxMCPClientException $e){
    $firstUncertain=strpos($e->getMessage(),'MUTATION_OUTCOME_UNCERTAIN:')===0;
}
batchCheck($firstUncertain,'first write failure may have side effects');
\ModxMcp\Tools\SearchSupport::$failureBeforeWrites=true;
$preflightFailure=false;
try{$tool->execute($ctx,$arg);}
catch(\RuntimeException $e){
    $preflightFailure=$e->getMessage()==='search fault';
}
batchCheck($preflightFailure,'search failure before writes must retain original error');
\ModxMcp\Tools\SearchSupport::$failureBeforeWrites=false;
\ModxMcp\Tools\ElementContentSupport::$failWrite=2;
$dry=$tool->execute($ctx,array_merge($arg,array('dry_run'=>true)));
batchCheck(($dry['elements']??0)===2,'dry run must remain available with write failures');
\ModxMcp\Tools\ElementContentSupport::$failWrite=0;
$ok=$tool->execute($ctx,$arg);
batchCheck(($ok['elements']??0)===2
    && \ModxMcp\Tools\ElementMutationSupport::$refreshes===3,
    'successful batch must still report full result and refresh once');
echo 'REPLACE_ACROSS_PARTIAL_OUTCOME_OK checks=7'.PHP_EOL;
}
