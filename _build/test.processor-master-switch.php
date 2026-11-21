<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Проверка «processor-master-switch»; тестовый код не запускается на production.
 * EN: «processor-master-switch» regression test; not executed in production.
 */
/** Fake dispatcher target: gate before any processor call. No real MODX. */
namespace ModxMcp\Tools {
    interface ToolInterface {
        public function name(); public function group(); public function isMutation();
        public function supports($context); public function execute($context,array $data);
    }
    class AuditSupport {public static $calls=0; public static function log($ctx,$name,$type,$data){self::$calls++;}}
    class ProcessorSupport {
        public static function normalize($response){return array('success'=>true);}
        // RU: Fake проверяет контракт редактирования секретов отдельно.
        // EN: The fake delegates credential redaction to its own regression.
        public static function sanitizeProcessorOutput($result){return $result;}
        // RU: Модульный fake поддерживает новый контракт ответов MODX.
        // EN: The offline fake implements the MODX response shape contract.
        public static function decodedResponse($response){
            $raw=$response->getResponse();
            if(is_array($raw))return $raw;
            $value=is_string($raw)?json_decode($raw,true):null;
            return is_array($value)?$value:array();
        }
    }
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/RunProcessorTool.php';
}
namespace ModxMcp\Security {
    final class ProtectedAuthorization {
        public static $protected = false;
        public static $persistedMasterEnabled = false;
        public static function required($modx) { return self::$protected; }
        public static function processorMasterEnabled($modx) {
            return self::$persistedMasterEnabled;
        }
    }
    require_once __DIR__.'/../core/components/modxmcp/src/Security/ProcessorAccessPolicy.php';
}
namespace {
class ModxMCPClientException extends \RuntimeException {}
class ProcessorGateFakeModx {
    public $options=array();
    public $called=0;
    public function __construct($opts) {$this->options=$opts;}
    public function getOption($key,$unused=null,$default=null) {
        return array_key_exists($key,$this->options)?$this->options[$key]:$default;
    }
    public function runProcessor($path,$props,$opts) {
        $this->called++;return false;
    }
}
class ProcessorGateFakeResponse {
    public function isError() {return false;}
    public function getResponse() {return '{"success":true}';}
}
class ProcessorGateFakePlatform {
    public $called=0;
    public $respond=false;
    public function runProcessor($modx,$path,$props) {$this->called++;return $this->respond?new ProcessorGateFakeResponse():false;}
    public function processorTarget($path) {return $path;}
}
class ProcessorGateFakeContext {
    public $m;public $p;
    public function __construct($m) {$this->m=$m;$this->p=new ProcessorGateFakePlatform();}
    public function modx() {return $this->m;}
    public function platform() {return $this->p;}
}
function gateDeny($opts,$data,$label) {
    $m=new ProcessorGateFakeModx($opts);
    $ctx=new ProcessorGateFakeContext($m);
    try {
        (new \ModxMcp\Tools\RunProcessorTool())->execute($ctx,$data);
    } catch (\Throwable $e) {
        if ($m->called!==0 || $ctx->p->called!==0) {
            throw new \RuntimeException('GATE_BYPASS: '.$label);
        }
        if (strpos($e->getMessage(),'AUTH_DENIED')===false
            && strpos($e->getMessage(),'run_processor is disabled')===false) {
            throw new \RuntimeException('UNEXPECTED_DENIAL '.$label.': '.$e->getMessage());
        }
        return;
    }
    throw new \RuntimeException('GATE_ACCEPTED '.$label);
}
$default=array(
    'modxmcp.allow_run_processor'=>false,
    'modxmcp.processor_access_mode'=>'disabled',
    'modxmcp.allowed_processors'=>'system/clearcache',
    'modxmcp.privileged_processors'=>'security/user/create',
);
gateDeny($default,array('processor'=>'system/clearcache'),'master-disabled');
$enabled=$default;$enabled['modxmcp.allow_run_processor']=true;
gateDeny($enabled,array('processor'=>'system/clearcache'),'mode-disabled');
$enabled['modxmcp.processor_access_mode']='allowlist';
gateDeny($enabled,array('processor'=>'security/user/create'),'privileged-outside-mode');
$adminMode=$enabled;$adminMode['modxmcp.processor_access_mode']='privileged';
gateDeny($adminMode,array('processor'=>'security/user/create'),'privileged-requires-ed25519');
gateDeny($enabled,array('processor'=>'system/clearcache','processors_path'=>'/tmp'), 'custom-loader');
gateDeny($enabled,array('processor'=>'system/settings/update'),'trust-anchor');
gateDeny($enabled,array('processor'=>'../system/clearcache'),'traversal');
$m=new ProcessorGateFakeModx($enabled);
$ctx=new ProcessorGateFakeContext($m);
$ctx->p->respond=true;
$result=(new \ModxMcp\Tools\RunProcessorTool())->execute($ctx,array('processor'=>'system/clearcache'));
if ($ctx->p->called!==1 || $m->called!==0 || !is_array($result) || !isset($result['success']) || $result['success']!==true
    || \ModxMcp\Tools\AuditSupport::$calls!==1) {
    throw new \RuntimeException('ALLOWLIST_POSITIVE_DISPATCH_FAILED');
}
// RU: В защищённом режиме фактическая политика БД важнее кеша PHP.
// EN: Protected mode must use persisted policy even when PHP cache disagrees.
\ModxMcp\Security\ProtectedAuthorization::$protected=true;
\ModxMcp\Security\ProtectedAuthorization::$persistedMasterEnabled=false;
$staleCacheEnabled=$enabled;
$staleCacheEnabled['modxmcp.allow_run_processor']=true;
gateDeny($staleCacheEnabled,array('processor'=>'resource/getlist'),
    'protected-master-false-overrides-cached-true');
\ModxMcp\Security\ProtectedAuthorization::$persistedMasterEnabled=true;
$staleCacheDisabled=$enabled;
$staleCacheDisabled['modxmcp.allow_run_processor']=false;
$protectedModx=new ProcessorGateFakeModx($staleCacheDisabled);
$protectedContext=new ProcessorGateFakeContext($protectedModx);
$protectedContext->p->respond=true;
$protectedResult=(new \ModxMcp\Tools\RunProcessorTool())->execute(
    $protectedContext,array('processor'=>'resource/getlist')
);
if (!is_array($protectedResult) || empty($protectedResult['success'])
    || $protectedContext->p->called!==1) {
    throw new \RuntimeException('PROTECTED_PERSISTED_MASTER_IGNORED');
}
echo 'PROCESSOR_MASTER_SWITCH_GATE_OK checks=10'.PHP_EOL;
}
