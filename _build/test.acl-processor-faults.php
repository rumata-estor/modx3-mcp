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
        $expected = $name === 'create_user'
            ? 'MUTATION_OUTCOME_UNCERTAIN'
            : 'injected processor failure';
        if (strpos($e->getMessage(),$expected)===false)throw $e;
    }
    if ($ctx->platform->calls!==1 || $ctx->modx->cache->flushes!==0
        || \ModxMcp\Tools\AuditSupport::$calls!==$auditBefore) {
        throw new \RuntimeException('ACL_FAULT_FAIL: post-error cache or audit '.$name);
    }
    $checks++;
}
if ($checks!==29)throw new \RuntimeException('ACL_FAULT_FAIL: unexpected ACL catalog size '.$checks);
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
    .' secret_checks=1 context_denials='.$contextChecks.PHP_EOL;
}
