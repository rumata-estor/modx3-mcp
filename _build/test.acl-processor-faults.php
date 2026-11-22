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
        // RU: Реальная реализация проверяется в тестах редактирования секретов.
        // EN: The real sanitizer is covered by dedicated credential tests.
        public static function sanitizeProcessorOutput($value){return $value;}
    }
    require_once __DIR__.'/../core/components/modxmcp/src/Security/ProtectedSettingsGuard.php';
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
class AclFakeError {
    public $resets=0;
    public function reset() {$this->resets++;}
}
class AclFakeModx {
    public $lexicon;public $cache;public $error;
    public function __construct(){
        $this->lexicon=new AclFakeLexicon();
        $this->cache=new AclFakeCache();
        $this->error=new AclFakeError();
    }
    public function getCacheManager(){return $this->cache;}
}
class AclFakeResponse {
    public $error;
    public function __construct($error){$this->error=$error;}
    public function isError(){return $this->error;}
}
class AclFakePlatform {
    public $calls=0;public $fail=true;
    public $noResponse=false;
    public function runProcessor($modx,$name,$props) {
        $this->calls++;
        return $this->noResponse ? false : new AclFakeResponse($this->fail);
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
        // RU: Ошибки всех ACL-мутаций скрывают текст процессора.
        // EN: All ACL mutation failures must suppress raw processor messages.
        $expected = 'MUTATION_OUTCOME_UNCERTAIN';
        if (strpos($e->getMessage(),$expected)===false)throw $e;
    }
    if ($ctx->platform->calls!==1 || $ctx->modx->cache->flushes!==0
        || $ctx->modx->error->resets!==1
        || \ModxMcp\Tools\AuditSupport::$calls!==$auditBefore) {
        throw new \RuntimeException('ACL_FAULT_FAIL: post-error cache or audit '.$name);
    }
    $checks++;
}
if ($checks!==29)throw new \RuntimeException('ACL_FAULT_FAIL: unexpected ACL catalog size '.$checks);
// RU: Каждый ACL-процессор может выполнить часть работы и не ответить.
// RU: Нельзя интерпретировать отсутствие ответа как безопасный отказ.
// EN: Every ACL action may partially mutate state and then return nothing;
// EN: never classify a missing response as safe-to-retry failure.
$missingResponseChecks=0;
foreach ($specs as $name=>$spec) {
    if ($spec['route']!=='acl') continue;
    $ctx=new AclFakeContext();
    $ctx->platform->noResponse=true;
    $tool=new \ModxMcp\Tools\ProcessorMutationTool($name,$spec);
    try {
        $tool->execute($ctx,array('id'=>9,'name'=>'fixture'));
        throw new \RuntimeException('ACL_MISSING_RESPONSE_ACCEPTED: '.$name);
    } catch (ModxMCPClientException $e) {
        if (strpos($e->getMessage(),'MUTATION_OUTCOME_UNCERTAIN')!==0)
            throw new \RuntimeException('ACL_MISSING_RESPONSE_RETRY_RISK: '.$name);
    }
    if ($ctx->platform->calls!==1 || $ctx->modx->cache->flushes!==0)
        throw new \RuntimeException('ACL_MISSING_RESPONSE_SIDE_EFFECT: '.$name);
    $missingResponseChecks++;
}
if ($missingResponseChecks!==29) throw new \RuntimeException('ACL_NO_RESPONSE_COVERAGE');
// RU: Ошибка create_user не должна раскрывать пароль, даже если
// RU: штатный MODX-процессор включил его в текст ошибки.
// EN: A create_user error must not expose a generated password even if
// EN: the MODX processor includes that secret in its message.
$ctx=new AclFakeContext();
$tool=new \ModxMcp\Tools\ProcessorMutationTool('create_user',$specs['create_user']);
try {
    $tool->execute($ctx,array('username'=>'ci_user'));
    throw new \RuntimeException('ACL_SECRET_LEAK_TEST_FAILED: accepted error');
} catch (ModxMCPClientException $e) {
    if (strpos($e->getMessage(),'MUTATION_OUTCOME_UNCERTAIN')!==0
        || strpos($e->getMessage(),'injected processor failure')!==false) {
        throw new \RuntimeException('ACL_SECRET_LEAK_TEST_FAILED: unsafe error');
    }
}
// RU: Ни одна обычная операция create_user/update_user не должна
// RU: назначать или менять sudo через произвольные поля процессора.
// EN: User create/update must reject sudo-like processor parameters
// EN: before dispatch, regardless of capitalization or input value.
$sudoDenials=0;
foreach (array('create_user','update_user') as $userAction) {
    foreach (array('sudo', ' SuDo ', 'is_sudo') as $field) {
        foreach (array(1, 0) as $value) {
            $context=new AclFakeContext();
            $tool=new \ModxMcp\Tools\ProcessorMutationTool($userAction,$specs[$userAction]);
            try {
                $tool->execute($context,array('id'=>8,'username'=>'fixture',$field=>$value));
                throw new \RuntimeException('SUDO_MUTATION_ALLOWED: '.$userAction);
            } catch(ModxMCPClientException $e) {
                if(strpos($e->getMessage(),'AUTH_DENIED:')!==0)throw $e;
            }
            if($context->platform->calls!==0 || $context->modx->cache->flushes!==0)
                throw new \RuntimeException('SUDO_MUTATION_REACHED_PROCESSOR');
            $sudoDenials++;
        }
    }
}
if($sudoDenials!==12)throw new \RuntimeException('SUDO_DENIAL_COVERAGE_MISSING');
// RU: Проверка до запуска процессора; контекст не должен обойти защиту
// RU: настроек modxmcp.* через регистр, пробелы и вложенные поля.
// EN: Reject protected context setting mutations before processor dispatch,
// EN: including case/whitespace variations and nested settings payloads.
$contextChecks=0;
foreach (array(
    array('create_context_setting',array('key'=>' MODXMCP.enabled ', 'context_key'=>'web')),
    array('update_context_setting',array('key'=>'ModxMCP.api_token', 'context_key'=>'web')),
    array('delete_context_setting',array('key'=>' modxmcp.permit_public_key ')),
    array('update_context',array('key'=>'web','settings'=>array('modxmcp.enabled'=>'1'))),
    array('update_context',array('key'=>'web','settings'=>'{"modxmcp.enabled":"1"}')),
) as $case) {
    $context=new AclFakeContext();
    $tool=new \ModxMcp\Tools\ProcessorMutationTool(
        $case[0],$specs[$case[0]]
    );
    try {
        $tool->execute($context,$case[1]);
        throw new \RuntimeException('PROTECTED_CONTEXT_ALLOWED: '.$case[0]);
    } catch (ModxMCPClientException $e) {
        if (strpos($e->getMessage(),'settings')===false)throw $e;
    }
    if ($context->platform->calls!==0) {
        throw new \RuntimeException('PROTECTED_CONTEXT_PROCESSOR_EXECUTED');
    }
    $contextChecks++;
}
echo 'ACL_PROCESSOR_ERROR_MATRIX_OK actions='.$checks
    .' no_response='.$missingResponseChecks
    .' secret_checks=1 context_denials='.$contextChecks.PHP_EOL;
}
