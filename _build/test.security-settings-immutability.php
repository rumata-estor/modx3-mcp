<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Проверяет запрет изменения внутренних настроек MCP без установки MODX.
 * EN: Verifies MCP security settings cannot be edited via API without MODX.
 */
namespace ModxMcp\Tools {
    interface ToolInterface {
        public function name(); public function group(); public function isMutation();
        public function supports($context); public function execute($context,array $data);
    }
    class ElementMutationSupport {
        public static $refreshes=0;
        public static function refreshCache($context){self::$refreshes++;}
    }
    class AuditSupport {
        public static $calls=0;
        public static function log($context,$action,$type,$payload){self::$calls++;}
    }
    class SystemSettingListTool {
        public static function normalize($setting){return array('key'=>$setting->get('key'));}
    }
    require_once __DIR__.'/../core/components/modxmcp/src/Security/ProtectedSettingsGuard.php';
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/SystemSettingCreateTool.php';
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/SystemSettingUpdateTool.php';
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/SystemSettingDeleteTool.php';
}
namespace {
class ModxMCPClientException extends \RuntimeException {}
class SettingsFakeSetting {
    public $key;public $saves=0;public $removes=0;public $sets=0;
    public function __construct($key){$this->key=$key;}
    public function get($name){return $name==='key'?$this->key:null;}
    public function set($name,$value){$this->sets++;if($name==='key')$this->key=$value;}
    public function fromArray($data,$prefix='',$raw=true,$adapt=true){$this->sets++;}
    public function save(){$this->saves++;return true;}
    public function remove(){$this->removes++;return true;}
}
class SettingsFakeModx {
    public $row;public $gets=0;public $creates=0;
    public function __construct($row){$this->row=$row;}
    public function getObject($class,$query){$this->gets++;return $this->row;}
    public function newObject($class){$this->creates++;return new SettingsFakeSetting('test');}
}
class SettingsFakePlatform {
    public function className($name){return 'FakeSystemSetting';}
}
class SettingsFakeContext {
    public $modx;
    public function __construct($modx){$this->modx=$modx;}
    public function modx(){return $this->modx;}
    public function platform(){return new SettingsFakePlatform();}
}
function denial($name,$tool,$data,$row) {
    $modx=new SettingsFakeModx($row);
    $context=new SettingsFakeContext($modx);
    $beforeRefresh=\ModxMcp\Tools\ElementMutationSupport::$refreshes;
    $beforeAudit=\ModxMcp\Tools\AuditSupport::$calls;
    try {
        $tool->execute($context,$data);
        throw new \RuntimeException('SECURITY_SETTING_ALLOWED: '.$name);
    } catch (ModxMCPClientException $e) {
        if (strpos($e->getMessage(),'Managing modxmcp.* settings via MCP API is not allowed')===false) {
            throw $e;
        }
    }
    if ($modx->creates!==0 || ($row && ($row->saves!==0 || $row->removes!==0 || $row->sets!==0))
        || $beforeRefresh!==\ModxMcp\Tools\ElementMutationSupport::$refreshes
        || $beforeAudit!==\ModxMcp\Tools\AuditSupport::$calls) {
        throw new \RuntimeException('SECURITY_SETTING_SIDE_EFFECT: '.$name);
    }
}
$create=new \ModxMcp\Tools\SystemSettingCreateTool();
$update=new \ModxMcp\Tools\SystemSettingUpdateTool();
$delete=new \ModxMcp\Tools\SystemSettingDeleteTool();
denial('create_by_key',$create,array('key'=>'modxmcp.allow_run_processor','value'=>'1'),null);
denial('update_by_key',$update,array('key'=>'modxmcp.external_authorization_required','value'=>'0'),
    new SettingsFakeSetting('modxmcp.external_authorization_required'));
denial('update_by_id',$update,array('id'=>100,'value'=>'1'),
    new SettingsFakeSetting('modxmcp.allow_run_processor'));
denial('rename_to_protected',$update,array('id'=>101,'key'=>'modxmcp.processor_access_mode','value'=>'privileged'),
    new SettingsFakeSetting('safe.source_key'));
denial('delete_by_key',$delete,array('key'=>'modxmcp.permit_public_key'),
    new SettingsFakeSetting('modxmcp.permit_public_key'));
denial('delete_by_id',$delete,array('id'=>102),
    new SettingsFakeSetting('modxmcp.api_token'));
// RU: Проверяем пробелы и регистр, которые нормализует MODX.
// EN: Verify MODX key normalization cannot bypass protected settings.
denial('create_spaced_uppercase',$create,
    array('key'=>'  ModxMCP.enabled  ','value'=>'1'),null);
denial('update_renamed_spaced',$update,
    array('id'=>104,'key'=>' MODXMCP.permit_public_key ','value'=>'unsafe'),
    new SettingsFakeSetting('other_key'));
denial('update_stored_uppercase',$update,array('id'=>105,'value'=>'1'),
    new SettingsFakeSetting('ModxMCP.enabled'));
denial('delete_stored_spaced',$delete,array('id'=>106),
    new SettingsFakeSetting('  modxmcp.api_token  '));
echo 'SECURITY_SETTINGS_UNMUTABLE_VIA_MCP_OK checks=10'.PHP_EOL;
}
