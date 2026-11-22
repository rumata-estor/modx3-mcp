<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Проверка сокрытия секретов во вложенном ответе произвольного процессора.
 * EN: Regression for nested credential redaction in generic processor output.
 */
namespace ModxMcp\Tools {
    interface ToolInterface {
        public function name(); public function group(); public function isMutation();
        public function supports($context); public function execute($context,array $data);
    }
    class AuditSupport {public static function log($context,$name,$route,$data){}}
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/ProcessorSupport.php';
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/ProcessorMutationTool.php';
}
namespace ModxMcp\Registry {
    require_once __DIR__.'/../core/components/modxmcp/src/Registry/MutationProcessorCatalog.php';
}
namespace {
class ModxMCPClientException extends \RuntimeException {}
$payload=array(
    'success'=>true,'message'=>'Generated password: secret-value',
    'object'=>array(
        'id'=>15,'username'=>'demo',
        'password'=>'secret-hash','token'=>'secret-token',
        'profile'=>array('private_key'=>'secret-key',
            'data'=>array(array('api_key'=>'secret-api','name'=>'visible'))),
    ),
    'results'=>array(array('id'=>20,'salt'=>'secret-salt','title'=>'visible')),
);
$safe=\ModxMcp\Tools\ProcessorSupport::sanitizeProcessorOutput($payload);
if ($safe['object']['username']!=='demo'
    || $safe['object']['id']!==15
    || $safe['results'][0]['title']!=='visible'
    || $safe['object']['profile']['data'][0]['name']!=='visible') {
    throw new \RuntimeException('PROCESSOR_SAFE_FIELDS_LOST');
}
$encoded=json_encode($safe);
foreach (array('secret-value','secret-hash','secret-token','secret-key',
    'secret-api','secret-salt') as $secret) {
    if (strpos($encoded,$secret)!==false)
        throw new \RuntimeException('PROCESSOR_SECRET_LEAK');
}
foreach (array(
    $safe['message'],$safe['object']['password'],$safe['object']['token'],
    $safe['object']['profile']['private_key'],
    $safe['object']['profile']['data'][0]['api_key'],
    $safe['results'][0]['salt']
) as $field) {
    if ($field!=='[REDACTED]')
        throw new \RuntimeException('PROCESSOR_REDACTION_MISSING');
}
// RU: Проверяем путь update_user через настоящий ACL-диспетчер,
// RU: а не только отдельную функцию маскирования.
// EN: Exercise the real update_user ACL response path, not merely the
// EN: standalone sanitizer helper.
class SecretAclResponse {
    public function isError(){return false;}
    public function getResponse(){return array('success'=>true,'object'=>array(
        'id'=>21,'username'=>'safe','password'=>'secret-acl-hash',
        'profile'=>array('api_key'=>'secret-acl-token')));
    }
    public function getObject(){return array();}
}
class SecretAclPlatform {
    public function runProcessor($modx,$processor,$props){
        if($processor!=='security/user/update')throw new \RuntimeException('ACL_ROUTE_WRONG');
        return new SecretAclResponse();
    }
}
class SecretAclCache {public function flushPermissions(){}}
class SecretAclError {public function reset(){}}
class SecretAclLexicon {public function load(){}}
class SecretAclModx {
    public $error;public $lexicon;
    public function __construct(){
        $this->error=new SecretAclError();$this->lexicon=new SecretAclLexicon();
    }
    public function getCacheManager(){return new SecretAclCache();}
}
class SecretAclContext {
    private $m;private $p;
    public function __construct(){
        $this->m=new SecretAclModx();$this->p=new SecretAclPlatform();
    }
    public function modx(){return $this->m;}
    public function platform(){return $this->p;}
}
$spec=\ModxMcp\Registry\MutationProcessorCatalog::specs()['update_user'];
$updated=(new \ModxMcp\Tools\ProcessorMutationTool('update_user',$spec))
    ->execute(new SecretAclContext(),array('id'=>21,'fullname'=>'Updated'));
if(!is_array($updated) || $updated['id']!==21
    || $updated['username']!=='safe'
    || $updated['password']!=='[REDACTED]'
    || $updated['profile']['api_key']!=='[REDACTED]'
    || strpos(json_encode($updated),'secret-acl-')!==false)
    throw new \RuntimeException('ACL_UPDATE_USER_RESULT_LEAK');
echo 'GENERIC_PROCESSOR_SECRET_REDACTION_OK checks=7'.PHP_EOL;
}
