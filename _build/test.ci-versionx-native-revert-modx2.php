<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Одноразовый MODX2: реальная история VersionX и восстановление через MCP.
 * EN: Native VersionX 2.4.0 resource snapshot + real MCP revert, readback/CAS.
 */
if (PHP_SAPI !== 'cli' || getenv('MODXMCP_DISPOSABLE_CI') !== '1'
    || strpos((string)getenv('MODX_CONFIG_CORE'), '/home/runner/') !== 0
    || !is_file((string)getenv('MODX_CONFIG_CORE'))
    || strpos((string)getenv('RUNNER_TEMP'), '/home/runner/') !== 0) {
    fwrite(STDERR,"Refusing VersionX native E2E outside disposable CI.\n");
    exit(3);
}
require getenv('MODX_CONFIG_CORE');
if(is_file(MODX_CORE_PATH.'src/Revolution/modX.php')) {
    throw new RuntimeException('Legacy VersionX 2.4 E2E only targets MODX 2');
}
require MODX_CORE_PATH.'model/modx/modx.class.php';
$modx=new modX();
$modx->initialize('mgr');
$modx->setOption('modxmcp.disabled_groups','');
$path=MODX_CORE_PATH.'components/versionx/';
if(!is_file($path.'model/versionx.class.php')
    || !is_file($path.'processors/mgr/resources/revert.class.php')) {
    throw new RuntimeException('Pinned native VersionX 2.x source unavailable');
}
$admin=$modx->getObject('modUser',1);
if(!$admin || (int)$admin->get('sudo')!==1) {
    throw new RuntimeException('Disposable sudo manager unavailable');
}
$modx->user=$admin;
$modx->user->addSessionContext('mgr');
if(!$modx->addPackage('versionx',$path.'model/')) {
    throw new RuntimeException('Cannot load native VersionX model');
}
if(!$modx->getManager()->createObjectContainer('vxResource')) {
    throw new RuntimeException('Could not initialize disposable VersionX resource table');
}
$vx=$modx->getService('versionx','VersionX',$path.'model/');
if(!$vx || !method_exists($vx,'newResourceVersion')) {
    throw new RuntimeException('Legacy VersionX snapshot API unavailable');
}
require MODX_CORE_PATH.'components/modxmcp/model/modxmcp.class.php';
require __DIR__.'/test.cas-mcp-helper.php';
$api=new modxMCP($modx);
$checks=0;
function vxRpc($action,array $args=array()){
    global $api;
    return disposableCasRpc($api,$action,$args);
}
function vxCheck($ok,$reason){
    global $checks;
    if(!$ok)throw new RuntimeException('VERSIONX_NATIVE_REVERT_E2E_FAILED '.$reason);
    ++$checks;
}
function vxRev(){ $s=vxRpc('get_site_state');return (int)$s['site_revision'];}
function vxGuard(){return array('observed_site_revision'=>vxRev(),
    'strict_site_revision'=>true,'read_set'=>array());}
$id=0;$versionId=0;
$marker=bin2hex(random_bytes(5));
$beforeText='MCP_CI_VersionX_Before_'.$marker;
$afterText='MCP_CI_VersionX_After_'.$marker;
try{
    $created=vxRpc('create_element',array(
        'type'=>'resource','name'=>'MCP_CI_VersionX_'.$marker,
        'alias'=>'mcp-versionx-'.$marker,'parent'=>0,'template'=>0,
        'published'=>0,'hidemenu'=>1,'content'=>$beforeText
    ));
    $id=(int)($created['id']??0);
    vxCheck($id>0,'own unpublished test page ID');
    $read=vxRpc('get_element',array('type'=>'resource','id'=>$id));
    vxCheck(($read['content']??'')===$beforeText && empty($read['published']),
        'original resource readback through MCP');

    // RU: Настоящая версия создаётся ШТАТНЫМ сервисом VersionX,
    // RU: а не ручной записью истории / фиктивными версиями.
    // EN: Native VersionX service generates a real history record for this fixture.
    vxCheck($vx->newResourceVersion($id,'upd')===true,
        'native VersionX successfully generated resource history');
    $list=vxRpc('versionx_list_versions',array(
        'type'=>'resource','content_id'=>$id,'limit'=>20
    ));
    vxCheck((int)($list['count']??0)>=1,'MCP lists native VersionX history');
    foreach(($list['versions']??array()) as $version){
        if((int)($version['content_id']??0)===$id){
            $versionId=(int)($version['version_id']??0);
            break;
        }
    }
    vxCheck($versionId>0,'snapshot version ID independently visible');
    $snapshot=vxRpc('versionx_get_version',array(
        'type'=>'resource','content_id'=>$id,'version_id'=>$versionId
    ));
    vxCheck(($snapshot['content_preview']??'')===$beforeText,
        'historical content from MCP matches native snapshot');
    $updated=vxRpc('update_element',array(
        'type'=>'resource','id'=>$id,'content'=>$afterText,'published'=>0,
        '_runtime_preconditions'=>vxGuard()
    ));
    $current=vxRpc('get_element',array('type'=>'resource','id'=>$id));
    vxCheck(($current['content']??'')===$afterText,
        'test resource actually changed before revert');
    vxCheck(empty($current['published']),'resource never published');

    $start=vxRev();
    $request=array('type'=>'resource','content_id'=>$id,
        'version_id'=>$versionId,'confirm'=>true,
        '_runtime_preconditions'=>vxGuard());
    $reverted=vxRpc('versionx_revert_version',$request);
    vxCheck(!empty($reverted['reverted']) && (int)$reverted['content_id']===$id,
        'native VersionX revert result confirmed');
    vxCheck(vxRev()===$start+1,'revert CAS revision increased once');
    $after=vxRpc('get_element',array('type'=>'resource','id'=>$id));
    vxCheck(($after['content']??'')===$beforeText,
        'real native VersionX revert restored snapshot through MCP readback');
    vxCheck(empty($after['published']),'reverted resource remains unpublished');
    $revision=vxRev();$refused=false;
    try{vxRpc('versionx_revert_version',$request);}
    catch(ModxMCPClientException $e){
        $refused=strpos($e->getMessage(),'STALE_STATE')!==false;
    }
    vxCheck($refused,'stale native revert replay blocked');
    vxCheck(vxRev()===$revision,'stale revert did not consume a revision');
    vxCheck((vxRpc('get_element',array('type'=>'resource','id'=>$id))['content']??'')
        ===$beforeText,'stale replay preserved restored content');
    echo "CAS_VERSIONX_REVERT_MODX2_NATIVE_HISTORY_READBACK_STALE_OK\n";
} finally {
    // RU: Чистим только свою неопубликованную страницу во временной базе.
    // EN: Only the owned ephemeral resource is deleted through MCP.
    if($id>0){
        try{vxRpc('delete_element',array('type'=>'resource','id'=>$id,'dry_run'=>false));}
        catch(Throwable $ignored){}
    }
}
echo 'VERSIONX_NATIVE_REVERT_E2E_OK checks='.$checks.' platform=modx2'.PHP_EOL;
