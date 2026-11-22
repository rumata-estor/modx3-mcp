<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Отдельное CAS-доказательство удаления одноразового транспортного провайдера.
 * EN: Disposable transport provider deletion via MCP, no network calls.
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
function delRpc($action,array $data=array()) {
    global $api;
    return disposableCasRpc($api,$action,$data);
}
function delCheck($test,$label) {
    global $checks;
    if(!$test)throw new RuntimeException('CAS_CREATE_EVIDENCE_FAILED '.$label);
    $checks++;
}
$fixture=bin2hex(random_bytes(6));
$name='MCP_Disposable_Delete_Provider_'.$fixture;
$id=0;
function delGuard(){
    $s=delRpc('get_site_state');
    return array('observed_site_revision'=>(int)$s['site_revision'],
        'strict_site_revision'=>true,'read_set'=>array());
}
function delList($name){
    $result=delRpc('list_providers');
    delCheck(isset($result['results'])&&is_array($result['results']),
        'MCP provider list available');
    return array_values(array_filter($result['results'],function($row)use($name){
        return is_array($row) && (string)($row['name']??'')===$name;
    }));
}
try{
    // RU: Только собственная фикстура через штатный MODX API; нет сети.
    // EN: Seed an unassigned provider natively in disposable MODX, no network.
    $class=$is2?'transport.modTransportProvider'
        :'MODX\\Revolution\\Transport\\modTransportProvider';
    $provider=$modx->newObject($class);
    delCheck($provider!==null,'disposable provider native class available');
    $provider->fromArray(array('name'=>$name,
        'service_url'=>'https://mcp-disposable.invalid/',
        'description'=>'isolated test fixture'));
    delCheck($provider->save()!==false,'disposable provider fixture saved');
    $id=(int)$provider->get('id');
    delCheck($id>0,'temporary provider ID assigned');
    $rows=delList($name);
    delCheck(count($rows)===1 && (int)($rows[0]['id']??0)===$id,
        'temporary provider independently visible in MCP list');
    $request=array('id'=>$id,'_runtime_preconditions'=>delGuard());
    $response=delRpc('delete_provider',$request);
    delCheck(!empty($response['deleted']) && (int)($response['id']??0)===$id,
        'delete_provider returned deleted own ID');
    $id=0;
    delCheck(count(delList($name))===0,'provider no longer appears in MCP list');
    $after=delRpc('get_site_state');
    delCheck((int)$after['site_revision']
        ===(int)$request['_runtime_preconditions']['observed_site_revision']+1,
        'delete_provider incremented site revision exactly once');
    $stale=false;
    try{delRpc('delete_provider',$request);}
    catch(ModxMCPClientException $e){
        $stale=strpos($e->getMessage(),'STALE_STATE')!==false;
    }
    delCheck($stale,'stale delete_provider replay rejected');
    $replayed=delRpc('get_site_state');
    delCheck((int)$replayed['site_revision']===(int)$after['site_revision'],
        'stale provider delete did not consume revision');
    delCheck(count(delList($name))===0,
        'replayed delete did not reintroduce provider');
    echo 'CAS_DELETE_PROVIDER_DISPOSABLE_STALE_REPLAY_OK'.PHP_EOL;
} finally {
    if($id>0){
        // RU: Cleanup только собственной фикстуры одноразового MODX.
        // EN: Cleanup exclusively the disposable provider fixture.
        $provider=$modx->getObject($class,$id);
        if($provider)$provider->remove();
    }
}
echo 'CAS_DELETE_PROVIDER_EVIDENCE_OK checks='.$checks
    .' platform='.($is2?'modx2':'modx3').PHP_EOL;
