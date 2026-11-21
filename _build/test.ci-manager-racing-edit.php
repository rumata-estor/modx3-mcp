<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Проверка «ci-manager-racing-edit»; тестовый код не запускается на production.
 * EN: «ci-manager-racing-edit» regression test; not executed in production.
 */
/**
 * One-use adversarial MODX Manager process racing a signed MCP write.
 *
 * Deliberately edits a hidden CI fixture outside MCP, because the invariant
 * being tested is protection from an independent human Manager transaction.
 * All normal business mutations are still performed through MODX MCP.
 */
if (PHP_SAPI !== 'cli'
    || getenv('MODXMCP_DISPOSABLE_CI') !== '1'
    || strpos((string)getenv('MODX_CONFIG_CORE'),'/home/runner/') !== 0
    || !is_file((string)getenv('MODX_CONFIG_CORE'))
    || $argc !== 3
    || !preg_match('/\A[1-9][0-9]*\z/D',$argv[1])
    || strpos((string)$argv[2],'/home/runner/') !== 0) {
    fwrite(STDERR,'Refusing racing Manager fixture outside disposable CI.'.PHP_EOL);
    exit(3);
}
$id=(int)$argv[1];
$ready=(string)$argv[2];
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
$class=$is2?'modResource':'MODX\\Revolution\\modResource';
$resource=$modx->getObject($class,$id);
if(!$resource || (int)$resource->get('published') !== 0
    || (int)$resource->get('hidemenu') !== 1
    || strpos((string)$resource->get('pagetitle'),'Ed25519 protected CI ') !== 0) {
    throw new RuntimeException('Racing edit target is not a disposable fixture.');
}
if($modx->beginTransaction()===false) {
    throw new RuntimeException('Could not begin Manager transaction.');
}
$committed=false;
try {
    $resource->set('content','MANAGER_RACING');
    if(!$resource->save())throw new RuntimeException('Manager update failed.');
    // The row is locked by this independent Manager DB transaction.
    if(file_put_contents($ready,'row-updated-uncommitted')===false) {
        throw new RuntimeException('Cannot signal Manager row lock.');
    }
    // Deliberate overlap: the main protected MCP request must wait for the
    // Manager transaction to finish before rechecking target fingerprint.
    usleep(3000000);
    if($modx->commit()===false)throw new RuntimeException('Manager commit failed.');
    $committed=true;
    echo 'DISPOSABLE_MANAGER_ROW_RACE_COMMITTED'.PHP_EOL;
} finally {
    if(!$committed)$modx->rollback();
}
