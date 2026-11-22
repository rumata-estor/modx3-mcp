<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Отдельное CAS-доказательство создания привязки набора только к одноразовому чанку.
 * EN: Disposable property set assignment to a temporary chunk with CAS replay.
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
function assignRpc($action,array $data=array()) {
    global $api;
    return disposableCasRpc($api,$action,$data);
}
function assignCheck($test,$label) {
    global $checks;
    if(!$test)throw new RuntimeException('CAS_CREATE_EVIDENCE_FAILED '.$label);
    $checks++;
}
$fixture=bin2hex(random_bytes(5));
$setId=0;$chunkId=0;
function assignGuard(){
    $state=assignRpc('get_site_state');
    return array('observed_site_revision'=>(int)$state['site_revision'],
        'strict_site_revision'=>true,'read_set'=>array());
}
function assignLinks($setId){
    $set=assignRpc('get_property_set',array('id'=>$setId));
    assignCheck(isset($set['assignments'])&&is_array($set['assignments']),
        'property set assignment readback');
    return $set['assignments'];
}
function assignReplay($action,array $request){
    $before=assignRpc('get_site_state');$denied=false;
    try{assignRpc($action,$request);}
    catch(ModxMCPClientException $e){$denied=strpos($e->getMessage(),'STALE_STATE')!==false;}
    assignCheck($denied,$action.' stale CAS denied');
    $after=assignRpc('get_site_state');
    assignCheck((int)$before['site_revision']===(int)$after['site_revision'],
        $action.' stale replay did not advance revision');
}
try{
    // RU: Создаём исключительно собственный чанк. Никогда не исполняем его.
    // EN: Bind only to a new disposable chunk; never render or execute it.
    $chunk=assignRpc('create_element',array('type'=>'chunk',
        'name'=>'MCP_CAS_Assign_Chunk_'.$fixture,'content'=>'STATIC_'.$fixture));
    $chunkId=(int)($chunk['id']??0);
    assignCheck($chunkId>0,'disposable chunk created');
    $set=assignRpc('create_property_set',array('name'=>'MCP_CAS_Assign_Set_'.$fixture,
        'description'=>'assignment CI fixture','properties'=>array()));
    $setId=(int)($set['id']??0);
    assignCheck($setId>0,'disposable property set created');
    assignCheck(assignLinks($setId)===array(),'new set has no assignments');
    $assign=array('element'=>$chunkId,'element_type'=>'chunk',
        'property_set'=>$setId,'_runtime_preconditions'=>assignGuard());
    $result=assignRpc('assign_property_set',$assign);
    assignCheck(($result['status']??'')==='assigned','assignment applied');
    $links=assignLinks($setId);
    assignCheck(count($links)===1
        && (int)($links[0]['element']??0)===$chunkId
        && (int)($links[0]['property_set']??0)===$setId,
        'assignment exact readback');
    assignReplay('assign_property_set',$assign);
    assignCheck(count(assignLinks($setId))===1,
        'stale assignment produced no duplicate');
    echo 'CAS_PROPERTY_SET_ASSIGN_READBACK_STALE_REPLAY_OK'.PHP_EOL;

    $unassign=array('element'=>$chunkId,'element_type'=>'chunk',
        'property_set'=>$setId,'_runtime_preconditions'=>assignGuard());
    $result=assignRpc('unassign_property_set',$unassign);
    assignCheck(($result['status']??'')==='unassigned','assignment removed');
    assignCheck(assignLinks($setId)===array(),'unassigned link absent in MCP readback');
    assignReplay('unassign_property_set',$unassign);
    assignCheck(assignLinks($setId)===array(),'replay did not recreate assignment');
    echo 'CAS_PROPERTY_SET_UNASSIGN_READBACK_STALE_REPLAY_OK'.PHP_EOL;
}finally{
    if($setId>0)assignRpc('delete_property_set',array('id'=>$setId));
    if($chunkId>0)assignRpc('delete_element',array('type'=>'chunk','id'=>$chunkId));
}
echo 'CAS_PROPERTY_SET_ASSIGNMENT_OK checks='.$checks
    .' platform='.($is2?'modx2':'modx3').PHP_EOL;
