<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Отдельное CAS-доказательство создания пространства имён без регистрации файлов.
 * EN: Namespace metadata-only CAS lifecycle and stale replay evidence.
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
function nsRpc($action,array $data=array()) {
    global $api;
    return disposableCasRpc($api,$action,$data);
}
function nsCheck($test,$label) {
    global $checks;
    if(!$test)throw new RuntimeException('CAS_CREATE_EVIDENCE_FAILED '.$label);
    $checks++;
}
$fixture=bin2hex(random_bytes(5));
$name='mcpdisposablens'.$fixture;
$created=false;
function nsGuard(){
    $state=nsRpc('get_site_state');
    return array('observed_site_revision'=>(int)$state['site_revision'],
        'strict_site_revision'=>true,'read_set'=>array());
}
function nsReplay($action,array $request){
    $before=nsRpc('get_site_state');
    $denied=false;
    try{nsRpc($action,$request);}
    catch(ModxMCPClientException $e){$denied=strpos($e->getMessage(),'STALE_STATE')!==false;}
    nsCheck($denied,$action.' stale replay denied');
    $after=nsRpc('get_site_state');
    nsCheck((int)$before['site_revision']===(int)$after['site_revision'],
        $action.' stale replay did not increment revision');
}
function nsLookup($name){
    $result=nsRpc('list_namespaces',array('limit'=>500));
    nsCheck(isset($result['results'])&&is_array($result['results']),
        'namespace list available');
    $matches=array();
    foreach($result['results'] as $row){
        if(is_array($row) && ($row['name']??'')===$name)$matches[]=$row;
    }
    return $matches;
}
try{
    // RU: Уникальное имя и только метаданные — без файлов и разрешений.
    // EN: Register only a disposable metadata namespace, never files.
    $create=array('name'=>$name,'path'=>'core/components/',
        '_runtime_preconditions'=>nsGuard());
    nsRpc('create_namespace',$create);
    $created=true;
    nsCheck(count(nsLookup($name))===1,'namespace created once');
    nsReplay('create_namespace',$create);
    nsCheck(count(nsLookup($name))===1,'namespace create replay no duplicate');
    echo 'CAS_NAMESPACE_CREATE_READBACK_STALE_REPLAY_OK'.PHP_EOL;

    $update=array('name'=>$name,'assets_path'=>'assets/components/',
        '_runtime_preconditions'=>nsGuard());
    nsRpc('update_namespace',$update);
    $rows=nsLookup($name);
    nsCheck(count($rows)===1,'namespace update preserved identity');
    nsReplay('update_namespace',$update);
    echo 'CAS_NAMESPACE_UPDATE_READBACK_STALE_REPLAY_OK'.PHP_EOL;

    $delete=array('name'=>$name,'_runtime_preconditions'=>nsGuard());
    nsRpc('delete_namespace',$delete);
    $created=false;
    nsCheck(count(nsLookup($name))===0,'namespace removed');
    nsReplay('delete_namespace',$delete);
    echo 'CAS_NAMESPACE_DELETE_READBACK_STALE_REPLAY_OK'.PHP_EOL;
}finally{
    if($created)nsRpc('delete_namespace',array('name'=>$name));
}
echo 'CAS_NAMESPACE_LIFECYCLE_OK checks='.$checks
    .' platform='.($is2?'modx2':'modx3').PHP_EOL;
