<?php
/**
 * CI-only loopback TLS reverse-proxy trust settings for disposable MODX.
 * Not part of any installed package. No business table is accessed here.
 * HTTPS remains required, and only 127.0.0.1 is trusted as a proxy peer.
 */
if (PHP_SAPI !== 'cli'
    || getenv('MODXMCP_DISPOSABLE_CI') !== '1'
    || strpos((string)getenv('MODX_CONFIG_CORE'),'/home/runner/') !== 0
    || !is_file((string)getenv('MODX_CONFIG_CORE'))
    || !in_array((string)($argv[1] ?? ''),array('enable','disable'),true)
    || !in_array((string)($argv[2] ?? ''),array(
        'modx2-alex-palochkin','modx3-alex-palochkin'),true)
    || $argc !== 3) {
    fwrite(STDERR,"Refusing protected v4 settings outside disposable CI.\n");
    exit(3);
}
$mode=$argv[1];
$site=$argv[2];
require_once getenv('MODX_CONFIG_CORE');
$is2=!is_file(MODX_CORE_PATH.'src/Revolution/modX.php');
if ($is2) {
    require_once MODX_CORE_PATH.'model/modx/modx.class.php';
    $modx=new modX();
} else {
    require_once MODX_CORE_PATH.'vendor/autoload.php';
    $modx=\MODX\Revolution\modX::getInstance();
}
$modx->initialize('mgr');
$class=$is2?'modSystemSetting':'MODX\\Revolution\\modSystemSetting';
if (($is2?'modx2-alex-palochkin':'modx3-alex-palochkin')!==$site) {
    throw new RuntimeException('CI site identity/platform mismatch');
}
function ciSetting($key,$value,$create=false) {
    global $modx,$class;
    $obj=$modx->getObject($class,array('key'=>$key));
    if(!$obj && $create) {
        $obj=$modx->newObject($class);
        $obj->set('key',$key);
        $obj->set('namespace','modxmcp');
        $obj->set('xtype','textfield');
        $obj->set('area','modxmcp:security');
    }
    if (!$obj)throw new RuntimeException('CI security setting missing: '.$key);
    $obj->set('value',(string)$value);
    if(!$obj->save())throw new RuntimeException('CI protected proxy setup failed');
}
if($mode==='enable') {
    ciSetting('modxmcp.permit_site_id',$site);
    ciSetting('modxmcp.require_https','1');
    ciSetting('modxmcp.trust_proxy_https','1');
    ciSetting('modxmcp.trusted_proxy_ips','127.0.0.1',true);
    ciSetting('modxmcp.allowed_ips','127.0.0.1');
} else {
    // The CI database is discarded; rollback protects the temporary fixture.
    ciSetting('modxmcp.trust_proxy_https','0');
    ciSetting('modxmcp.trusted_proxy_ips','',true);
    ciSetting('modxmcp.allowed_ips','');
    ciSetting('modxmcp.permit_site_id','ci-http-'.($is2?'modx2':'modx3'));
}
$cache=$modx->getCacheManager();
if($cache)$cache->refresh();
echo $mode==='enable'
    ? "MODXMCP_DISPOSABLE_TLS_REVERSE_PROXY_CONFIGURED\n"
    : "MODXMCP_DISPOSABLE_TLS_REVERSE_PROXY_RESET\n";
