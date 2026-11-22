<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Отдельное CAS-доказательство создания порядка двух собственных неопубликованных страниц.
 * EN: Disposable two-resource reorder CAS and independent readback evidence.
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
function orderRpc($action,array $data=array()) {
    global $api;
    return disposableCasRpc($api,$action,$data);
}
function orderCheck($test,$label) {
    global $checks;
    if(!$test)throw new RuntimeException('CAS_CREATE_EVIDENCE_FAILED '.$label);
    $checks++;
}
$fixture=bin2hex(random_bytes(5));
$ids=array();
function orderGuard(){
    $state=orderRpc('get_site_state');
    return array('observed_site_revision'=>(int)$state['site_revision'],
        'strict_site_revision'=>true,'read_set'=>array());
}
function orderRead($id){
    return orderRpc('get_element',array('type'=>'resource','id'=>$id));
}
try{
    // RU: Обновляем исключительно две созданные тестом скрытые страницы.
    // EN: Change menuindex only on two self-created unpublished pages.
    foreach(array(0,1) as $idx){
        $result=orderRpc('create_element',array('type'=>'resource',
            'name'=>'MCP_Disposable_Reorder_'.$fixture.'_'.$idx,
            'alias'=>'mcp-order-'.$fixture.'-'.$idx,
            'content'=>'fixture only','template'=>0,'published'=>0,
            'hidemenu'=>1,'parent'=>0,'menuindex'=>1200+$idx));
        $id=(int)($result['id']??0);
        orderCheck($id>0,'created disposable resource '.$idx);
        $ids[]=$id;
    }
    $old=array();
    foreach($ids as $id){
        $resource=orderRead($id);
        orderCheck(empty($resource['published']),'fixture remains unpublished');
        $old[]=(int)$resource['menuindex'];
    }
    $next=array($old[0]+100,$old[1]+200);
    $request=array('items'=>array(
        array('id'=>$ids[0],'menuindex'=>$next[0]),
        array('id'=>$ids[1],'menuindex'=>$next[1]),
    ),'_runtime_preconditions'=>orderGuard());
    $result=orderRpc('reorder_resources',$request);
    orderCheck((int)($result['count']??0)===2,'reorder handled both resources');
    foreach(($result['results']??array()) as $row){
        orderCheck(($row['status']??'')==='ok','reorder per-item success');
    }
    foreach($ids as $idx=>$id){
        $resource=orderRead($id);
        orderCheck((int)$resource['menuindex']===$next[$idx],
            'new menuindex persisted via independent MCP read');
        orderCheck(empty($resource['published']),'reorder did not publish fixture');
    }
    $stateBefore=orderRpc('get_site_state');
    $stale=false;
    try{orderRpc('reorder_resources',$request);}
    catch(ModxMCPClientException $e){
        $stale=strpos($e->getMessage(),'STALE_STATE')!==false;
    }
    orderCheck($stale,'stale reorder replay rejected');
    $stateAfter=orderRpc('get_site_state');
    orderCheck((int)$stateBefore['site_revision']===(int)$stateAfter['site_revision'],
        'stale replay did not consume revision');
    foreach($ids as $idx=>$id){
        $resource=orderRead($id);
        orderCheck((int)$resource['menuindex']===$next[$idx],
            'stale replay preserved new menuindex');
    }
    echo 'CAS_REORDER_RESOURCES_READBACK_STALE_REPLAY_OK'.PHP_EOL;
} finally {
    foreach(array_reverse($ids) as $id){
        orderRpc('delete_element',array('type'=>'resource','id'=>$id,'dry_run'=>false));
    }
}
echo 'CAS_REORDER_RESOURCES_EVIDENCE_OK checks='.$checks
    .' platform='.($is2?'modx2':'modx3').PHP_EOL;
