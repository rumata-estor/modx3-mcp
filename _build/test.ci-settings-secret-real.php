<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Реальный MODX 2/3 + ClientConfig: секреты не выдаются через MCP.
 * EN: Disposable real MODX 2/3 and ClientConfig secret readback tests.
 */
if (PHP_SAPI !== 'cli' || getenv('MODXMCP_DISPOSABLE_CI') !== '1'
    || strpos((string)getenv('MODX_CONFIG_CORE'), '/home/runner/') !== 0
    || !is_file((string)getenv('MODX_CONFIG_CORE'))
    || strpos((string)getenv('RUNNER_TEMP'), '/home/runner/') !== 0) exit(3);
require getenv('MODX_CONFIG_CORE');
$is2=!is_file(MODX_CORE_PATH.'src/Revolution/modX.php');
if($is2){require_once MODX_CORE_PATH.'model/modx/modx.class.php';$modx=new modX();}
else{require_once MODX_CORE_PATH.'vendor/autoload.php';
    $modx=\MODX\Revolution\modX::getInstance();}
$modx->initialize('mgr');
$modx->setOption('modxmcp.disabled_groups','');
$modx->setOption('clientconfig.context_aware',true);
$modx->setOption('clientconfig.clear_cache',false);
require MODX_CORE_PATH.'components/modxmcp/model/modxmcp.class.php';
require __DIR__.'/test.cas-mcp-helper.php';
$api=new modxMCP($modx);
$checks=0;
function realSettingRpc($action,array $data=array()){
    global $api,$modx;
    $modx->setOption('modxmcp.disabled_groups','');
    $modx->setOption('clientconfig.context_aware',true);
    $modx->setOption('clientconfig.clear_cache',false);
    return disposableCasRpc($api,$action,$data);
}
function realSettingCheck($ok,$reason){
    global $checks;
    if(!$ok)throw new RuntimeException('REAL_SETTINGS_SECRECY_FAIL '.$reason);
    $checks++;
}
function realSettingGuard(){
    $state=realSettingRpc('get_site_state');
    return array('observed_site_revision'=>(int)$state['site_revision'],
        'strict_site_revision'=>true,'read_set'=>array());
}
$tag=bin2hex(random_bytes(6));
$contextSecret='ci_access_token_'.$tag;
$contextOrdinary='ci_plain_label_'.$tag;
$addonSecret='ciauth_token_'.$tag;
$addonOrdinary='ciplainlabel'.$tag;
$createdContext=array();
$createdAddon=array();
$secretValue='CI_FAKE_ACCESS_TOKEN_VALUE_'.$tag;
try {
    // RU: Все изменения выполняются только штатным MODX MCP на одноразовой базе.
    // EN: All fixture writes use the guarded MCP dispatcher in disposable CI.
    foreach(array($contextSecret=>$secretValue,$contextOrdinary=>'VISIBLE_LABEL') as $key=>$value){
        realSettingRpc('create_context_setting',array(
            'context_key'=>'web','key'=>$key,'value'=>$value,
            '_runtime_preconditions'=>realSettingGuard()
        ));
        $createdContext[]=$key;
    }
    $secretRead=realSettingRpc('get_context_setting',array(
        'context_key'=>'web','key'=>$contextSecret
    ));
    realSettingCheck(is_array($secretRead)
        && ($secretRead['value']??null)==='[REDACTED]',
        'real context get hides token');
    $ordinary=realSettingRpc('get_context_setting',array(
        'context_key'=>'web','key'=>$contextOrdinary
    ));
    realSettingCheck(($ordinary['value']??null)==='VISIBLE_LABEL',
        'real context get preserves ordinary setting');
    $listed=realSettingRpc('list_context_settings',array(
        'context_key'=>'web','limit'=>500
    ));
    realSettingCheck(is_array($listed) && isset($listed['results'])
        && is_array($listed['results']),'context list format');
    $found=array();
    foreach($listed['results'] as $row){
        if(is_array($row) && isset($row['key']))$found[$row['key']]=$row['value']??null;
    }
    realSettingCheck(isset($found[$contextSecret])
        && $found[$contextSecret]==='[REDACTED]',
        'real context list hides token');
    realSettingCheck(isset($found[$contextOrdinary])
        && $found[$contextOrdinary]==='VISIBLE_LABEL',
        'real context list preserves ordinary setting');

    // RU: ClientConfig 2.5.0 уже устанавливается в этом же disposable CI job.
    // EN: The same disposable CI job has staged pinned ClientConfig 2.5.0.
    $model=MODX_CORE_PATH.'components/clientconfig/model/';
    realSettingCheck(is_file($model.'clientconfig/clientconfig.class.php'),
        'pinned addon model is present');
    realSettingCheck($modx->addPackage('clientconfig',$model),
        'addon model loaded');
    foreach(array(
        $addonSecret=>array('value'=>$secretValue,'default'=>$secretValue,
            'context_values'=>array('web'=>$secretValue,'mgr'=>$secretValue)),
        $addonOrdinary=>array('value'=>'VISIBLE_LABEL','default'=>'VISIBLE_DEFAULT',
            'context_values'=>array('web'=>'VISIBLE_WEB'))
    ) as $key=>$payload){
        $request=array_merge(array('key'=>$key,'label'=>$key,'xtype'=>'textfield'),$payload);
        $request['_runtime_preconditions']=realSettingGuard();
        $response=realSettingRpc('clientconfig_create_setting',$request);
        $createdAddon[]=$key;
        $expected=$key===$addonSecret?'[REDACTED]':'VISIBLE_LABEL';
        realSettingCheck(($response['value']??null)===$expected,
            'addon create hides or preserves returned value');
        $get=realSettingRpc('clientconfig_get_setting',array('key'=>$key));
        realSettingCheck(($get['value']??null)===$expected,
            'addon get hides or preserves value');
        realSettingCheck(($get['default']??null)===
            ($key===$addonSecret?'[REDACTED]':'VISIBLE_DEFAULT'),
            'addon get hides or preserves default');
        realSettingCheck(($get['context_values']['web']??null)===
            ($key===$addonSecret?'[REDACTED]':'VISIBLE_WEB'),
            'addon get hides or preserves context');
        if($key===$addonSecret) {
            realSettingCheck(($get['context_values']['mgr']??null)==='[REDACTED]',
                'addon second context masked');
            $updated=realSettingRpc('clientconfig_update_setting',array(
                'key'=>$key,'value'=>'CI_FAKE_CHANGED_'.$tag,
                '_runtime_preconditions'=>realSettingGuard()
            ));
            realSettingCheck(($updated['value']??null)==='[REDACTED]',
                'addon update response masked');
        }
    }
    $list=realSettingRpc('clientconfig_list_settings',array('query'=>'ci'));
    $seen=array();
    foreach(($list['results']??array()) as $row){
        if(isset($row['key']))$seen[$row['key']]=$row;
    }
    realSettingCheck(isset($seen[$addonSecret])
        && ($seen[$addonSecret]['value']??null)==='[REDACTED]'
        && ($seen[$addonSecret]['context_values']['web']??null)==='[REDACTED]',
        'addon list masks global and context');
    realSettingCheck(isset($seen[$addonOrdinary])
        && ($seen[$addonOrdinary]['value']??null)==='VISIBLE_LABEL',
        'addon list ordinary value preserved');
    // RU: Проверяем, что маскировка ответа не уничтожила секрет в БД.
    // EN: Output masking must never corrupt the persisted underlying value.
    $raw=$modx->getObject('cgSetting',array('key'=>$addonSecret));
    realSettingCheck($raw && $raw->get('value')==='CI_FAKE_CHANGED_'.$tag,
        'secret persisted intact after redacted readback');
    echo 'REAL_CONTEXT_CLIENTCONFIG_SECRECY_MODX'.($is2?'2':'3')
        .'_OK checks='.$checks.PHP_EOL;
} finally {
    foreach($createdAddon as $key){
        try{realSettingRpc('clientconfig_delete_setting',array(
            'key'=>$key,'_runtime_preconditions'=>realSettingGuard()
        ));}catch(Throwable $ignored){}
    }
    foreach($createdContext as $key){
        try{realSettingRpc('delete_context_setting',array(
            'context_key'=>'web','key'=>$key,'_runtime_preconditions'=>realSettingGuard()
        ));}catch(Throwable $ignored){}
    }
}
