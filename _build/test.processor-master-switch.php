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
    public $fail=false;
    public function __construct($fail=false){$this->fail=$fail;}
    public function isError() {return $this->fail;}
    public function getResponse() {return '{"success":true}';}
    public function getMessage() {return 'secret-should-not-appear';}
}
class ProcessorGateFakePlatform {
    public $called=0;
    public $respond=false;
    public $fail=false;
    public $throw=false;
    public function runProcessor($modx,$path,$props) {
        $this->called++;
        if($this->throw)throw new \RuntimeException('SECRET: fake-auth-token');
        return $this->respond
            ? new ProcessorGateFakeResponse($this->fail):false;
    }
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
// RU: Неявное приведение PHP строки 'false' давало опасное true.
// EN: Nonempty string 'false' must not enable the processor master switch.
foreach (array('false','off','yes','enabled','invalid',2) as $wrong) {
    $mismatch=$default;
    $mismatch['modxmcp.allow_run_processor']=$wrong;
    $mismatch['modxmcp.processor_access_mode']='allowlist';
    gateDeny($mismatch,array('processor'=>'system/clearcache'),
        'noncanonical-master-'.(string)$wrong);
}
$enabled=$default;$enabled['modxmcp.allow_run_processor']=true;
gateDeny($enabled,array('processor'=>'system/clearcache'),'mode-disabled');
$enabled['modxmcp.processor_access_mode']='allowlist';
gateDeny($enabled,array('processor'=>'security/user/create'),'privileged-outside-mode');
$adminMode=$enabled;$adminMode['modxmcp.processor_access_mode']='privileged';
gateDeny($adminMode,array('processor'=>'security/user/create'),'privileged-requires-ed25519');
gateDeny($enabled,array('processor'=>'system/clearcache','processors_path'=>'/tmp'), 'custom-loader');
gateDeny($enabled,array('processor'=>'system/settings/update'),'trust-anchor');
gateDeny($enabled,array('processor'=>'../system/clearcache'),'traversal');
foreach (array('false',17,'',true,null) as $badProperties) {
    gateDeny($enabled,array('processor'=>'system/clearcache',
        'properties'=>$badProperties),'invalid-properties');
}
$m=new ProcessorGateFakeModx($enabled);
$ctx=new ProcessorGateFakeContext($m);
$ctx->p->respond=true;
$result=(new \ModxMcp\Tools\RunProcessorTool())->execute($ctx,array('processor'=>'system/clearcache'));
if ($ctx->p->called!==1 || $m->called!==0 || !is_array($result) || !isset($result['success']) || $result['success']!==true
    || \ModxMcp\Tools\AuditSupport::$calls!==1) {
    throw new \RuntimeException('ALLOWLIST_POSITIVE_DISPATCH_FAILED');
}
// RU: Сбой стороннего процессора не должен раскрывать секретную строку.
// EN: A failed third-party processor must not expose its raw secret message.
$ctx->p->fail=true;
try {
    (new \ModxMcp\Tools\RunProcessorTool())->execute(
        $ctx,array('processor'=>'system/clearcache')
    );
    throw new \RuntimeException('PROCESSOR_ERROR_UNSAFE_SUCCESS');
} catch(ModxMCPClientException $e) {
    if(strpos($e->getMessage(),'MUTATION_OUTCOME_UNCERTAIN')!==0
        || strpos($e->getMessage(),'secret-should-not-appear')!==false)
        throw new \RuntimeException('PROCESSOR_ERROR_SECRET_LEAK');
}
// RU: Нет ответа или исключение после dispatch — исход неопределённый,
// RU: секреты из текста исключения нельзя возвращать клиенту.
// EN: Missing response or a thrown processor exception is uncertain;
// EN: raw exception text may contain credentials and must be suppressed.
$ctx->p->fail=false;
$ctx->p->respond=false;
$noResponseUncertain=false;
try {(new \ModxMcp\Tools\RunProcessorTool())->execute(
    $ctx,array('processor'=>'system/clearcache')
);} catch(ModxMCPClientException $e) {
    $noResponseUncertain=strpos($e->getMessage(),'MUTATION_OUTCOME_UNCERTAIN:')===0;
}
if(!$noResponseUncertain)throw new \RuntimeException('PROCESSOR_EMPTY_RESPONSE_NOT_UNCERTAIN');
$ctx->p->throw=true;
$thrownUncertain=false;
try {(new \ModxMcp\Tools\RunProcessorTool())->execute(
    $ctx,array('processor'=>'system/clearcache')
);} catch(ModxMCPClientException $e) {
    $thrownUncertain=strpos($e->getMessage(),'MUTATION_OUTCOME_UNCERTAIN:')===0
        && strpos($e->getMessage(),'fake-auth-token')===false;
}
if(!$thrownUncertain)throw new \RuntimeException('PROCESSOR_THROW_SECRET_OR_STATUS');
$ctx->p->throw=false;
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
