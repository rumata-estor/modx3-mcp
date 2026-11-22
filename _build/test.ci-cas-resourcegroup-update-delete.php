<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Отдельное CAS-доказательство создания группы ресурсов без ACL.
 * EN: Disposable resource group rename/delete CAS evidence without ACL grants.
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
function rgRpc($action,array $data=array()) {
    global $api;
    return disposableCasRpc($api,$action,$data);
}
function rgCheck($test,$label) {
    global $checks;
    if(!$test)throw new RuntimeException('CAS_CREATE_EVIDENCE_FAILED '.$label);
    $checks++;
}
$fixture=bin2hex(random_bytes(5));
$name='MCP_CAS_RGroup_'.$fixture;
$id=0;
function rgGuard(){
    $state=rgRpc('get_site_state');
    return array('observed_site_revision'=>(int)$state['site_revision'],
        'strict_site_revision'=>true,'read_set'=>array());
}
function rgReplay($action,array $request){
    $before=rgRpc('get_site_state');$denied=false;
    try{rgRpc($action,$request);}
    catch(ModxMCPClientException $e){$denied=strpos($e->getMessage(),'STALE_STATE')!==false;}
    rgCheck($denied,$action.' stale CAS replay denied');
    $after=rgRpc('get_site_state');
    rgCheck((int)$before['site_revision']===(int)$after['site_revision'],
        $action.' stale CAS did not advance revision');
}
try{
    // RU: Группа изолирована, не назначается ни пользователям, ни ресурсам.
    // EN: No resource or user ACL assignments to the disposable group.
    $created=rgRpc('create_resource_group',array('name'=>$name));
    $id=(int)($created['id']??0);
    rgCheck($id>0,'resource group fixture created');
    $read=rgRpc('get_resource_group',array('id'=>$id));
    rgCheck(($read['name']??'')===$name,'new group readback');
    $updatedName=$name.'_updated';
    $update=array('id'=>$id,'name'=>$updatedName,
        '_runtime_preconditions'=>rgGuard());
    rgRpc('update_resource_group',$update);
    $read=rgRpc('get_resource_group',array('id'=>$id));
    rgCheck(($read['name']??'')===$updatedName,
        'resource group rename persisted');
    rgReplay('update_resource_group',$update);
    $read=rgRpc('get_resource_group',array('id'=>$id));
    rgCheck(($read['name']??'')===$updatedName,
        'stale rename did not alter group');
    echo 'CAS_RESOURCE_GROUP_UPDATE_READBACK_STALE_REPLAY_OK'.PHP_EOL;

    $delete=array('id'=>$id,'_runtime_preconditions'=>rgGuard());
    rgRpc('delete_resource_group',$delete);
    $id=0;
    rgReplay('delete_resource_group',$delete);
    $list=rgRpc('list_resource_groups',array('query'=>$updatedName,'limit'=>100));
    rgCheck(isset($list['results'])&&is_array($list['results']),
        'resource group list available');
    foreach($list['results'] as $row){
        rgCheck(!is_array($row)||($row['name']??'')!==$updatedName,
            'deleted group absent from readback');
    }
    echo 'CAS_RESOURCE_GROUP_DELETE_READBACK_STALE_REPLAY_OK'.PHP_EOL;
}finally{
    if($id>0)rgRpc('delete_resource_group',array('id'=>$id));
}
echo 'CAS_RESOURCE_GROUP_UPDATE_DELETE_OK checks='.$checks
    .' platform='.($is2?'modx2':'modx3').PHP_EOL;
