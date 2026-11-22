<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Контексты создаются без неявного anonymous Load Only, только disposable CI.
 * EN: Context creation cannot silently grant anonymous Load Only in disposable CI.
 */
if (PHP_SAPI !== 'cli' || getenv('MODXMCP_DISPOSABLE_CI') !== '1'
    || strpos((string)getenv('MODX_CONFIG_CORE'), '/home/runner/') !== 0
    || !is_file((string)getenv('MODX_CONFIG_CORE'))) {
    fwrite(STDERR, "Refusing context ACL defaults test outside disposable GitHub CI.\n");
    exit(3);
}
require_once getenv('MODX_CONFIG_CORE');
$is2 = !is_file(MODX_CORE_PATH . 'src/Revolution/modX.php');
if ($is2) {
    require_once MODX_CORE_PATH . 'model/modx/modx.class.php';
    $modx=new modX();
} else {
    require_once MODX_CORE_PATH . 'vendor/autoload.php';
    $modx=\MODX\Revolution\modX::getInstance();
}
$modx->initialize('mgr');
require_once MODX_CORE_PATH.'components/modxmcp/model/modxmcp.class.php';
require_once __DIR__.'/test.cas-mcp-helper.php';
$api=new modxMCP($modx);
$modx->setOption('modxmcp.disabled_groups','');
$fixture=bin2hex(random_bytes(5));
// RU: Ключ контекста должен быть буквенно-цифровым на MODX 2/3.
// EN: Native MODX 2/3 context key validation requires alphanumeric keys.
$key='mcpacl'.$fixture;
$deniedKey='mcpdenyacl'.$fixture;
$created=false;
$checks=0;
function acdRpc($action,array $data=array()){
    global $api,$modx;
    // RU: refresh(context_settings) перечитывает системные опции MODX и
    // RU: сбрасывает только временное включение группы в этом PHP-процессе.
    // RU: Перед каждым вызовом возвращаем разрешение в памяти disposable CI.
    // EN: Context-cache refresh reloads MODX options, undoing the CI-only
    // EN: in-memory capability toggle; restore it without changing the DB.
    $modx->setOption('modxmcp.disabled_groups','');
    return disposableCasRpc($api,$action,$data);
}
function acdCheck($ok,$label){
    global $checks;
    if (!$ok) throw new RuntimeException('ACL_CONTEXT_DEFAULTS_FAILED: '.$label);
    $checks++;
}
// RU: Для проверки replay используем исходную, а не новую ревизию.
// EN: Preserve the observed snapshot across the original call and replay.
function acdGuard(){
    $state=acdRpc('get_site_state');
    return array('observed_site_revision'=>(int)$state['site_revision'],
        'strict_site_revision'=>true,'read_set'=>array());
}
function acdStaleReplay($action,array $request,$label){
    $denied=false;
    try {acdRpc($action,$request);}
    catch(ModxMCPClientException $e){
        $denied=strpos($e->getMessage(),'STALE_STATE')!==false;
    }
    acdCheck($denied,$label.' stale CAS replay denied');
}
function acdContextRules($key){
    $result=acdRpc('list_context_access',array(
        'usergroup'=>0,'context'=>$key,'limit'=>500
    ));
    acdCheck(isset($result['results'])&&is_array($result['results']),
        'read anonymous context access via MCP');
    return array_values(array_filter($result['results'],function($row)use($key){
        return is_array($row) && (string)($row['target']??'')===$key
            && (int)($row['principal']??-1)===0;
    }));
}
try{
    // RU: Прямое намерение включить anonymous не должно запускать процессор.
    // EN: Explicit anonymous access is denied before context creation.
    $denied=false;
    try {
        acdRpc('create_context',array(
            'key'=>$deniedKey,'name'=>'Denied anonymous '.$fixture,
            'enableAnonymous'=>true
        ));
    } catch(ModxMCPClientException $e) {
        $denied=strpos($e->getMessage(),'AUTH_DENIED:')===0;
    }
    acdCheck($denied,'explicit anonymous access rejected');
    $contexts=acdRpc('list_contexts',array('limit'=>500));
    acdCheck(isset($contexts['results'])&&is_array($contexts['results']),
        'context listing available after denial');
    foreach($contexts['results'] as $context){
        acdCheck(!is_array($context) || ($context['key']??'')!==$deniedKey,
            'rejected creation left no context');
    }

    // RU: Обычное создание неявно не должно давать Load Only.
    // EN: Default context creation must not grant anonymous Load Only.
    $createGuard=acdGuard();
    $createRequest=array('key'=>$key,'name'=>'Temporary context '.$fixture,
        '_runtime_preconditions'=>$createGuard);
    $response=acdRpc('create_context',$createRequest);
    $created=true;
    acdStaleReplay('create_context',$createRequest,'create context');
    acdCheck(is_array($response),'MCP context create response');
    acdCheck(count(acdContextRules($key))===0,
        'no anonymous access granted by default');
    $contexts=acdRpc('list_contexts',array('limit'=>500));
    $found=false;
    foreach(($contexts['results']??array()) as $context){
        if(is_array($context)&&($context['key']??'')===$key)$found=true;
    }
    acdCheck($found,'new context exists without anonymous access');
    $contextRead=acdRpc('get_context',array('key'=>$key));
    acdCheck(is_array($contextRead),'created context read through MCP');
    echo 'CAS_CONTEXT_CREATE_READBACK_STALE_REPLAY_OK'.PHP_EOL;

    // RU: Изменение имени контекста не должно включать anonymous ACL.
    // EN: Context description/name update must not grant anonymous access.
    $contextUpdate=array('key'=>$key,'name'=>'Updated context '.$fixture,
        '_runtime_preconditions'=>acdGuard());
    acdRpc('update_context',$contextUpdate);
    $contextAfter=acdRpc('get_context',array('key'=>$key));
    acdCheck(is_array($contextAfter),'updated context read through MCP');
    acdStaleReplay('update_context',$contextUpdate,'update context');
    acdCheck(count(acdContextRules($key))===0,
        'context update did not create anonymous ACL');
    echo 'CAS_CONTEXT_UPDATE_READBACK_STALE_REPLAY_OK'.PHP_EOL;
    // RU: PR #54, @AgelxNash: жизненный цикл настройки через MCP,
    // RU: refresh() должен получать явный список затронутых контекстов.
    // EN: PR #54, @AgelxNash: exercise context setting lifecycle and
    // EN: explicit cache invalidation on real disposable MODX 2/3.
    $settingKey='mcp_cache_'.$fixture;
    $settingCreate=array(
        'context_key'=>$key,'key'=>$settingKey,'value'=>'alpha',
        'namespace'=>'core','area'=>'default',
        '_runtime_preconditions'=>acdGuard(),
    );
    acdRpc('create_context_setting',$settingCreate);
    acdStaleReplay('create_context_setting',$settingCreate,'create context setting');
    $settingRead=acdRpc('get_context_setting',array(
        'context_key'=>$key,'key'=>$settingKey
    ));
    acdCheck(is_array($settingRead) && ($settingRead['value']??null)==='alpha',
        'context setting persisted after creation');
    echo 'CAS_CONTEXT_SETTING_CREATE_READBACK_STALE_REPLAY_OK'.PHP_EOL;
    $settingUpdate=array('context_key'=>$key,'key'=>$settingKey,
        'value'=>'beta','_runtime_preconditions'=>acdGuard());
    acdRpc('update_context_setting',$settingUpdate);
    acdStaleReplay('update_context_setting',$settingUpdate,'update context setting');
    $settingRead=acdRpc('get_context_setting',array(
        'context_key'=>$key,'key'=>$settingKey
    ));
    acdCheck(is_array($settingRead) && ($settingRead['value']??null)==='beta',
        'context setting updated via MCP');
    echo 'CAS_CONTEXT_SETTING_UPDATE_READBACK_STALE_REPLAY_OK'.PHP_EOL;
    $settingDelete=array('context_key'=>$key,'key'=>$settingKey,
        '_runtime_preconditions'=>acdGuard());
    acdRpc('delete_context_setting',$settingDelete);
    acdStaleReplay('delete_context_setting',$settingDelete,'delete context setting');
    $removed=true;
    try {acdRpc('get_context_setting',array(
        'context_key'=>$key,'key'=>$settingKey
    ));$removed=false;}
    catch(ModxMCPClientException $e) {}
    acdCheck($removed,'context setting removed by MCP');
    echo 'CAS_CONTEXT_SETTING_DELETE_READBACK_STALE_REPLAY_OK'.PHP_EOL;
    // RU: Удаляем контекст без анонимных ACL и проверяем stale replay.
    // EN: Delete the isolated context and reject its stale replay.
    $contextDelete=array('key'=>$key,'_runtime_preconditions'=>acdGuard());
    acdRpc('delete_context',$contextDelete);
    $created=false;
    acdStaleReplay('delete_context',$contextDelete,'delete context');
    $contextsAfter=acdRpc('list_contexts',array('limit'=>500));
    foreach(($contextsAfter['results']??array()) as $row) {
        acdCheck(!is_array($row) || ($row['key']??'')!==$key,
            'deleted context absent from MCP context list');
    }
    echo 'CAS_CONTEXT_DELETE_READBACK_STALE_REPLAY_OK'.PHP_EOL;
    echo 'ACL_CONTEXT_DEFAULT_DENY_ANONYMOUS_OK platform='
        .($is2?'modx2':'modx3').' checks='.$checks.PHP_EOL;
} finally {
    // RU: Удаляем только наш disposable контекст через MCP.
    // EN: Remove solely the disposable context via MCP.
    if($created) acdRpc('delete_context',array('key'=>$key));
}
