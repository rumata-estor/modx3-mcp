<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Отдельное CAS-доказательство создания отказа операций без необязательных компонентов.
 * EN: Missing optional addons must fail safely before any mutation.
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
function addonRpc($action,array $data=array()) {
    global $api;
    return disposableCasRpc($api,$action,$data);
}
function addonCheck($test,$label) {
    global $checks;
    if(!$test)throw new RuntimeException('CAS_CREATE_EVIDENCE_FAILED '.$label);
    $checks++;
}
// RU: Только стандартная чистая установка без указанных дополнений.
// EN: Only a stock disposable installation with no optional addon packages.
foreach(array('migx','minishop2','virtualpage','versionx') as $addon){
    addonCheck(!is_dir(MODX_CORE_PATH.'components/'.$addon.'/'),
        'optional addon not installed '.$addon);
}
$fixture=bin2hex(random_bytes(5));
$cases=array(
    array('migx_create_config',array('name'=>'mcp_missing_'.$fixture),
        'MIGX is not installed'),
    array('ms2_create_link_type',array('name'=>'mcp_missing_'.$fixture),
        'miniShop2'),
    array('virtualpage_create_event',array('name'=>'mcp_missing_'.$fixture),
        'VirtualPage'),
    array('versionx_revert_version',array('type'=>'resource','content_id'=>99999999,
        'version_id'=>99999999,'confirm'=>true),'VersionX'),
);
foreach($cases as $case){
    $before=addonRpc('get_site_state');
    $refused=false;$message='';
    try{addonRpc($case[0],$case[1]);}
    catch(ModxMCPClientException $e){
        $refused=true;$message=$e->getMessage();
    }
    addonCheck($refused,'missing addon mutation refused: '.$case[0]);
    addonCheck(stripos($message,$case[2])!==false,
        'missing addon error identifies dependency: '.$case[0]);
    $after=addonRpc('get_site_state');
    addonCheck((int)$after['site_revision']===(int)$before['site_revision'],
        'missing addon did not consume CAS revision: '.$case[0]);
    echo 'MISSING_ADDON_DENIED_SAFE action='.$case[0].PHP_EOL;
}
echo 'MISSING_ADDON_DENIAL_OK checks='.$checks
    .' platform='.($is2?'modx2':'modx3').PHP_EOL;
