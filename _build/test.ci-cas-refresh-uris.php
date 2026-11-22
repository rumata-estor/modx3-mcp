<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Проверка refresh_uris только в одноразовом тестовом MODX.
 * EN: Refresh URIs CAS test on an isolated disposable MODX instance only.
 */
if (PHP_SAPI !== 'cli' || getenv('MODXMCP_DISPOSABLE_CI') !== '1'
    || strpos((string)getenv('MODX_CONFIG_CORE'), '/home/runner/') !== 0
    || !is_file((string)getenv('MODX_CONFIG_CORE'))) exit(3);
require getenv('MODX_CONFIG_CORE');
$is2=!is_file(MODX_CORE_PATH.'src/Revolution/modX.php');
if ($is2) {require MODX_CORE_PATH.'model/modx/modx.class.php';$modx=new modX();}
else {require MODX_CORE_PATH.'vendor/autoload.php';$modx=\MODX\Revolution\modX::getInstance();}
$modx->initialize('mgr');
$modx->setOption('modxmcp.disabled_groups','');
require MODX_CORE_PATH.'components/modxmcp/model/modxmcp.class.php';
require __DIR__.'/test.cas-mcp-helper.php';
$api=new modxMCP($modx);$checks=0;
function uriRpc($action,array $args=array()){
    global $api;return disposableCasRpc($api,$action,$args);
}
function uriCheck($good,$label){
    global $checks;if(!$good)throw new RuntimeException('CAS_REFRESH_URI_FAILED '.$label);
    $checks++;
}
$fixture=bin2hex(random_bytes(5));$id=0;
try{
    // RU: URI своего неопубликованного ресурса не должен испортиться.
    // EN: Preserve only the URI of an unpublished temporary resource.
    $created=uriRpc('create_element',array('type'=>'resource',
        'name'=>'MCP_URI_Disposable_'.$fixture,
        'alias'=>'mcp-uri-'.$fixture,'published'=>0,'hidemenu'=>1,
        'template'=>0,'parent'=>0,'content'=>'test only'));
    $id=(int)($created['id']??0);
    uriCheck($id>0,'fixture created');
    $before=uriRpc('get_element',array('type'=>'resource','id'=>$id));
    uriCheck(empty($before['published']),'resource unpublished');
    $site=uriRpc('get_site_state');
    $request=array('_runtime_preconditions'=>array(
        'observed_site_revision'=>(int)$site['site_revision'],
        'strict_site_revision'=>true,'read_set'=>array()));
    $result=uriRpc('refresh_uris',$request);
    uriCheck(!empty($result['refreshed']),'MODX URI refresh completed');
    $after=uriRpc('get_element',array('type'=>'resource','id'=>$id));
    uriCheck((string)($after['uri']??'')===(string)($before['uri']??''),
        'disposable resource URI stable');
    uriCheck(empty($after['published']),'URI refresh did not publish resource');
    $preReplay=uriRpc('get_site_state');
    $denied=false;
    try{uriRpc('refresh_uris',$request);}
    catch(ModxMCPClientException $e){
        $denied=strpos($e->getMessage(),'STALE_STATE')!==false;
    }
    uriCheck($denied,'stale URI refresh replay denied');
    $postReplay=uriRpc('get_site_state');
    uriCheck((int)$preReplay['site_revision']===(int)$postReplay['site_revision'],
        'stale refresh consumed no revision');
    echo 'CAS_REFRESH_URIS_READBACK_STALE_REPLAY_OK'.PHP_EOL;
}finally{
    if($id>0)uriRpc('delete_element',array('type'=>'resource',
        'id'=>$id,'dry_run'=>false));
}
echo 'CAS_REFRESH_URIS_OK checks='.$checks
    .' platform='.($is2?'modx2':'modx3').PHP_EOL;
