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
$key='mcp_acl_'.$fixture;
$deniedKey='mcp_deny_acl_'.$fixture;
$created=false;
$checks=0;
function acdRpc($action,array $data=array()){
    global $api;
    return disposableCasRpc($api,$action,$data);
}
function acdCheck($ok,$label){
    global $checks;
    if (!$ok) throw new RuntimeException('ACL_CONTEXT_DEFAULTS_FAILED: '.$label);
    $checks++;
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
    $response=acdRpc('create_context',array(
        'key'=>$key,'name'=>'Temporary context '.$fixture
    ));
    $created=true;
    acdCheck(is_array($response),'MCP context create response');
    acdCheck(count(acdContextRules($key))===0,
        'no anonymous access granted by default');
    $contexts=acdRpc('list_contexts',array('limit'=>500));
    $found=false;
    foreach(($contexts['results']??array()) as $context){
        if(is_array($context)&&($context['key']??'')===$key)$found=true;
    }
    acdCheck($found,'new context exists without anonymous access');
    echo 'ACL_CONTEXT_DEFAULT_DENY_ANONYMOUS_OK platform='
        .($is2?'modx2':'modx3').' checks='.$checks.PHP_EOL;
} finally {
    // RU: Удаляем только наш disposable контекст через MCP.
    // EN: Remove solely the disposable context via MCP.
    if($created) acdRpc('delete_context',array('key'=>$key));
}
