<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Отдельное CAS-доказательство создания очистки временного cache key.
 * EN: Disposable cache refresh and stale CAS replay evidence.
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
function cacheRpc($action,array $data=array()) {
    global $api;
    return disposableCasRpc($api,$action,$data);
}
function cacheCheck($test,$label) {
    global $checks;
    if(!$test)throw new RuntimeException('CAS_CREATE_EVIDENCE_FAILED '.$label);
    $checks++;
}
$fixture=bin2hex(random_bytes(6));
$key='mcp_disposable_cache_'.$fixture;
$manager=$modx->getCacheManager();
cacheCheck($manager!==null,'disposable cache manager available');
$opts=array('cache_key'=>'default');
function cacheGuard(){
    $state=cacheRpc('get_site_state');
    return array('observed_site_revision'=>(int)$state['site_revision'],
        'strict_site_revision'=>true,'read_set'=>array());
}
try{
    // RU: Одноразовый ключ создаём штатным cache manager MODX.
    // EN: Seed a disposable cache fixture with native MODX cache API only.
    $cachePayload='CAS_FIXTURE_'.$fixture;
    cacheCheck($manager->set($key,$cachePayload,300,$opts)!==false,
        'disposable key seeded');
    cacheCheck($manager->get($key,$opts)==='CAS_FIXTURE_'.$fixture,
        'cache seed independently readable');
    $request=array('_runtime_preconditions'=>cacheGuard());
    $result=cacheRpc('clear_cache',$request);
    cacheCheck(!empty($result['cleared']),'MCP cache clear returned success');
    cacheCheck($manager->get($key,$opts)===null,
        'disposable cache key removed by MCP clear');
    $after=cacheRpc('get_site_state');
    cacheCheck((int)$after['site_revision']
        ===(int)$request['_runtime_preconditions']['observed_site_revision']+1,
        'clear_cache advanced site revision once');
    $stale=false;
    try {cacheRpc('clear_cache',$request);}
    catch(ModxMCPClientException $e){
        $stale=strpos($e->getMessage(),'STALE_STATE')!==false;
    }
    cacheCheck($stale,'stale clear_cache CAS replay denied');
    $post=cacheRpc('get_site_state');
    cacheCheck((int)$post['site_revision']===(int)$after['site_revision'],
        'rejected clear_cache replay consumed no revision');
    cacheCheck($manager->get($key,$opts)===null,
        'stale cache replay left key absent');
    echo 'CAS_CLEAR_CACHE_DISPOSABLE_KEY_STALE_REPLAY_OK'.PHP_EOL;
}finally{
    // RU: Уборка только одноразового ключа тестового стенда.
    // EN: Cleanup only our disposable cache key.
    $manager->delete($key,$opts);
}
echo 'CAS_CLEAR_CACHE_EVIDENCE_OK checks='.$checks
    .' platform='.($is2?'modx2':'modx3').PHP_EOL;
