<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Проверка ClientConfig 2.5.0: глобальные и три контекста без Manager Save.
 * EN: Real ClientConfig 2.5.0 global and three-context readback without Manager Save.
 */
/**
 * Source of the upstream ClientConfig addon is provisioned into this disposable
 * MODX fixture by GitHub CI. DB tables are created through the MODX xPDO
 * manager, never raw SQL or on any installed/production site.
 */
if (PHP_SAPI !== 'cli' || getenv('MODXMCP_DISPOSABLE_CI') !== '1'
    || strpos((string)getenv('MODX_CONFIG_CORE'),'/home/runner/') !== 0
    || !is_file((string)getenv('MODX_CONFIG_CORE'))
    || strpos((string)getenv('RUNNER_TEMP'),'/home/runner/') !== 0) {
    fwrite(STDERR,"Refusing ClientConfig addon E2E outside disposable CI.\n");
    exit(3);
}
require_once getenv('MODX_CONFIG_CORE');
$is2=!is_file(MODX_CORE_PATH.'src/Revolution/modX.php');
if($is2) {
    require_once MODX_CORE_PATH.'model/modx/modx.class.php';
    $modx=new modX();
} else {
    require_once MODX_CORE_PATH.'vendor/autoload.php';
    $modx=\MODX\Revolution\modX::getInstance();
}
$modx->initialize('mgr');
$modx->setOption('modxmcp.disabled_groups','');
$modx->setOption('clientconfig.context_aware',true);
$modx->setOption('clientconfig.clear_cache',false);
$path=MODX_CORE_PATH.'components/clientconfig/model/';
if(!is_file($path.'clientconfig/clientconfig.class.php')) {
    throw new RuntimeException('Pinned ClientConfig model source absent');
}
if(!$modx->addPackage('clientconfig',$path)) {
    throw new RuntimeException('ClientConfig xPDO package cannot be loaded');
}
$manager=$modx->getManager();
foreach(array('cgSetting','cgContextValue') as $class) {
    if(!$manager->createObjectContainer($class)) {
        throw new RuntimeException('Cannot prepare disposable addon model '.$class);
    }
}
require_once $path.'clientconfig/clientconfig.class.php';
require_once MODX_CORE_PATH.'components/modxmcp/model/modxmcp.class.php';
require_once __DIR__.'/test.cas-mcp-helper.php';
$client=new ClientConfig($modx);
$api=new modxMCP($modx);
$uid=bin2hex(random_bytes(5));
$key='cicache'.$uid;
$other='cictx'.$uid;
$createdContext=false;
$createdSetting=false;
$checks=0;
function clientConfigLiveCheck($ok,$label) {
    global $checks;
    if(!$ok) throw new RuntimeException('CLIENTCONFIG_ADDON_E2E_FAILED: '.$label);
    $checks++;
}
function clientConfigLiveRpc($action,array $data=array()) {
    global $modx,$api;
    $modx->setOption('modxmcp.disabled_groups','');
    $modx->setOption('clientconfig.context_aware',true);
    $modx->setOption('clientconfig.clear_cache',false);
    return disposableCasRpc($api,$action,$data);
}
function clientConfigLiveValue($context,$key) {
    global $client,$modx;
    $modx->setOption('clientconfig.context_aware',true);
    $options=$client->getSettings($context);
    return array_key_exists($key,$options)?$options[$key]:null;
}
try {
    clientConfigLiveRpc('create_context',array(
        'key'=>$other,'name'=>'ClientConfig disposable '.$uid
    ));
    $createdContext=true;
    $result=clientConfigLiveRpc('clientconfig_create_setting',array(
        'key'=>$key,'label'=>'Disposable ClientConfig '.$uid,
        'xtype'=>'textfield','description'=>'','value'=>'global_old',
        'context_values'=>array(
            'web'=>'web_old','mgr'=>'mgr_old',$other=>'custom_old'
        )
    ));
    $createdSetting=true;
    clientConfigLiveCheck(isset($result['id']) && (int)$result['id']>0,
        'created setting through MCP');
    foreach(array('web'=>'web_old','mgr'=>'mgr_old',$other=>'custom_old') as $ctx=>$val) {
        clientConfigLiveCheck(clientConfigLiveValue($ctx,$key)===$val,
            'first addon getSettings in '.$ctx);
    }
    $global=$client->getSettings();
    clientConfigLiveCheck(isset($global[$key]) && $global[$key]==='global_old',
        'initial global value from upstream ClientConfig');
    $result=clientConfigLiveRpc('clientconfig_update_setting',array(
        'key'=>$key,'value'=>'global_new',
        'context_values'=>array('web'=>'web_new',$other=>'custom_new')
    ));
    clientConfigLiveCheck(is_array($result),'updated via MCP without Manager');
    clientConfigLiveCheck(clientConfigLiveValue('web',$key)==='web_new',
        'web context reads new value without Manager Save');
    clientConfigLiveCheck(clientConfigLiveValue('mgr',$key)==='mgr_old',
        'mgr override remains untouched');
    clientConfigLiveCheck(clientConfigLiveValue($other,$key)==='custom_new',
        'custom context reads independent new value');
    $global=$client->getSettings();
    clientConfigLiveCheck(isset($global[$key]) && $global[$key]==='global_new',
        'global setting is independently current');
    $result=clientConfigLiveRpc('clientconfig_update_setting',array(
        'key'=>$key,'context_values'=>array('web'=>null)
    ));
    clientConfigLiveCheck(clientConfigLiveValue('web',$key)==='global_new',
        'deleting web override falls back to global');
    clientConfigLiveCheck(clientConfigLiveValue('mgr',$key)==='mgr_old',
        'mgr remains independent after deleting web override');
    clientConfigLiveCheck(clientConfigLiveValue($other,$key)==='custom_new',
        'custom context remains independent after deleting web override');
    clientConfigLiveRpc('clientconfig_delete_setting',array('key'=>$key));
    $createdSetting=false;
    clientConfigLiveCheck(clientConfigLiveValue('web',$key)===null,
        'addon no longer reads deleted setting after cache invalidation');
    echo 'CLIENTCONFIG_INSTALLED_ADDON_MCP_CONTEXT_E2E_OK platform='
        .($is2?'modx2':'modx3').' checks='.$checks.PHP_EOL;
} finally {
    if($createdSetting) {
        try { clientConfigLiveRpc('clientconfig_delete_setting',array('key'=>$key)); }
        catch(\Throwable $ignored) {}
    }
    if($createdContext) {
        try { clientConfigLiveRpc('delete_context',array('key'=>$other)); }
        catch(\Throwable $ignored) {}
    }
}
