<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Два независимых PHP-процесса резервируют одно намерение в MariaDB.
 * EN: Two independent PHP workers contend on one operation intent.
 */
if(PHP_SAPI!=='cli' || getenv('MODXMCP_DISPOSABLE_CI')!=='1'
    || strpos((string)getenv('MODX_CONFIG_CORE'),'/home/runner/')!==0
    || $argc!==6)exit(3);
$table=(string)$argv[1];
$id=(string)$argv[2];
$digest=(string)$argv[3];
$ready=(string)$argv[4];
$gate=(string)$argv[5];
if(!preg_match('/\Aci_mcp_operations_[0-9a-f]{10}\z/D',$table)
    || strpos($ready,'/home/runner/')!==0
    || strpos($gate,'/home/runner/')!==0)exit(3);
require_once __DIR__.'/../core/components/modxmcp/src/Security/MutationOperationLedger.php';
$pdo=new PDO('mysql:host=127.0.0.1;dbname=modx_build;charset=utf8mb4',
    'modx_ci','ci-throwaway-only-user',array(
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES=>false
    ));
file_put_contents($ready,'ready');
$limit=microtime(true)+15;
while(!is_file($gate)&&microtime(true)<$limit)usleep(10000);
if(!is_file($gate))exit(4);
$ledger=new \ModxMcp\Security\MutationOperationLedger($pdo,$table);
$result=$ledger->claim($id,$digest);
echo $result['fresh']?'OPERATION_WORKER_FIRST':'OPERATION_WORKER_REPLAY';
echo PHP_EOL;
