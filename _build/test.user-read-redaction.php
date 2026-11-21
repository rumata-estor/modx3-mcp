<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Проверяет скрытие учётных данных при чтении пользователя через MCP.
 * EN: Tests credential redaction in single-user MCP read responses.
 */
namespace ModxMcp\Tools {
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/ProcessorSupport.php';
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/AclProcessorSupport.php';
}
namespace {
class ModxMCPClientException extends \RuntimeException {}
class UserReadFakeResponse {
    public function isError(){return false;}
    public function getResponse(){
        return json_encode(array('success'=>true,'object'=>array(
            'id'=>42,'username'=>'test_user',
            'password'=>'SECRET_HASH','salt'=>'SECRET_SALT',
            'cachepwd'=>'CACHED_PASSWORD','remote_key'=>'SESSION_TOKEN',
            'email'=>'user@example.invalid'
        )));
    }
    public function getObject(){return array();}
}
class UserReadFakePlatform {
    public function runProcessor($modx,$processor,$properties){
        if($processor!=='security/user/get')throw new \RuntimeException('Bad fake processor');
        return new UserReadFakeResponse();
    }
}
class UserReadFakeLexicon {public function load(){}}
class UserReadFakeModx {
    public $lexicon;
    public function __construct(){$this->lexicon=new UserReadFakeLexicon();}
}
class UserReadFakeContext {
    private $modx;private $platform;
    public function __construct(){
        $this->modx=new UserReadFakeModx();
        $this->platform=new UserReadFakePlatform();
    }
    public function modx(){return $this->modx;}
    public function platform(){return $this->platform;}
}
$result=\ModxMcp\Tools\AclProcessorSupport::run(
    new UserReadFakeContext(),'security/user/get',array('id'=>42),false
);
if (!is_array($result) || (int)$result['id']!==42
    || $result['username']!=='test_user'
    || $result['email']!=='user@example.invalid') {
    throw new \RuntimeException('USER_READ_REDACTION_DESTROYED_PUBLIC_FIELDS');
}
foreach (array('password','salt','cachepwd','remote_key') as $field) {
    if (array_key_exists($field,$result)) {
        throw new \RuntimeException('USER_READ_CREDENTIAL_LEAK: '.$field);
    }
}
echo 'USER_GET_CREDENTIAL_REDACTION_OK checks=7'.PHP_EOL;
}
