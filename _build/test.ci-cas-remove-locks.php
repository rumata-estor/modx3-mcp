<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Отдельное CAS-доказательство создания безопасной проверки remove_locks на одноразовой установке.
 * EN: Empty disposable installation remove_locks CAS and stale replay.
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
function locksRpc($action,array $data=array()) {
    global $api;
    return disposableCasRpc($api,$action,$data);
}
function locksCheck($test,$label) {
    global $checks;
    if(!$test)throw new RuntimeException('CAS_CREATE_EVIDENCE_FAILED '.$label);
    $checks++;
}
// RU: Установка одноразовая; не создаём и не удаляем внешние lock-объекты.
// EN: Disposable MODX installation only; no unrelated lock state is used.
$state=locksRpc('get_site_state');
$guard=array('observed_site_revision'=>(int)$state['site_revision'],
    'strict_site_revision'=>true,'read_set'=>array());
$request=array('_runtime_preconditions'=>$guard);
$result=locksRpc('remove_locks',$request);
locksCheck($result!==false,'remove locks returned non-false MCP result');
$after=locksRpc('get_site_state');
locksCheck((int)$after['site_revision']===(int)$state['site_revision']+1,
    'empty installation remove_locks incremented CAS once');
$stale=false;
try{locksRpc('remove_locks',$request);}
catch(ModxMCPClientException $e){
    $stale=strpos($e->getMessage(),'STALE_STATE')!==false;
}
locksCheck($stale,'stale remove_locks replay denied');
$afterReplay=locksRpc('get_site_state');
locksCheck((int)$afterReplay['site_revision']===(int)$after['site_revision'],
    'stale replay did not consume revision');
echo 'CAS_REMOVE_LOCKS_EMPTY_INSTALL_STALE_REPLAY_OK checks='.$checks
    .' platform='.($is2?'modx2':'modx3').PHP_EOL;
