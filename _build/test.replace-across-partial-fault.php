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
        public static $extraHits=0;
        public static function search($ctx,array $data){
            if(self::$failureBeforeWrites)throw new \RuntimeException('search fault');
            $hits=array(
                array('type'=>'chunk','id'=>1,'name'=>'first'),
                array('type'=>'chunk','id'=>2,'name'=>'second'),
            );
            for($i=0;$i<self::$extraHits;$i++) {
                $hits[]=array('type'=>'chunk','id'=>1,'name'=>'extra');
            }
            return array('results'=>array_slice($hits,0,(int)$data['limit']));
        }
    }
    class ElementContentSupport {
        public static $failWrite=0;
        public static $failRead=0;
        public static $driftId=0;
        public static $readCalls=array();
        public static $changed=array();
        public static function map($ctx,$type){return array('class'=>'FakeChunk','field'=>'snippet');}
        public static function read($ctx,$element,$map){
            if (self::$failRead === $element->id) {
                throw new \RuntimeException('injected second read failure');
            }
            self::$readCalls[$element->id]
                =(self::$readCalls[$element->id]??0)+1;
            $content=$element->content;
            if (self::$driftId === $element->id
                && self::$readCalls[$element->id] > 1) {
                $content='CHANGED OUTSIDE needle AFTER';
            }
            return array($content,false,null);
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
    public $missingId=0;
    public $vanishAfterPreflightId=0;
    public $calls=array();
    public function __construct() {
        $this->objects=array(1=>new ReplacementFixture(1),2=>new ReplacementFixture(2));
    }
    public function getObject($class,$id,$cache=null){
        $id=(int)$id;
        $this->calls[$id]=($this->calls[$id]??0)+1;
        return $class==='FakeChunk' && $id!==$this->missingId
            && !($id===$this->vanishAfterPreflightId
                && $this->calls[$id]>1)
            ? ($this->objects[$id]??null):null;
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
// RU: Ошибка чтения второй цели должна случиться ДО записи первой.
// EN: Second-target read errors must happen before the first write.
\ModxMcp\Tools\ElementContentSupport::$failRead=2;
$readFailed=false;
try {$tool->execute($ctx,$arg);}
catch(\RuntimeException $e) {
    $readFailed=$e->getMessage()==='injected second read failure';
}
batchCheck($readFailed
    && \ModxMcp\Tools\ElementContentSupport::$changed===array(),
    'second-target read failed after a first-target write');
\ModxMcp\Tools\ElementContentSupport::$failRead=0;
// RU: Объект, исчезнувший до записи, останавливает пакет целиком.
// EN: Missing second target must reject the batch before mutations.
$ctx->modx()->missingId=2;
$missingPreflight=false;
try {$tool->execute($ctx,$arg);}
catch(ModxMCPClientException $e) {
    $missingPreflight=strpos($e->getMessage(),'NOT_FOUND:')===0;
}
batchCheck($missingPreflight
    && \ModxMcp\Tools\ElementContentSupport::$changed===array(),
    'missing second target allowed first-target write');
$ctx->modx()->missingId=0;
// RU: Внешнее изменение первой цели после preflight: ни одной записи.
// EN: First-target drift after preflight prevents all writes.
\ModxMcp\Tools\ElementContentSupport::$readCalls=array();
\ModxMcp\Tools\ElementContentSupport::$driftId=1;
$firstStale=false;
try {$tool->execute($ctx,$arg);}
catch(ModxMCPClientException $e){
    $firstStale=strpos($e->getMessage(),'STALE_STATE:')===0;
}
batchCheck($firstStale
    && \ModxMcp\Tools\ElementContentSupport::$changed===array(),
    'first-target prewrite drift overwrote newer content');
// RU: Внешнее изменение второй цели после записи первой — неопределённо.
// EN: Second-target drift after first write needs uncertain status.
\ModxMcp\Tools\ElementContentSupport::$readCalls=array();
\ModxMcp\Tools\ElementContentSupport::$driftId=2;
$secondUncertain=false;
try {$tool->execute($ctx,$arg);}
catch(ModxMCPClientException $e){
    $secondUncertain=strpos($e->getMessage(),'MUTATION_OUTCOME_UNCERTAIN:')===0;
}
batchCheck($secondUncertain
    && (\ModxMcp\Tools\ElementContentSupport::$changed[1]??null)
        ==='BEFORE NEW AFTER'
    && !isset(\ModxMcp\Tools\ElementContentSupport::$changed[2]),
    'second-target drift did not protect changed content');
\ModxMcp\Tools\ElementContentSupport::$readCalls=array();
\ModxMcp\Tools\ElementContentSupport::$driftId=0;
\ModxMcp\Tools\ElementContentSupport::$changed=array();
// RU: Вторая цель исчезает именно после preflight и первой записи.
// EN: Second target disappears after preflight and the first write.
$ctx->modx()->calls=array();
$ctx->modx()->vanishAfterPreflightId=2;
$vanishedUncertain=false;
try{$tool->execute($ctx,$arg);}
catch(ModxMCPClientException $e){
    $vanishedUncertain=strpos($e->getMessage(),'MUTATION_OUTCOME_UNCERTAIN:')===0;
}
batchCheck($vanishedUncertain
    && (\ModxMcp\Tools\ElementContentSupport::$changed[1]??null)
        ==='BEFORE NEW AFTER'
    && !isset(\ModxMcp\Tools\ElementContentSupport::$changed[2]),
    'vanished second target after preflight was not guarded');
$ctx->modx()->vanishAfterPreflightId=0;
$ctx->modx()->calls=array();
\ModxMcp\Tools\ElementContentSupport::$changed=array();
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
batchCheck(\ModxMcp\Tools\ElementMutationSupport::$refreshes===3,
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
    && \ModxMcp\Tools\ElementMutationSupport::$refreshes===5,
    'successful batch must still report full result and refresh once');
// RU: Переполнение лимита поиска не должно тихо изменять подмножество.
// EN: A result set larger than the limit must fail before mutation.
\ModxMcp\Tools\SearchSupport::$extraHits=1;
\ModxMcp\Tools\ElementContentSupport::$changed=array();
$truncatedDenied=false;
try {$tool->execute($ctx,array_merge($arg,array('limit'=>2)));}
catch(ModxMCPClientException $e) {
    $truncatedDenied=strpos($e->getMessage(),'VALIDATION_ERROR:')===0;
}
batchCheck($truncatedDenied
    && \ModxMcp\Tools\ElementContentSupport::$changed===array(),
    'truncated search silently mutated selected targets');
$truncatedPreview=$tool->execute($ctx,array_merge($arg,array(
    'limit'=>2,'dry_run'=>true
)));
batchCheck(($truncatedPreview['search_limit_reached']??false)===true
    && ($truncatedPreview['elements']??0)===2,
    'dry-run did not disclose incomplete search');
// RU: При 200 совпадениях нельзя доказать, что 201-го нет.
// EN: The hard 200-result cap cannot prove that no 201st match exists.
\ModxMcp\Tools\SearchSupport::$extraHits=198;
$hardCapDenied=false;
try {$tool->execute($ctx,array_merge($arg,array('limit'=>200)));}
catch(ModxMCPClientException $e) {
    $hardCapDenied=strpos($e->getMessage(),'VALIDATION_ERROR:')===0;
}
batchCheck($hardCapDenied
    && \ModxMcp\Tools\ElementContentSupport::$changed===array(),
    'hard 200-target search cap silently accepted');
\ModxMcp\Tools\SearchSupport::$extraHits=0;
echo 'REPLACE_ACROSS_PARTIAL_OUTCOME_OK checks=15'.PHP_EOL;
}
