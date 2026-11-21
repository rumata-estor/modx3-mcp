<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Проверка «ci-manager-external-edit»; тестовый код не запускается на production.
 * EN: «ci-manager-external-edit» regression test; not executed in production.
 */
/**
 * Disposable-only ADVERSARIAL MODX Manager change outside MCP.
 *
 * This is an explicit simulation of an out-of-band admin edit, not a
 * functional MCP client operation. Resource creation, signed updates,
 * stale rejection, readbacks and deletion in the parent test use MCP.
 * No raw SQL and no access to any installed or production site.
 */
if(PHP_SAPI!=='cli' || getenv('MODXMCP_DISPOSABLE_CI')!=='1'
    || strpos((string)getenv('MODX_CONFIG_CORE'),'/home/runner/')!==0
    || !is_file((string)getenv('MODX_CONFIG_CORE'))
    || $argc!==2 || !preg_match('/^[1-9][0-9]*$/D',$argv[1]))exit(3);
require_once getenv('MODX_CONFIG_CORE');
$is2=!is_file(MODX_CORE_PATH.'src/Revolution/modX.php');
if($is2){
    require_once MODX_CORE_PATH.'model/modx/modx.class.php';
    $modx=new modX();
}else{
    require_once MODX_CORE_PATH.'vendor/autoload.php';
    $modx=\MODX\Revolution\modX::getInstance();
}
$modx->initialize('mgr');
$cls=$is2?'modResource':'MODX\\Revolution\\modResource';
$item=$modx->getObject($cls,(int)$argv[1]);
if(!$item)throw new RuntimeException('External Manager fixture target missing');
if((int)$item->get('published')!==0 || (int)$item->get('hidemenu')!==1
    || strpos((string)$item->get('pagetitle'),'Ed25519 protected CI ')!==0){
    throw new RuntimeException('Refusing external edit outside disposable fixture');
}
$item->set('content','MANAGER_CHANGED');
if(!$item->save())throw new RuntimeException('Disposable external admin edit failed');
echo 'DISPOSABLE_MANAGER_OUT_OF_BAND_EDIT_OK'.PHP_EOL;
