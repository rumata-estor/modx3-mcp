<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Проверка «token-rotation-faults»; тестовый код не запускается на production.
 * EN: «token-rotation-faults» regression test; not executed in production.
 */
/** Offline token-rotation fault injection. No real MODX, DB or site mutations. */
namespace ModxMcp\Tools {
    interface ToolInterface {
        public function name();public function group();public function isMutation();
        public function supports($context);public function execute($context,array $data);
    }
    class ElementMutationSupport {
        public static $calls=0;
        public static function refreshCache($context) {self::$calls++;}
    }
    class AuditSupport {
        public static $calls=0;
        public static function log($context,$action,$type,$details) {self::$calls++;}
    }
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/RegenerateTokenTool.php';
}
namespace {
class ModxMCPClientException extends \RuntimeException {}
class TokenSettingFake {
    public $value='original-secret'; public $saveFails=false; public $saved=null;
    public function fromArray($data,$prefix='',$raw=true,$adapt=true) {}
    public function set($name,$value) {if($name==='value')$this->value=$value;}
    public function save() {if($this->saveFails)return false;$this->saved=$this->value;return true;}
}
class TokenModxFake {
    public $setting;public $reloads=0;
    public function __construct($setting) {$this->setting=$setting;}
    public function getObject($class,$criteria) {return $this->setting;}
    public function newObject($class) {return new TokenSettingFake();}
    public function reloadConfig() {$this->reloads++;}
}
class TokenPlatformFake {public function className($name) {return 'fakeSetting';}}
class TokenContextFake {
    private $modx;
    public function __construct($modx) {$this->modx=$modx;}
    public function modx() {return $this->modx;}
    public function platform() {return new TokenPlatformFake();}
}
$tool=new \ModxMcp\Tools\RegenerateTokenTool();
$setting=new TokenSettingFake();$setting->saveFails=true;
$modx=new TokenModxFake($setting);$context=new TokenContextFake($modx);
try {
    $tool->execute($context,array());
    throw new \RuntimeException('TOKEN_FAULT_FAIL: rejected save was successful');
} catch (ModxMCPClientException $e) {
    if (strpos($e->getMessage(),'could not save')===false)throw $e;
}
if($setting->saved!==null || $modx->reloads!==0
    || \ModxMcp\Tools\ElementMutationSupport::$calls!==0
    || \ModxMcp\Tools\AuditSupport::$calls!==0) {
    throw new \RuntimeException('TOKEN_FAULT_FAIL: side effect on rejected save');
}
$setting=new TokenSettingFake();$modx=new TokenModxFake($setting);
$result=$tool->execute(new TokenContextFake($modx),array());
if(!isset($result['token']) || !preg_match('/\A[a-f0-9]{64}\z/D',$result['token'])
    || $setting->saved!==$result['token'] || $modx->reloads!==1
    || \ModxMcp\Tools\ElementMutationSupport::$calls!==1
    || \ModxMcp\Tools\AuditSupport::$calls!==1) {
    throw new \RuntimeException('TOKEN_FAULT_FAIL: positive mutation not complete');
}
echo 'TOKEN_ROTATION_FAULT_MATRIX_OK checks=2'.PHP_EOL;
}
