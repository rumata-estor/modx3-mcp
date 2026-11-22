<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Adversarial-проверка обхода доверенных настроек через MCP в disposable CI.
 * EN: Disposable adversarial authorization-bypass attempts against trust settings.
 */
if(PHP_SAPI!=='cli' || getenv('MODXMCP_DISPOSABLE_CI')!=='1'
    || strpos((string)getenv('MODX_CONFIG_CORE'),'/home/runner/')!==0
    || !is_file((string)getenv('MODX_CONFIG_CORE')))exit(3);
require getenv('MODX_CONFIG_CORE');
$is2=!is_file(MODX_CORE_PATH.'src/Revolution/modX.php');
if($is2){require_once MODX_CORE_PATH.'model/modx/modx.class.php';$modx=new modX();}
else{require_once MODX_CORE_PATH.'vendor/autoload.php';
    $modx=\MODX\Revolution\modX::getInstance();}
$modx->initialize('mgr');
$modx->setOption('modxmcp.disabled_groups','');
require MODX_CORE_PATH.'components/modxmcp/model/modxmcp.class.php';
require __DIR__.'/test.cas-mcp-helper.php';
$api=new modxMCP($modx);
$checks=0;$tag=bin2hex(random_bytes(5));
$ordinary='ci_safe_'.$tag;
$bad='modxmcp.external_authorization_required';
$before=$modx->getObject(
    $is2?'modSystemSetting':'MODX\\Revolution\\modSystemSetting',
    array('key'=>$bad)
);
if(!$before)throw new RuntimeException('Missing protected setting fixture.');
$protectedValue=(string)$before->get('value');
function authBypassRpc($action,array $request=array()){
    global $modx,$api;
    $modx->setOption('modxmcp.disabled_groups','');
    return disposableCasRpc($api,$action,$request);
}
function authBypassCheck($yes,$label){
    global $checks;
    if(!$yes)throw new RuntimeException('AUTH_BYPASS_E2E_FAIL '.$label);
    $checks++;
}
function authBypassGuard(){
    $state=authBypassRpc('get_site_state');
    return array('observed_site_revision'=>(int)$state['site_revision'],
        'strict_site_revision'=>true,'read_set'=>array());
}
function authBypassSettingValue($key){
    global $modx,$is2;
    $row=$modx->getObject(
        $is2?'modSystemSetting':'MODX\\Revolution\\modSystemSetting',
        array('key'=>$key)
    );
    return $row?(string)$row->get('value'):null;
}
$madeOrdinary=false;
try{
    $create=authBypassRpc('create_system_setting',array(
        'key'=>$ordinary,'value'=>'SAFE','_runtime_preconditions'=>authBypassGuard()
    ));
    $madeOrdinary=true;
    authBypassCheck(authBypassSettingValue($ordinary)==='SAFE',
        'ordinary control setting created');

    // RU: Проверяем формат, регистр, обновление/удаление и переименование.
    // EN: Exercise mixed-case, whitespace, rename and delete aliases.
    $cases=array(
        array('create_system_setting',array('key'=>$bad,'value'=>'0')),
        array('create_system_setting',array('key'=>' MODXMCP.enabled ','value'=>'0')),
        array('create_system_setting',array('key'=>'ModXMCP.api_token','value'=>'FAKE')),
        array('update_system_setting',array('key'=>$bad,'value'=>'0')),
        array('update_system_setting',array('key'=>' MODXMCP.enabled ','value'=>'0')),
        array('delete_system_setting',array('key'=>$bad)),
        array('delete_system_setting',array('key'=>' MODXMCP.enabled ')),
        array('update_system_setting',array(
            'key'=>$ordinary,'new_key'=>$bad,'value'=>'0'
        )),
        array('update_system_setting',array(
            'key'=>$ordinary,'new_key'=>' MODXMCP.api_token ','value'=>'FAKE'
        )),
        array('create_context_setting',array(
            'context_key'=>'web','key'=>$bad,'value'=>'0'
        )),
        array('create_context_setting',array(
            'context_key'=>'web','name'=>' MODXMCP.enabled ','value'=>'0'
        )),
        array('update_context_setting',array(
            'context_key'=>'web','key'=>$bad,'value'=>'0'
        )),
        array('delete_context_setting',array(
            'context_key'=>'web','key'=>$bad
        )),
        array('create_context',array(
            'key'=>'ci_ctx_'.$tag,'settings'=>array($bad=>'0')
        )),
        array('update_context',array(
            'key'=>'web','settings'=>json_encode(array(
                array('key'=>$bad,'value'=>'0')
            ))
        )),
        array('update_context',array(
            'key'=>'web','context_settings'=>array($bad=>'0')
        )),
        array('run_processor',array('processor'=>'system/settings/update',
            'properties'=>array('key'=>$bad,'value'=>'0'))),
        array('run_processor',array('processor'=>'system/setting/update',
            'properties'=>array('key'=>$bad,'value'=>'0'))),
        array('run_processor',array('processor'=>'context/setting/update',
            'properties'=>array('key'=>$bad,'value'=>'0','fk'=>'web'))),
        array('run_processor',array('processor'=>'context/update',
            'properties'=>array('key'=>'web','settings'=>array($bad=>'0')))),
        array('run_processor',array('processor'=>'security/user/setting/update',
            'properties'=>array('key'=>$bad,'value'=>'0'))),
        array('run_processor',array('processor'=>'resource/getlist',
            'processors_path'=>'/core/model/modx/processors/system/settings/',
            'properties'=>array('key'=>$bad,'value'=>'0'))),
    );
    foreach($cases as $index=>$case){
        $start=authBypassRpc('get_site_state');
        $request=$case[1];
        // RU: Каждый probe получает свежий CAS, чтобы отказ был из авторизации.
        // EN: Fresh CAS ensures denial is from security gates, not stale state.
        $request['_runtime_preconditions']=authBypassGuard();
        $denied=false;
        try{authBypassRpc($case[0],$request);}
        catch(ModxMCPClientException $e){$denied=true;}
        authBypassCheck($denied,'probe '.($index+1).' rejected');
        authBypassCheck(authBypassSettingValue($bad)===$protectedValue,
            'probe '.($index+1).' protected value unchanged');
        $after=authBypassRpc('get_site_state');
        authBypassCheck((int)$after['site_revision']===(int)$start['site_revision'],
            'probe '.($index+1).' did not consume CAS revision');
    }
    authBypassCheck(authBypassSettingValue($ordinary)==='SAFE',
        'original ordinary setting unchanged by protected rename');
    $contextSetting=null;
    try{$contextSetting=authBypassRpc('get_context_setting',array(
        'context_key'=>'web','key'=>$bad
    ));}catch(ModxMCPClientException $ignored){}
    authBypassCheck($contextSetting===null,
        'no context shadow of protected setting');
    echo 'AUTH_BYPASS_SETTINGS_MODX'.($is2?'2':'3')
        .'_OK checks='.$checks.' probes='.count($cases).PHP_EOL;
} finally {
    if($madeOrdinary){
        try{authBypassRpc('delete_system_setting',array(
            'key'=>$ordinary,'_runtime_preconditions'=>authBypassGuard()
        ));}catch(Throwable $ignored){}
    }
}
