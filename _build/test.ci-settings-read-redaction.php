<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Одноразовый MODX: защищённые ключи нельзя раскрыть через MCP.
 * EN: Disposable MODX proves credential setting reads are redacted.
 */
if (PHP_SAPI !== 'cli' || getenv('MODXMCP_DISPOSABLE_CI') !== '1'
    || strpos((string)getenv('MODX_CONFIG_CORE'), '/home/runner/') !== 0
    || !is_file((string)getenv('MODX_CONFIG_CORE'))) exit(3);
require getenv('MODX_CONFIG_CORE');
$is2 = !is_file(MODX_CORE_PATH.'src/Revolution/modX.php');
if($is2){require_once MODX_CORE_PATH.'model/modx/modx.class.php';$modx=new modX();}
else{require_once MODX_CORE_PATH.'vendor/autoload.php';
    $modx=\MODX\Revolution\modX::getInstance();}
$modx->initialize('mgr');
require MODX_CORE_PATH.'components/modxmcp/model/modxmcp.class.php';
$api=new modxMCP($modx);
$class=$is2?'modSystemSetting':'MODX\\Revolution\\modSystemSetting';
$id=bin2hex(random_bytes(7));
$key='ci_mcp_provider_access_token_'.$id;
$plainKey='ci_mcp_setting_label_'.$id;
$checks=0;
function settingCiCheck($yes,$what){
    global $checks;
    if(!$yes) throw new RuntimeException('CI_SETTING_REDACTION_FAIL '.$what);
    $checks++;
}
$created=array();
try{
    // RU: Только фикстура на временном MODX; не используется MCP для записи
    // RU: системных ключей безопасности, как и в реальном защищённом контуре.
    // EN: Install test fixture via disposable Manager model, never bypass MCP
    // EN: to alter a real site.
    foreach(array($key=>'CI_ONLY_SECRET_DO_NOT_LEAK',$plainKey=>'VISIBLE_VALUE') as $k=>$v){
        $setting=$modx->newObject($class);
        $setting->set('key',$k);
        $setting->set('value',$v);
        $setting->set('namespace','modxmcp');
        $setting->set('xtype','textfield');
        settingCiCheck($setting->save(),'fixture saved');
        $created[]=$k;
    }
    $secret=$api->processRequest('get_system_setting','',array('key'=>$key));
    settingCiCheck(($secret['value']??'')==='[REDACTED]','get secret redacted');
    $plain=$api->processRequest('get_system_setting','',array('key'=>$plainKey));
    settingCiCheck(($plain['value']??'')==='VISIBLE_VALUE','get ordinary value');
    $list=$api->processRequest('list_system_settings','',array('namespace'=>'modxmcp'));
    $seen=array();
    foreach($list as $row) {
        if(is_array($row) && isset($row['key']))$seen[$row['key']]=$row['value']??null;
    }
    settingCiCheck(isset($seen[$key]) && $seen[$key]==='[REDACTED]',
        'list secret redacted');
    settingCiCheck(isset($seen[$plainKey]) && $seen[$plainKey]==='VISIBLE_VALUE',
        'list ordinary value');
    // RU: Даже при непосредственной попытке изменить trust anchor через MCP
    // RU: операция запрещается до изменения значения.
    // EN: The protected system setting cannot be modified through MCP.
    $denied=false;
    try {
        $state=$api->processRequest('get_site_state','',array());
        $api->processRequest('update_system_setting','',array(
            'key'=>'modxmcp.external_authorization_required','value'=>'0',
            '_runtime_preconditions'=>array(
                'strict_site_revision'=>true,
                'observed_site_revision'=>(int)$state['site_revision'],
                'read_set'=>array()
            )
        ));
    } catch(ModxMCPClientException $e) {
        $denied=true;
    }
    settingCiCheck($denied,'trust anchor mutation denied');
    echo 'CI_SYSTEM_SETTINGS_READ_REDACTION_MODX'.($is2?'2':'3')
        .'_OK checks='.$checks.PHP_EOL;
} finally {
    foreach($created as $k) {
        $setting=$modx->getObject($class,array('key'=>$k));
        if($setting)$setting->remove();
    }
}
