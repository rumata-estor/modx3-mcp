<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Отдельное CAS-доказательство дублирования временных элементов без публикации.
 * EN: Disposable chunk/resource duplication with stale CAS replay.
 */
if (PHP_SAPI !== 'cli' || getenv('MODXMCP_DISPOSABLE_CI') !== '1'
    || strpos((string)getenv('MODX_CONFIG_CORE'), '/home/runner/') !== 0
    || !is_file((string)getenv('MODX_CONFIG_CORE'))) {
    fwrite(STDERR,'Refusing ACL evidence tests outside disposable CI.'.PHP_EOL);
    exit(3);
}
require getenv('MODX_CONFIG_CORE');
$is2=!is_file(MODX_CORE_PATH.'src/Revolution/modX.php');
if ($is2) {
    require MODX_CORE_PATH.'model/modx/modx.class.php';
    $modx=new modX();
} else {
    require MODX_CORE_PATH.'vendor/autoload.php';
    $modx=\MODX\Revolution\modX::getInstance();
}
$modx->initialize('mgr');
$modx->setOption('modxmcp.disabled_groups','');
require MODX_CORE_PATH.'components/modxmcp/model/modxmcp.class.php';
require __DIR__.'/test.cas-mcp-helper.php';
$api=new modxMCP($modx);
$checks=0;
function dupRpc($action,array $data=array()) {
    global $api;
    return disposableCasRpc($api,$action,$data);
}
function dupCheck($test,$label) {
    global $checks;
    if(!$test)throw new RuntimeException('CAS_CREATE_EVIDENCE_FAILED '.$label);
    $checks++;
}
$fixture=bin2hex(random_bytes(5));
$created=array();
function dupGuard(){
    $s=dupRpc('get_site_state');
    return array('observed_site_revision'=>(int)$s['site_revision'],
        'strict_site_revision'=>true,'read_set'=>array());
}
function dupReplay($action,array $request){
    $before=dupRpc('get_site_state');$denied=false;
    try{dupRpc($action,$request);}
    catch(ModxMCPClientException $e){
        $denied=strpos($e->getMessage(),'STALE_STATE')!==false;
    }
    dupCheck($denied,$action.' stale request denied');
    $after=dupRpc('get_site_state');
    dupCheck((int)$before['site_revision']===(int)$after['site_revision'],
        $action.' old CAS consumed no revision');
}
try{
    // RU: Только собственный скрытый неопубликованный ресурс.
    // EN: Use only a disposable hidden and unpublished page.
    $source=dupRpc('create_element',array('type'=>'resource',
        'name'=>'MCP_DUP_Source_'.$fixture,'content'=>'RESOURCE_'.$fixture,'template'=>0,'published'=>0,'hidemenu'=>1,'parent'=>0));
    $sourceId=(int)($source['id']??0);
    dupCheck($sourceId>0,'unpublished resource created');
    $created[]=array('type'=>'resource','id'=>$sourceId);
    $name='MCP_DUP_Copy_'.$fixture;
    $request=array('id'=>$sourceId,'name'=>$name,'duplicate_children'=>false,
        '_runtime_preconditions'=>dupGuard());
    $copied=dupRpc('duplicate_resource',$request);
    $copyId=(int)($copied['id']??0);
    dupCheck($copyId>0 && $copyId!==$sourceId,
        'duplicate resource returned distinct ID');
    $created[]=array('type'=>'resource','id'=>$copyId);
    $read=dupRpc('get_element',array('type'=>'resource','id'=>$copyId));
    dupCheck((string)($read['pagetitle']??'')===$name
        && (string)($read['content']??'')==='RESOURCE_'.$fixture,
        'duplicate resource content independently read');
    dupCheck(empty($read['published']),'resource copy remains unpublished');
    dupReplay('duplicate_resource',$request);
    $again=dupRpc('get_element',array('type'=>'resource','id'=>$copyId));
    dupCheck((int)($again['id']??0)===$copyId,'stale duplicate did not remove copy');
    echo 'CAS_DUPLICATE_RESOURCE_READBACK_STALE_REPLAY_OK'.PHP_EOL;
}finally{
    foreach(array_reverse($created) as $item){
        dupRpc('delete_element',array('type'=>$item['type'],'id'=>$item['id'],'dry_run'=>false));
    }
}
echo 'CAS_DUPLICATE_RESOURCE_CHECKS_OK checks='.$checks
    .' platform='.($is2?'modx2':'modx3').PHP_EOL;
