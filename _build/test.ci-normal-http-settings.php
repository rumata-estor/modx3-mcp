<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Включает обычный HTTP API только на одноразовом CI MODX.
 * EN: Enables ordinary HTTP API exclusively on disposable CI MODX.
 */
/**
 * RU: Тестовая настройка через штатный объект MODX, без прямых SQL-записей.
 * EN: Disposable setup via MODX objects, not direct SQL writes.
 */
if (PHP_SAPI !== 'cli' || getenv('MODXMCP_DISPOSABLE_CI') !== '1'
    || strpos((string)getenv('MODX_CONFIG_CORE'),'/home/runner/')!==0
    || !is_file((string)getenv('MODX_CONFIG_CORE')) || $argc!==3
    || !in_array($argv[1],array('enable','disable'),true)
    || strpos((string)$argv[2],'/home/runner/')!==0) {
    fwrite(STDERR,"Refusing ordinary HTTP setup outside disposable CI.\n");
    exit(3);
}
$operation=$argv[1];
$tokenFile=$argv[2];
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
$cls=$is2?'modSystemSetting':'MODX\\Revolution\\modSystemSetting';
$enabled=$modx->getObject($cls,array('key'=>'modxmcp.enabled'));
$mode=$modx->getObject($cls,array('key'=>'modxmcp.external_authorization_required'));
if (!$enabled || !$mode || (string)$mode->get('value')!=='0') {
    throw new RuntimeException('Ordinary API CI requires independent authorization disabled.');
}
if ($operation==='enable') {
    $token=$modx->getObject($cls,array('key'=>'modxmcp.api_token'));
    $value=$token ? (string)$token->get('value') : '';
    if (!preg_match('/\A[a-f0-9]{64}\z/D',$value)) {
        throw new RuntimeException('Disposable API token unavailable.');
    }
    if (file_put_contents($tokenFile,$value) !== 64 || !chmod($tokenFile,0600)) {
        throw new RuntimeException('Could not prepare disposable token fixture.');
    }
    $enabled->set('value','1');
} else {
    $enabled->set('value','0');
}
if (!$enabled->save()) throw new RuntimeException('Disposable API toggle save failed.');
$manager=$modx->getCacheManager();
if ($manager) $manager->refresh();
if ($operation==='disable' && is_file($tokenFile)) @unlink($tokenFile);
echo 'MODXMCP_ORDINARY_HTTP_DISPOSABLE_'.$operation.PHP_EOL;
