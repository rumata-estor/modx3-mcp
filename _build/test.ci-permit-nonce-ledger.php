<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Проверка «ci-permit-nonce-ledger»; тестовый код не запускается на production.
 * EN: «ci-permit-nonce-ledger» regression test; not executed in production.
 */
/**
 * Offline DB durability/replay test on disposable GitHub Actions MariaDB.
 * Creates/drops one random auth-ledger table, NEVER touches MODX data.
 */
if(PHP_SAPI!=='cli' || getenv('MODXMCP_DISPOSABLE_CI')!=='1'
   || strpos((string)getenv('MODX_CONFIG_CORE'),'/home/runner/')!==0
   || !is_file((string)getenv('MODX_CONFIG_CORE'))) exit(3);
require_once __DIR__.'/../core/components/modxmcp/src/Security/PermitNonceLedger.php';
use ModxMcp\Security\PermitNonceLedger;
$pdo=new PDO(
    'mysql:host=127.0.0.1;dbname=modx_build;charset=utf8mb4',
    'modx_ci','ci-throwaway-only-user',
    array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION)
);
$table='ci_modxmcp_nonce_'.bin2hex(random_bytes(6));
$checks=0;
function assertNonce($condition,$message){
    global $checks;
    if(!$condition)throw new RuntimeException('NONCE_LEDGER_FAIL '.$message);
    $checks++;
}
function nonceMustFail($action,$message){
    global $checks;
    try { $action(); } catch (\Throwable $e) {
        $checks++;return;
    }
    throw new RuntimeException('NONCE_LEDGER_FAIL unexpectedly accepted '.$message);
}
try {
    $pdo->exec('CREATE TABLE `'.$table.'` ('
        .' `nonce_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,'
        .' `issued_at` BIGINT UNSIGNED NOT NULL,'
        .' `expires_at` BIGINT UNSIGNED NOT NULL,'
        .' PRIMARY KEY (`nonce_hash`)'
        .') ENGINE=InnoDB');
    $ledger=new PermitNonceLedger($pdo,$table);
    $now=time();
    $first=bin2hex(random_bytes(16));
    $issued=$now-1;$expires=$now+70;
    assertNonce($ledger->reserve($first,$issued,$expires,$now)===true,'first reservation');
    nonceMustFail(function()use($ledger,$first,$issued,$expires,$now){
        $ledger->reserve($first,$issued,$expires,$now);
    },'replay same process');
    $second=new PermitNonceLedger(new PDO(
        'mysql:host=127.0.0.1;dbname=modx_build;charset=utf8mb4',
        'modx_ci','ci-throwaway-only-user',
        array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION)
    ),$table);
    nonceMustFail(function()use($second,$first,$issued,$expires,$now){
        $second->reserve($first,$issued,$expires,$now);
    },'replay new connection');

    nonceMustFail(function()use($ledger,$issued,$expires,$now){
        $ledger->reserve('../escape',$issued,$expires,$now);
    },'noncanonical nonce');
    nonceMustFail(function()use($ledger,$first,$issued,$expires,$now){
        $ledger->reserve($first,$issued,$expires,$now+90);
    },'expired nonce');
    nonceMustFail(function()use($ledger,$first,$issued,$expires,$now){
        $ledger->reserve($first,$issued+100,$expires+200,$now);
    },'future nonce');
    $pdo->beginTransaction();
    try {
        nonceMustFail(function()use($ledger,$issued,$expires,$now){
            $ledger->reserve(bin2hex(random_bytes(16)),$issued,$expires,$now);
        },'same mutation transaction');
    } finally { $pdo->rollBack(); }
    nonceMustFail(function()use($pdo,$table){
        new PermitNonceLedger($pdo,'bad;DROP TABLE '.$table);
    },'unsafe table name');
    $missing=new PermitNonceLedger($pdo,'ci_modxmcp_nonce_missing');
    nonceMustFail(function()use($missing,$issued,$expires,$now){
        $missing->reserve(bin2hex(random_bytes(16)),$issued,$expires,$now);
    },'missing nonce table does not fail open');

    $race=bin2hex(random_bytes(16));
    $gate=getenv('RUNNER_TEMP').'/nonce-gate-'.bin2hex(random_bytes(6));
    $workers=array();
    for($i=0;$i<2;$i++){
        $ready=$gate.'.ready.'.$i;
        $cmd=array(
            PHP_BINARY,__DIR__.'/test.ci-permit-nonce-worker.php',
            $table,$race,(string)$issued,(string)$expires,(string)$now,$ready,$gate
        );
        $pipes=array();
        $process=proc_open($cmd,array(
            0=>array('pipe','r'),1=>array('pipe','w'),2=>array('pipe','w')
        ),$pipes);
        assertNonce(is_resource($process),'start independent worker');
        fclose($pipes[0]);
        $workers[]=array($process,$pipes,$ready);
    }
    $deadline=microtime(true)+15;
    while((!is_file($workers[0][2]) || !is_file($workers[1][2]))
        && microtime(true)<$deadline)usleep(10000);
    assertNonce(is_file($workers[0][2])&&is_file($workers[1][2]),'both workers ready');
    file_put_contents($gate,'go');
    $outcomes=array();
    foreach($workers as $worker){
        $stdout=stream_get_contents($worker[1][1]);
        $stderr=stream_get_contents($worker[1][2]);
        fclose($worker[1][1]);fclose($worker[1][2]);
        $exit=proc_close($worker[0]);
        assertNonce($exit===0,'worker exited cleanly '.$stderr);
        $outcomes[]=trim($stdout);
    }
    assertNonce(count(array_filter($outcomes,function($x){
        return $x==='NONCE_WORKER_RESERVED';
    }))===1,'exactly one concurrent reservation');
    assertNonce(count(array_filter($outcomes,function($x){
        return $x==='NONCE_WORKER_DENIED';
    }))===1,'exactly one concurrent replay denied');
    $rows=(int)$pdo->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn();
    assertNonce($rows===2,'only first and one racer ever persisted');
    nonceMustFail(function()use($ledger,$race,$issued,$expires,$now){
        $ledger->reserve($race,$issued,$expires,$now);
    },'raced nonce still denied after workers exit');
    // Crash-after-reserve simulation: the first worker exits without a
    // mutation, and an independent worker must still be unable to reuse
    // the nonce. A nonce cannot be returned on an ambiguous failure.
    $crashNonce=bin2hex(random_bytes(16));
    $crashGate=getenv('RUNNER_TEMP').'/nonce-crash-'.bin2hex(random_bytes(6));
    $crashReady=$crashGate.'.ready';
    $cmd=array(PHP_BINARY,__DIR__.'/test.ci-permit-nonce-worker.php',
        $table,$crashNonce,(string)$issued,(string)$expires,(string)$now,
        $crashReady,$crashGate,'crash');
    $pipes=array();
    $crashing=proc_open($cmd,array(
        0=>array('pipe','r'),1=>array('pipe','w'),2=>array('pipe','w')
    ),$pipes);
    assertNonce(is_resource($crashing),'start crash worker');
    fclose($pipes[0]);
    $deadline=microtime(true)+15;
    while(!is_file($crashReady) && microtime(true)<$deadline)usleep(10000);
    assertNonce(is_file($crashReady),'crash worker ready');
    file_put_contents($crashGate,'go');
    $crashOutput=stream_get_contents($pipes[1]);
    $crashError=stream_get_contents($pipes[2]);
    fclose($pipes[1]);fclose($pipes[2]);
    $crashExit=proc_close($crashing);
    assertNonce($crashExit===71,'worker crashed immediately after nonce reservation '.$crashError);
    nonceMustFail(function()use($ledger,$crashNonce,$issued,$expires,$now){
        $ledger->reserve($crashNonce,$issued,$expires,$now);
    },'replay after simulated worker crash');
    $rows=(int)$pdo->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn();
    assertNonce($rows===3,'crashed worker nonce remained durably consumed');
    echo 'ED25519_NONCE_LEDGER_DISPOSABLE_OK checks='.$checks
      .' concurrent=1-winner replay=denied survives-failure=yes'.PHP_EOL;
} finally {
    $pdo->exec('DROP TABLE IF EXISTS `'.$table.'`');
}
