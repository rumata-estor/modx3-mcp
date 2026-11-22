<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Отдельное CAS-доказательство создания провайдера пакетов без сетевых запросов.
 * EN: Disposable provider metadata CAS lifecycle with no remote requests.
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
function providerRpc($action,array $data=array()) {
    global $api;
    return disposableCasRpc($api,$action,$data);
}
function providerCheck($test,$label) {
    global $checks;
    if(!$test)throw new RuntimeException('CAS_CREATE_EVIDENCE_FAILED '.$label);
    $checks++;
}
$fixture=bin2hex(random_bytes(5));
$name='MCP_Disposable_Provider_'.$fixture;
$id=0;
function providerGuard(){
    $s=providerRpc('get_site_state');
    return array('observed_site_revision'=>(int)$s['site_revision'],
        'strict_site_revision'=>true,'read_set'=>array());
}
function providerRows($name){
    $list=providerRpc('list_providers');
    providerCheck(isset($list['results']) && is_array($list['results']),
        'provider list returned results');
    return array_values(array_filter($list['results'],function($r) use($name){
        return is_array($r) && (string)($r['name']??'')===$name;
    }));
}
function providerReplay($action,array $request){
    $before=providerRpc('get_site_state');$denied=false;
    try{providerRpc($action,$request);}
    catch(ModxMCPClientException $e){$denied=strpos($e->getMessage(),'STALE_STATE')!==false;}
    providerCheck($denied,$action.' stale CAS replay denied');
    $after=providerRpc('get_site_state');
    providerCheck((int)$before['site_revision']===(int)$after['site_revision'],
        $action.' stale replay did not advance revision');
}
try {
    // RU: Домен .invalid, никаких запросов к внешнему каталогу.
    // EN: Non-resolving reserved domain; no package search or network call.
    $create=array('name'=>$name,'service_url'=>'https://mcp-ci.example.invalid/',
        'description'=>'disposable metadata only','_runtime_preconditions'=>providerGuard());
    $result=providerRpc('create_provider',$create);
    $matches=providerRows($name);
    providerCheck(count($matches)===1,'exactly one provider registered');
    $id=(int)($matches[0]['id']??0);
    providerCheck($id>0,'provider identity read through MCP list');
    providerReplay('create_provider',$create);
    providerCheck(count(providerRows($name))===1,'no duplicate provider on replay');
    echo 'CAS_PROVIDER_CREATE_READBACK_STALE_REPLAY_OK'.PHP_EOL;

    $update=array('id'=>$id,'name'=>$name,
        'description'=>'disposable provider updated',
        '_runtime_preconditions'=>providerGuard());
    providerRpc('update_provider',$update);
    $matches=providerRows($name);
    providerCheck(count($matches)===1,'provider update retained identity');
    providerReplay('update_provider',$update);
    echo 'CAS_PROVIDER_UPDATE_READBACK_STALE_REPLAY_OK'.PHP_EOL;

    $delete=array('id'=>$id,'_runtime_preconditions'=>providerGuard());
    providerRpc('delete_provider',$delete);
    $id=0;
    providerCheck(count(providerRows($name))===0,'provider deleted');
    providerReplay('delete_provider',$delete);
    echo 'CAS_PROVIDER_DELETE_READBACK_STALE_REPLAY_OK'.PHP_EOL;
} finally {
    if($id>0)providerRpc('delete_provider',array('id'=>$id));
}
echo 'CAS_PROVIDER_LIFECYCLE_OK checks='.$checks
    .' platform='.($is2?'modx2':'modx3').PHP_EOL;
