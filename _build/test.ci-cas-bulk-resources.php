<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Отдельное CAS-доказательство создания публикации только двух собственных страниц.
 * EN: Disposable two-resource bulk publish CAS readback and replay evidence.
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
function bulkRpc($action,array $data=array()) {
    global $api;
    return disposableCasRpc($api,$action,$data);
}
function bulkCheck($test,$label) {
    global $checks;
    if(!$test)throw new RuntimeException('CAS_CREATE_EVIDENCE_FAILED '.$label);
    $checks++;
}
$fixture=bin2hex(random_bytes(5));
$ids=array();
function bulkGuard(){
    $state=bulkRpc('get_site_state');
    return array('observed_site_revision'=>(int)$state['site_revision'],
        'strict_site_revision'=>true,'read_set'=>array());
}
function bulkRead($id){
    return bulkRpc('get_element',array('type'=>'resource','id'=>$id));
}
try{
    // RU: Операция в одноразовом MODX, только две созданные тестом страницы.
    // EN: Publish only two pages in the disposable test instance.
    foreach(array(0,1) as $index){
        $created=bulkRpc('create_element',array('type'=>'resource',
            'name'=>'MCP_CI_Bulk_'.$fixture.'_'.$index,
            'alias'=>'mcp-bulk-'.$fixture.'-'.$index,
            'template'=>0,'published'=>0,'hidemenu'=>1,'parent'=>0));
        $id=(int)($created['id']??0);
        bulkCheck($id>0,'disposable resource created');
        $ids[]=$id;
        bulkCheck(empty(bulkRead($id)['published']),
            'resource initially unpublished');
    }
    $preview=bulkRpc('bulk_resources',array('operation'=>'publish',
        'ids'=>$ids,'limit'=>2,'dry_run'=>true));
    bulkCheck((int)($preview['count']??0)===2,
        'preview selected both isolated resources');
    foreach($ids as $id){
        bulkCheck(empty(bulkRead($id)['published']),
            'dry-run preserved unpublished state');
    }
    $request=array('operation'=>'publish','ids'=>$ids,
        'limit'=>2,'dry_run'=>false,'_runtime_preconditions'=>bulkGuard());
    $result=bulkRpc('bulk_resources',$request);
    bulkCheck((int)($result['count']??0)===2,
        'bulk action processed both resources');
    foreach($result['results'] as $row){
        bulkCheck(($row['status']??'')==='ok','each bulk result succeeded');
    }
    foreach($ids as $id){
        bulkCheck(!empty(bulkRead($id)['published']),
            'published state independently confirmed');
    }
    $before=bulkRpc('get_site_state');$denied=false;
    try{bulkRpc('bulk_resources',$request);}
    catch(ModxMCPClientException $e){
        $denied=strpos($e->getMessage(),'STALE_STATE')!==false;
    }
    bulkCheck($denied,'stale bulk action denied');
    $after=bulkRpc('get_site_state');
    bulkCheck((int)$before['site_revision']===(int)$after['site_revision'],
        'stale bulk action did not advance revision');
    foreach($ids as $id){
        bulkCheck(!empty(bulkRead($id)['published']),
            'stale replay preserved published state');
    }
    echo 'CAS_BULK_RESOURCES_READBACK_STALE_REPLAY_OK'.PHP_EOL;
}finally{
    foreach(array_reverse($ids) as $id){
        bulkRpc('delete_element',array('type'=>'resource','id'=>$id,'dry_run'=>false));
    }
}
echo 'CAS_BULK_RESOURCES_OK checks='.$checks
    .' platform='.($is2?'modx2':'modx3').PHP_EOL;
