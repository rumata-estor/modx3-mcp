<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Отдельное CAS-доказательство создания восстановления собственной удалённой страницы.
 * EN: Disposable unpublished resource undelete CAS evidence.
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
function restoreRpc($action,array $data=array()) {
    global $api;
    return disposableCasRpc($api,$action,$data);
}
function restoreCheck($test,$label) {
    global $checks;
    if(!$test)throw new RuntimeException('CAS_CREATE_EVIDENCE_FAILED '.$label);
    $checks++;
}
$fixture=bin2hex(random_bytes(6));
$id=0;
function restoreGuard(){
    $state=restoreRpc('get_site_state');
    return array('observed_site_revision'=>(int)$state['site_revision'],
        'strict_site_revision'=>true,'read_set'=>array());
}
try {
    // RU: Свой скрытый неопубликованный ресурс; нет реальных страниц.
    // EN: Only a disposable hidden and unpublished resource.
    $created=restoreRpc('create_element',array(
        'type'=>'resource','name'=>'MCP_Disposable_Undelete_'.$fixture,
        'alias'=>'mcp-undelete-'.$fixture,'content'=>'test-only',
        'published'=>0,'hidemenu'=>1,'template'=>0,'parent'=>0));
    $id=(int)($created['id']??0);
    restoreCheck($id>0,'disposable resource ID assigned');
    $first=restoreRpc('get_element',array('type'=>'resource','id'=>$id));
    restoreCheck(empty($first['deleted']),'fixture initially not deleted');
    restoreRpc('delete_element',array('type'=>'resource','id'=>$id,'dry_run'=>false));
    $deleted=restoreRpc('get_element',array('type'=>'resource','id'=>$id));
    restoreCheck(!empty($deleted['deleted']),'resource soft deleted via MCP');

    $request=array('id'=>$id,'_runtime_preconditions'=>restoreGuard());
    $result=restoreRpc('undelete_resource',$request);
    restoreCheck(!empty($result['undeleted']),'undelete returned success');
    $restored=restoreRpc('get_element',array('type'=>'resource','id'=>$id));
    restoreCheck(empty($restored['deleted']),'resource restored via independent MCP read');
    restoreCheck(empty($restored['published']),'restored resource remains unpublished');
    $before=restoreRpc('get_site_state');
    $replayDenied=false;
    try{restoreRpc('undelete_resource',$request);}
    catch(ModxMCPClientException $e){
        $replayDenied=strpos($e->getMessage(),'STALE_STATE')!==false;
    }
    restoreCheck($replayDenied,'stale undelete replay denied');
    $after=restoreRpc('get_site_state');
    restoreCheck((int)$before['site_revision']===(int)$after['site_revision'],
        'rejected replay consumed no revision');
    $read=restoreRpc('get_element',array('type'=>'resource','id'=>$id));
    restoreCheck(empty($read['deleted']),'stale replay left restored state intact');
    echo 'CAS_UNDELETE_RESOURCE_READBACK_STALE_REPLAY_OK'.PHP_EOL;
}finally{
    if($id>0){
        $state=restoreRpc('get_element',array('type'=>'resource','id'=>$id));
        if(empty($state['deleted'])){
            restoreRpc('delete_element',array('type'=>'resource','id'=>$id,'dry_run'=>false));
        }
    }
}
echo 'CAS_UNDELETE_RESOURCE_EVIDENCE_OK checks='.$checks
    .' platform='.($is2?'modx2':'modx3').PHP_EOL;
