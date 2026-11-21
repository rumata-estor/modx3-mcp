<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Проверка «ci-external-cas-worker»; тестовый код не запускается на production.
 * EN: «ci-external-cas-worker» regression test; not executed in production.
 */
/**
 * Independent writer for a disposable external CAS race.
 * Only website change is through modxMCP::processRequest().
 */
if (PHP_SAPI!=='cli' || getenv('MODXMCP_DISPOSABLE_CI')!=='1'
    || strpos((string)getenv('MODX_CONFIG_CORE'),'/home/runner/')!==0
    || !is_file((string)getenv('MODX_CONFIG_CORE')) || $argc!==7) {
    fwrite(STDERR,"Disposable CI worker only\n");exit(3);
}
$path=$argv[1];$revision=(int)$argv[2];$before=$argv[3];
$after=$argv[4];$ready=$argv[5];$gate=$argv[6];
if (!preg_match('#^cas-fault-[a-f0-9]{14}/renamed[.]txt$#',$path)
    || $revision<0 || strpos($ready,'/home/runner/')!==0
    || strpos($gate,'/home/runner/')!==0) exit(3);
require_once getenv('MODX_CONFIG_CORE');
$is2=!is_file(MODX_CORE_PATH . 'src/Revolution/modX.php');
if ($is2) {
    require_once MODX_CORE_PATH . 'model/modx/modx.class.php';
    $modx=new modX();
} else {
    require_once MODX_CORE_PATH . 'vendor/autoload.php';
    $modx=\MODX\Revolution\modX::getInstance();
}
$modx->initialize('mgr');
require_once MODX_CORE_PATH . 'components/modxmcp/model/modxmcp.class.php';
$api=new modxMCP($modx);
file_put_contents($ready,'ready');
$deadline=microtime(true)+15;
while (!is_file($gate) && microtime(true)<$deadline) usleep(10000);
if (!is_file($gate)) {fwrite(STDERR,"CAS_RACE_GATE_TIMEOUT\n");exit(4);}
try {
    $result=$api->processRequest('update_media_file','',array(
        'source'=>1,'path'=>$path,'content'=>$after,
        '_runtime_preconditions'=>array(
            'observed_site_revision'=>$revision,
            'strict_site_revision'=>true,
            'read_set'=>array(),
        ),
    ));
    if (!isset($result['_site_revision'])
        || (int)$result['_site_revision']!==$revision+1)
        throw new RuntimeException('Incorrect successful external CAS revision');
    echo 'EXTERNAL_CAS_WORKER_OK'.PHP_EOL;
} catch (ModxMCPClientException $e) {
    if (strpos($e->getMessage(),'STALE_STATE')===false) throw $e;
    echo 'EXTERNAL_CAS_WORKER_STALE'.PHP_EOL;
}
