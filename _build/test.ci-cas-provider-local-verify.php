<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Отдельное CAS-доказательство удаления одноразового транспортного провайдера.
 * EN: Disposable provider create/update via loopback-only HTTP verification.
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
function mockRpc($action,array $data=array()) {
    global $api;
    return disposableCasRpc($api,$action,$data);
}
function mockCheck($test,$label) {
    global $checks;
    if(!$test)throw new RuntimeException('CAS_CREATE_EVIDENCE_FAILED '.$label);
    $checks++;
}
$fixture=bin2hex(random_bytes(6));
$name='MCP_Disposable_LocalProvider_'.$fixture;
$id=0;
$url=(string)getenv('MODXMCP_CI_PROVIDER_URL');
mockCheck(preg_match('~^http://127\.0\.0\.1:[0-9]{2,5}/$~D',$url)===1,
    'provider URL is local loopback only');
function mockGuard(){
    $state=mockRpc('get_site_state');
    return array('observed_site_revision'=>(int)$state['site_revision'],
        'strict_site_revision'=>true,'read_set'=>array());
}
function mockList($name){
    $list=mockRpc('list_providers');
    mockCheck(isset($list['results'])&&is_array($list['results']),
        'MCP provider list available');
    return array_values(array_filter($list['results'],function($row)use($name){
        return is_array($row) && (string)($row['name']??'')===$name;
    }));
}
function mockReplay($action,array $request){
    $before=mockRpc('get_site_state');$denied=false;
    try{mockRpc($action,$request);}
    catch(ModxMCPClientException $e){
        $denied=strpos($e->getMessage(),'STALE_STATE')!==false;
    }
    mockCheck($denied,$action.' stale CAS replay denied');
    $after=mockRpc('get_site_state');
    mockCheck((int)$before['site_revision']===(int)$after['site_revision'],
        $action.' stale replay consumed no extra revision');
}
try{
    // RU: Внешние провайдеры никогда не вызываются; только localhost.
    // EN: Exercise stock MODX processor verify against isolated localhost.
    $create=array('name'=>$name,'service_url'=>$url,
        'description'=>'MCP disposable local provider',
        '_runtime_preconditions'=>mockGuard());
    $result=mockRpc('create_provider',$create);
    $rows=mockList($name);
    mockCheck(count($rows)===1,'provider created exactly once');
    $id=(int)($rows[0]['id']??0);
    mockCheck($id>0,'provider created ID read through MCP');
    mockReplay('create_provider',$create);
    mockCheck(count(mockList($name))===1,'stale create did not duplicate provider');
    echo 'CAS_PROVIDER_CREATE_LOCAL_VERIFY_STALE_REPLAY_OK'.PHP_EOL;

    $update=array('id'=>$id,'name'=>$name,'service_url'=>$url,
        'description'=>'MCP disposable local provider updated',
        '_runtime_preconditions'=>mockGuard());
    mockRpc('update_provider',$update);
    $rows=mockList($name);
    mockCheck(count($rows)===1 && (int)($rows[0]['id']??0)===$id,
        'updated provider retains original ID');
    mockCheck((string)($rows[0]['description']??'')
        ==='MCP disposable local provider updated',
        'updated description independent MCP readback');
    mockReplay('update_provider',$update);
    $rows=mockList($name);
    mockCheck(count($rows)===1,'stale update retained provider identity');
    mockCheck((string)($rows[0]['description']??'')
        ==='MCP disposable local provider updated',
        'stale update did not overwrite new description');
    echo 'CAS_PROVIDER_UPDATE_LOCAL_VERIFY_STALE_REPLAY_OK'.PHP_EOL;
} finally {
    if($id>0){
        mockRpc('delete_provider',array('id'=>$id));
        mockCheck(count(mockList($name))===0,'disposable provider cleanup complete');
    }
}
echo 'CAS_PROVIDER_LOCAL_VERIFY_EVIDENCE_OK checks='.$checks
    .' platform='.($is2?'modx2':'modx3').PHP_EOL;
