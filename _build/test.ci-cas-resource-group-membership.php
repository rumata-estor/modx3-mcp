<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Отдельное CAS-доказательство назначения одноразового ресурса в одноразовую группу.
 * EN: Disposable resource-to-group link CAS readback and replay evidence.
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
$groupId=0;$resourceId=0;$linked=false;
function rgGuard(){
    $state=rgRpc('get_site_state');
    return array('observed_site_revision'=>(int)$state['site_revision'],
        'strict_site_revision'=>true,'read_set'=>array());
}
function rgLinks($groupId){
    $res=rgRpc('list_resource_group_resources',array('resourceGroup'=>$groupId));
    rgCheck(isset($res['results'])&&is_array($res['results']),
        'group resource links MCP readback available');
    return $res['results'];
}
function rgMatch($rows,$resourceId){
    return array_values(array_filter($rows,function($row)use($resourceId){
        return is_array($row) && (int)($row['resource']??0)===$resourceId;
    }));
}
function rgStale($action,array $request){
    $before=rgRpc('get_site_state');$denied=false;
    try{rgRpc($action,$request);}
    catch(ModxMCPClientException $e){
        $denied=strpos($e->getMessage(),'STALE_STATE')!==false;
    }
    rgCheck($denied,$action.' stale CAS replay denied');
    $after=rgRpc('get_site_state');
    rgCheck((int)$before['site_revision']===(int)$after['site_revision'],
        $action.' stale replay did not consume site revision');
}
try{
    // RU: Только свой неопубликованный ресурс, никаких внешних ACL.
    // EN: Own unpublished resource and own group, no unrelated ACL grants.
    $group=rgRpc('create_resource_group',array('name'=>'MCP_CAS_LinkGrp_'.$fixture));
    $groupId=(int)($group['id']??0);
    rgCheck($groupId>0,'isolated resource group created');
    $resource=rgRpc('create_element',array('type'=>'resource',
        'name'=>'MCP_CAS_LinkRes_'.$fixture,'alias'=>'mcp-link-'.$fixture,
        'content'=>'CAS link only','template'=>0,'published'=>0,
        'hidemenu'=>1,'parent'=>0));
    $resourceId=(int)($resource['id']??0);
    rgCheck($resourceId>0,'isolated unpublished resource created');
    rgCheck(count(rgMatch(rgLinks($groupId),$resourceId))===0,
        'no resource group link before assignment');

    $assign=array('resource'=>$resourceId,'resourceGroup'=>$groupId,
        '_runtime_preconditions'=>rgGuard());
    $perm=(int)$modx->hasPermission('resourcegroup_resource_edit');
    $version=$modx->getVersionData();
    fwrite(STDERR,'RESOURCE_GROUP_ASSIGN_PRECHECK platform='.
        ($is2?'modx2':'modx3').' permission='.$perm
        .' major='.(int)($version['version']??0).PHP_EOL);
    try {
        rgRpc('assign_resource_to_group',$assign);
    } catch (ModxMCPClientException $e) {
        $observed=count(rgMatch(rgLinks($groupId),$resourceId));
        $linked=$observed>0;
        fwrite(STDERR,'RESOURCE_GROUP_ASSIGN_DIAG platform='.
            ($is2?'modx2':'modx3').' links_after_error='.$observed.PHP_EOL);
        throw $e;
    }
    $linked=true;
    rgCheck(count(rgMatch(rgLinks($groupId),$resourceId))===1,
        'resource assignment independently read via MCP');
    rgStale('assign_resource_to_group',$assign);
    rgCheck(count(rgMatch(rgLinks($groupId),$resourceId))===1,
        'stale assignment did not duplicate resource group link');
    echo 'CAS_RESOURCE_GROUP_ASSIGN_RESOURCE_STALE_READBACK_OK'.PHP_EOL;

    $remove=array('resource'=>$resourceId,'resourceGroup'=>$groupId,
        '_runtime_preconditions'=>rgGuard());
    rgRpc('remove_resource_from_group',$remove);
    $linked=false;
    rgCheck(count(rgMatch(rgLinks($groupId),$resourceId))===0,
        'removed resource group link independently read via MCP');
    rgStale('remove_resource_from_group',$remove);
    rgCheck(count(rgMatch(rgLinks($groupId),$resourceId))===0,
        'stale removal did not recreate resource group link');
    echo 'CAS_RESOURCE_GROUP_REMOVE_RESOURCE_STALE_READBACK_OK'.PHP_EOL;
}finally{
    if($linked && $resourceId>0 && $groupId>0){
        rgRpc('remove_resource_from_group',array('resource'=>$resourceId,
            'resourceGroup'=>$groupId));
    }
    if($resourceId>0)rgRpc('delete_element',array('type'=>'resource',
        'id'=>$resourceId,'dry_run'=>false));
    if($groupId>0)rgRpc('delete_resource_group',array('id'=>$groupId));
}
echo 'CAS_RESOURCE_GROUP_RESOURCE_LINK_OK checks='.$checks
    .' platform='.($is2?'modx2':'modx3').PHP_EOL;
