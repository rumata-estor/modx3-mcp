<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Отдельное CAS-доказательство сброса кэша разрешений без изменения ACL.
 * EN: Disposable permission-cache flush and stale CAS replay evidence.
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
function flushRpc($action,array $data=array()) {
    global $api;
    return disposableCasRpc($api,$action,$data);
}
function flushCheck($test,$label) {
    global $checks;
    if(!$test)throw new RuntimeException('CAS_CREATE_EVIDENCE_FAILED '.$label);
    $checks++;
}
// RU: Права пользователей не изменяются; проверяем только штатный сброс.
// EN: No ACL mutations, only a native permissions cache flush.
function flushGuard(){
    $state=flushRpc('get_site_state');
    return array('observed_site_revision'=>(int)$state['site_revision'],
        'strict_site_revision'=>true,'read_set'=>array());
}
$before=flushRpc('get_site_state');
$request=array('_runtime_preconditions'=>flushGuard());
$result=flushRpc('flush_permissions',$request);
flushCheck(is_array($result) && array_key_exists('flushed',$result),
    'flush permissions returned native result');
flushCheck($result['flushed']===true,
    'native MODX permissions cache manager confirms successful flush');
$after=flushRpc('get_site_state');
flushCheck((int)$after['site_revision']
    ===(int)$request['_runtime_preconditions']['observed_site_revision']+1,
    'flush_permissions advanced revision exactly once');
$stale=false;
try{flushRpc('flush_permissions',$request);}
catch(ModxMCPClientException $e){
    $stale=strpos($e->getMessage(),'STALE_STATE')!==false;
}
flushCheck($stale,'flush_permissions old CAS denied');
$afterReplay=flushRpc('get_site_state');
flushCheck((int)$afterReplay['site_revision']
    ===(int)$after['site_revision'],
    'stale permissions cache flush did not advance revision');
echo 'CAS_FLUSH_PERMISSIONS_NATIVE_STALE_REPLAY_OK'.PHP_EOL;
echo 'CAS_FLUSH_PERMISSIONS_EVIDENCE_OK checks='.$checks
    .' platform='.($is2?'modx2':'modx3').PHP_EOL;
