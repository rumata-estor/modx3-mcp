<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Проверка «acl-processor-faults»; тестовый код не запускается на production.
 * EN: «acl-processor-faults» regression test; not executed in production.
 */
/** Offline fault injection for ACL mutation catalog; no MODX, DB or site writes. */
namespace ModxMcp\Registry {
    require_once __DIR__.'/../core/components/modxmcp/src/Registry/MutationProcessorCatalog.php';
}
namespace ModxMcp\Tools {
    interface ToolInterface {
        public function name(); public function group(); public function isMutation();
        public function supports($context); public function execute($context,array $data);
    }
    class AuditSupport {
        public static $calls=0;
        public static function log($context,$name,$type,$payload){self::$calls++;}
    }
    class ProcessorSupport {
        public static function error($response){return 'injected processor failure';}
        public static function normalize($response){return array('success'=>true);}
    }
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/ProcessorMutationTool.php';
}
namespace {
class ModxMCPClientException extends \RuntimeException {}
class AclFakeLexicon {
    public $loads=0;
    public function load() {$this->loads++;}
}
class AclFakeCache {
    public $flushes=0;
    public function flushPermissions() {$this->flushes++;}
}
class AclFakeModx {
    public $lexicon;public $cache;
    public function __construct(){$this->lexicon=new AclFakeLexicon();$this->cache=new AclFakeCache();}
    public function getCacheManager(){return $this->cache;}
}
class AclFakeResponse {
    public $error;
    public function __construct($error){$this->error=$error;}
    public function isError(){return $this->error;}
}
class AclFakePlatform {
    public $calls=0;public $fail=true;
    public function runProcessor($modx,$name,$props) {
        $this->calls++;return new AclFakeResponse($this->fail);
    }
    public function className($name){return 'modUserGroup';}
}
class AclFakeContext {
    public $modx;public $platform;
    public function __construct(){
        $this->modx=new AclFakeModx();
        $this->platform=new AclFakePlatform();
    }
    public function modx(){return $this->modx;}
    public function platform(){return $this->platform;}
}
$specs=\ModxMcp\Registry\MutationProcessorCatalog::specs();
$checks=0;
foreach($specs as $name=>$spec){
    if ($spec['route']!=='acl')continue;
    $ctx=new AclFakeContext();
    $tool=new \ModxMcp\Tools\ProcessorMutationTool($name,$spec);
    $auditBefore=\ModxMcp\Tools\AuditSupport::$calls;
    try {
        $tool->execute($ctx,array('id'=>9,'name'=>'fixture','resource'=>9,'resourceGroup'=>4));
        throw new \RuntimeException('ACL_FAULT_FAIL: did not reject '.$name);
    } catch (ModxMCPClientException $e) {
        if (strpos($e->getMessage(),'injected processor failure')===false)throw $e;
    }
    if ($ctx->platform->calls!==1 || $ctx->modx->cache->flushes!==0
        || \ModxMcp\Tools\AuditSupport::$calls!==$auditBefore) {
        throw new \RuntimeException('ACL_FAULT_FAIL: post-error cache or audit '.$name);
    }
    $checks++;
}
if ($checks!==29)throw new \RuntimeException('ACL_FAULT_FAIL: unexpected ACL catalog size '.$checks);
echo 'ACL_PROCESSOR_ERROR_MATRIX_OK actions='.$checks.PHP_EOL;
}
