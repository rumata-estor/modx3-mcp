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
    public function getObject($class,$id) {
        return $class==='FakeResource' && in_array((int)$id,array(1,2),true)
            ? new BulkFaultResource((int)$id):null;
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
echo 'BULK_RESOURCES_PARTIAL_OUTCOME_OK checks=7'.PHP_EOL;
}
