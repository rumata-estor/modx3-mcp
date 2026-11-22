<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Пакетный ресурс не должен возвращать success после ошибки процессора.
 * EN: bulk_resources must fail closed on a per-resource mutation failure.
 */
namespace ModxMcp\Tools {
    interface ToolInterface {
        public function name();
        public function group();
        public function isMutation();
        public function supports($context);
        public function execute($context,array $data);
    }
    class ResourceListTool {}
    class ElementUpdateTool {
        public static $fail=0;
        public static $attempts=array();
        public function execute($context,array $data) {
            self::$attempts[]=(int)$data['id'];
            if (self::$fail===(int)$data['id'])
                throw new \RuntimeException('injected write failure');
            return array('ok'=>true);
        }
    }
    class ElementDeleteTool {
        public static $fail=0;
        public static $attempts=array();
        public function execute($context,array $data) {
            self::$attempts[]=(int)$data['id'];
            if(self::$fail===(int)$data['id'])
                throw new \RuntimeException('injected delete failure');
            return array('ok'=>true);
        }
    }
    class ElementMutationSupport {
        public static $refreshes=0;
        public static function refreshCache($context){self::$refreshes++;}
    }
    class AuditSupport {
        public static function log($context,$action,$type,array $fields){}
    }
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/BulkResourcesTool.php';
}
namespace {
class ModxMCPClientException extends \RuntimeException {}
class BulkFaultResource {
    public $id;
    public function __construct($id){$this->id=$id;}
    public function get($name){
        if($name==='pagetitle')return 'temporary-'.$this->id;
        if($name==='published')return 0;
        if($name==='template')return 0;
        if($name==='parent')return 0;
        return null;
    }
}
class BulkFaultModx {
    public $missingIds=array();
    public $calls=array();
    public $vanishAfterPreflight=0;
    public function getObject($class,$id) {
        $id=(int)$id;
        $this->calls[$id]=($this->calls[$id]??0)+1;
        if(in_array($id,$this->missingIds,true)
            || ($id===$this->vanishAfterPreflight && $this->calls[$id]>1)){
            return null;
        }
        return $class==='FakeResource' && in_array($id,array(1,2),true)
            ? new BulkFaultResource($id):null;
    }
}
class BulkFaultPlatform {
    public function className($name){return $name==='resource'?'FakeResource':null;}
}
class BulkFaultContext {
    private $m;private $p;
    public function __construct(){
        $this->m=new BulkFaultModx();$this->p=new BulkFaultPlatform();
    }
    public function modx(){return $this->m;}
    public function platform(){return $this->p;}
}
function bulkCheck($condition,$label){
    if(!$condition)throw new \RuntimeException('BULK_PARTIAL_FAILED: '.$label);
}
$ctx=new BulkFaultContext();
$tool=new \ModxMcp\Tools\BulkResourcesTool();
$data=array('operation'=>'publish','ids'=>array(1,2));
\ModxMcp\Tools\ElementUpdateTool::$fail=2;
$uncertain=false;
try{$tool->execute($ctx,$data);}
catch(ModxMCPClientException $e){
    $uncertain=strpos($e->getMessage(),'MUTATION_OUTCOME_UNCERTAIN:')===0
        && strpos($e->getMessage(),'resource 2')!==false;
}
bulkCheck($uncertain,'second failure returned apparent batch success');
bulkCheck(\ModxMcp\Tools\ElementUpdateTool::$attempts===array(1,2),
    'second failure did not occur after first resource');
bulkCheck(\ModxMcp\Tools\ElementMutationSupport::$refreshes===1,
    'partial failure did not invalidate cache');
\ModxMcp\Tools\ElementUpdateTool::$fail=1;
$firstDenied=false;
try{$tool->execute($ctx,$data);}
catch(ModxMCPClientException $e){
    $firstDenied=strpos($e->getMessage(),'MUTATION_OUTCOME_UNCERTAIN:')===0;
}
bulkCheck($firstDenied,'first failure was incorrectly reported successful');
\ModxMcp\Tools\ElementUpdateTool::$fail=2;
$preview=$tool->execute($ctx,array_merge($data,array('dry_run'=>true)));
bulkCheck(($preview['count']??0)===2 && $preview['dry_run']===true,
    'dry_run changed during fault handling');
\ModxMcp\Tools\ElementUpdateTool::$fail=0;
$success=$tool->execute($ctx,$data);
bulkCheck(($success['count']??0)===2
    && ($success['results'][0]['status']??'')==='ok'
    && ($success['results'][1]['status']??'')==='ok',
    'normal batch response broken');
\ModxMcp\Tools\ElementDeleteTool::$fail=2;
$deleteError=false;
try{$tool->execute($ctx,array('operation'=>'delete','ids'=>array(1,2)));}
catch(ModxMCPClientException $e){
    $deleteError=strpos($e->getMessage(),'MUTATION_OUTCOME_UNCERTAIN:')===0;
}
bulkCheck($deleteError,'delete second failure returned apparent success');
// RU: Заведомо отсутствующая цель блокирует весь пакет до первой записи.
// EN: Pre-existing missing target must fail before the first mutation.
$ctx=new BulkFaultContext();
$ctx->modx()->missingIds=array(2);
\ModxMcp\Tools\ElementUpdateTool::$attempts=array();
$missingDenied=false;
try {$tool->execute($ctx,$data);}
catch(ModxMCPClientException $e) {
    $missingDenied=strpos($e->getMessage(),'NOT_FOUND:')===0;
}
bulkCheck($missingDenied
    && \ModxMcp\Tools\ElementUpdateTool::$attempts===array(),
    'missing ID did not block all writes during preflight');
// RU: В dry_run можно показывать отсутствующие цели, ничего не меняя.
// EN: Preview may still report missing IDs, without any mutations.
$preview=$tool->execute($ctx,array_merge($data,array('dry_run'=>true)));
bulkCheck(($preview['results'][1]['status']??'')==='not_found',
    'dry-run not_found reporting changed');
// RU: Цель исчезла уже после preflight — это неопределённый исход.
// EN: Target disappearing after preflight must flag uncertain outcome.
$ctx=new BulkFaultContext();
$ctx->modx()->vanishAfterPreflight=2;
\ModxMcp\Tools\ElementUpdateTool::$attempts=array();
$vanishedUncertain=false;
try {$tool->execute($ctx,$data);}
catch(ModxMCPClientException $e) {
    $vanishedUncertain=strpos($e->getMessage(),'MUTATION_OUTCOME_UNCERTAIN:')===0;
}
bulkCheck($vanishedUncertain
    && \ModxMcp\Tools\ElementUpdateTool::$attempts===array(1),
    'vanished target after first write was not uncertain');
echo 'BULK_RESOURCES_PARTIAL_OUTCOME_OK checks=10'.PHP_EOL;
}
