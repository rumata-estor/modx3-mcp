<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Проверка «ci-permit-nonce-worker»; тестовый код не запускается на production.
 * EN: «ci-permit-nonce-worker» regression test; not executed in production.
 */
/**
 * Disposable-only nonce race worker: each independent PHP process uses its
 * own PDO connection. No site mutation and no MODX model objects.
 */
if (PHP_SAPI !== 'cli' || getenv('MODXMCP_DISPOSABLE_CI')!=='1'
    || strpos((string)getenv('MODX_CONFIG_CORE'),'/home/runner/')!==0
    || ($argc !== 8 && $argc !== 9)) exit(3);
require_once __DIR__.'/../core/components/modxmcp/src/Security/PermitNonceLedger.php';
$table=(string)$argv[1];
$nonce=(string)$argv[2];
$issued=(int)$argv[3];
$expires=(int)$argv[4];
$now=(int)$argv[5];
$ready=(string)$argv[6];
$gate=(string)$argv[7];
if (!preg_match('/\Aci_modxmcp_nonce_[0-9a-f]{12}\z/D',$table)
    || strpos($ready,'/home/runner/')!==0
    || strpos($gate,'/home/runner/')!==0) exit(3);
$pdo=new PDO(
    'mysql:host=127.0.0.1;dbname=modx_build;charset=utf8mb4',
    'modx_ci','ci-throwaway-only-user',
    array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION)
);
file_put_contents($ready,'ready');
$deadline=microtime(true)+15;
while(!is_file($gate)&&microtime(true)<$deadline) usleep(10000);
if(!is_file($gate)) exit(4);
$ledger=new ModxMcp\Security\PermitNonceLedger($pdo,$table);
try {
    $ledger->reserve($nonce,$issued,$expires,$now);
    if ($argc===9 && $argv[8]==='crash') {
        // Simulates the signing client disconnecting or the mutation
        // failing after the replay nonce was durably reserved.
        exit(71);
    }
    echo 'NONCE_WORKER_RESERVED'.PHP_EOL;
} catch (RuntimeException $e) {
    if (strpos($e->getMessage(),'Permit replayed or nonce ledger unavailable.')===false)
        throw $e;
    echo 'NONCE_WORKER_DENIED'.PHP_EOL;
}
