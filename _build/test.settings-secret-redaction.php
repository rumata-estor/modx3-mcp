<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Проверка того, что чтение настроек не раскрывает секреты.
 * EN: Regression tests for credential redaction in setting readbacks.
 */
namespace ModxMcp\Tools {
    interface ToolInterface {
        public function name(); public function group(); public function isMutation();
        public function supports($context); public function execute($context,array $data);
    }
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/SettingSecretSupport.php';
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/SystemSettingListTool.php';
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/SystemSettingGetTool.php';
}
namespace {
class RedactionSetting {
    private $key;private $value;
    public function __construct($key,$value){$this->key=$key;$this->value=$value;}
    public function get($key) {
        if($key==='key')return $this->key;
        if($key==='value')return $this->value;
        return null;
    }
}
$checks=0;
foreach(array(
    array('modxmcp.api_token','sensitive','[REDACTED]'),
    array('MODXMCP.API_TOKEN','sensitive','[REDACTED]'),
    array('smtp_password','sensitive','[REDACTED]'),
    array('payment.client_secret','sensitive','[REDACTED]'),
    array('external.refresh_token','sensitive','[REDACTED]'),
    array('modxmcp.permit_public_key','public-signing-key','public-signing-key'),
    array('modxmcp.processor_access_mode','disabled','disabled'),
    array('modxmcp.trust_proxy_https','0','0'),
) as $case) {
    $row=\ModxMcp\Tools\SystemSettingListTool::normalize(
        new RedactionSetting($case[0],$case[1])
    );
    if($row['value']!==$case[2] || $row['key']!==$case[0]) {
        throw new \RuntimeException('CREDENTIAL_REDACTION_FAILED: '.$case[0]);
    }
    $checks++;
}
echo 'MCP_SETTINGS_CREDENTIAL_REDACTION_OK checks='.$checks.PHP_EOL;
}
