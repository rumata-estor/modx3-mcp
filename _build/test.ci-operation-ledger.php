<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Одноразовый MariaDB-тест журнала намерений с потерей ответа.
 * EN: Disposable MariaDB operation-intent journal and lost-response test.
 */
if (PHP_SAPI!=='cli' || getenv('MODXMCP_DISPOSABLE_CI')!=='1'
    || strpos((string)getenv('MODX_CONFIG_CORE'),'/home/runner/')!==0
    || !is_file((string)getenv('MODX_CONFIG_CORE'))) {
    fwrite(STDERR,"Refusing journal test outside disposable CI.\n");
    exit(3);
}
require_once __DIR__.'/../core/components/modxmcp/src/Security/MutationOperationLedger.php';
use ModxMcp\Security\MutationOperationLedger;
$dsn='mysql:host=127.0.0.1;dbname=modx_build;charset=utf8mb4';
$credentials=array('modx_ci','ci-throwaway-only-user');
$options=array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_PERSISTENT=>false);
$pdo=new PDO($dsn,$credentials[0],$credentials[1],$options);
$table='ci_mcp_operations_'.bin2hex(random_bytes(5));
$checks=0;
function opCheck($yes,$what){
    global $checks;
    if (!$yes)throw new RuntimeException('OPERATION_JOURNAL_FAIL '.$what);
    $checks++;
}
function opReject($call,$what){
    global $checks;
    try {call_user_func($call);}
    catch(Throwable $e){$checks++;return;}
    throw new RuntimeException('OPERATION_JOURNAL_FAIL accepted '.$what);
}
function makeOpId(){
    $h=bin2hex(random_bytes(16));
    return substr($h,0,8).'-'.substr($h,8,4).'-4'.substr($h,13,3)
        .'-8'.substr($h,17,3).'-'.substr($h,20,12);
}
try {
    $pdo->exec('CREATE TABLE `'.$table.'` ('
        .' operation_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,'
        .' request_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,'
        .' state VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,'
        .' created_at BIGINT UNSIGNED NOT NULL,'
        .' updated_at BIGINT UNSIGNED NOT NULL,'
        .' PRIMARY KEY(operation_hash)'
        .') ENGINE=InnoDB');
    $ledger=new MutationOperationLedger($pdo,$table);
    $second=new MutationOperationLedger(new PDO(
        $dsn,$credentials[0],$credentials[1],$options),$table);
    $digest=MutationOperationLedger::requestDigest(
        'disposable-site','create_resource','resource',array(
            'pagetitle'=>'Recovery case',
            '_runtime_preconditions'=>array(
                'observed_site_revision'=>20,'strict_site_revision'=>true
            )
        )
    );
    $sameDigest=MutationOperationLedger::requestDigest(
        'disposable-site','create_resource','resource',array(
            '_runtime_preconditions'=>array(
                'strict_site_revision'=>true,'observed_site_revision'=>20
            ),
            'pagetitle'=>'Recovery case'
        )
    );
    opCheck(hash_equals($digest,$sameDigest),'canonical ordering');
    $different=MutationOperationLedger::requestDigest(
        'disposable-site','create_resource','resource',array('pagetitle'=>'Other'));
    opCheck($digest!==$different,'payload binding');
    $id=makeOpId();
    $first=$ledger->claim($id,$digest);
    opCheck($first['fresh']===true && $first['state']==='PENDING','fresh claim');
    // Simulated connection loss: first worker disappears without finish().
    unset($ledger);
    $replay=$second->claim($id,$digest);
    opCheck($replay['fresh']===false && $replay['state']==='PENDING',
        'replay after crash cannot execute again');
    opReject(function()use($second,$id,$different){
        $second->claim($id,$different);
    },'reuse ID with mismatched request');
    opCheck($second->get($id,$digest)['state']==='PENDING',
        'status lookup after crash');
    $second->finish($id,$digest,'UNCERTAIN');
    opCheck($second->get($id,$digest)['state']==='UNCERTAIN','uncertain durable');
    opCheck($second->claim($id,$digest)['fresh']===false
        && $second->claim($id,$digest)['state']==='UNCERTAIN',
        'uncertain replay cannot execute');
    opReject(function()use($second,$id,$digest){
        $second->finish($id,$digest,'SUCCEEDED');
    },'cannot rewrite terminal uncertain as success');
    $successful=makeOpId();
    opCheck($second->claim($successful,$digest)['fresh']===true,'second operation claimed');
    $second->finish($successful,$digest,'SUCCEEDED');
    opCheck($second->claim($successful,$digest)['state']==='SUCCEEDED',
        'success is status only, not result replay');
    opReject(function()use($second,$successful,$digest){
        $second->finish($successful,$digest,'SUCCEEDED');
    },'cannot terminal transition twice');
    opReject(function()use($second,$digest){
        $second->claim('path/traversal',$digest);
    },'invalid operation ID');
    opReject(function()use($second,$successful,$different){
        $second->get($successful,$different);
    },'status lookup wrong payload');
    opReject(function()use($second,$digest){
        $second->claim(makeOpId(),$digest.'extra');
    },'invalid digest');
    $pdo->beginTransaction();
    try {
        $blocked=new MutationOperationLedger($pdo,$table);
        opReject(function()use($blocked,$digest){
            $blocked->claim(makeOpId(),$digest);
        },'transaction must not contain claim');
    }finally{$pdo->rollBack();}
    $missing=new MutationOperationLedger($pdo,'ci_missing_operation_journal');
    opReject(function()use($missing,$digest){
        $missing->claim(makeOpId(),$digest);
    },'no table must fail closed');
    // RU: Два независимых процесса соревнуются за один ID и одну запись.
    // EN: Two separate PHP workers contend on the same durable intent.
    $raceId=makeOpId();
    $gate=getenv('RUNNER_TEMP').'/operation-gate-'.bin2hex(random_bytes(5));
    $workers=array();
    for($i=0;$i<2;$i++){
        $ready=$gate.'.ready.'.$i;
        $cmd=array(PHP_BINARY,__DIR__.'/test.ci-operation-ledger-worker.php',
            $table,$raceId,$digest,$ready,$gate);
        $pipes=array();
        $process=proc_open($cmd,array(
            0=>array('pipe','r'),1=>array('pipe','w'),2=>array('pipe','w')
        ),$pipes);
        opCheck(is_resource($process),'worker started');
        fclose($pipes[0]);
        $workers[]=array($process,$pipes,$ready);
    }
    $deadline=microtime(true)+15;
    while((!is_file($workers[0][2])||!is_file($workers[1][2]))
        && microtime(true)<$deadline)usleep(10000);
    opCheck(is_file($workers[0][2])&&is_file($workers[1][2]),'both ready');
    file_put_contents($gate,'go');
    $outcomes=array();
    foreach($workers as $w){
        $output=trim(stream_get_contents($w[1][1]));
        $stderr=stream_get_contents($w[1][2]);
        fclose($w[1][1]);fclose($w[1][2]);
        opCheck(proc_close($w[0])===0,'worker exited '.$stderr);
        $outcomes[]=$output;
    }
    opCheck(count(array_filter($outcomes,function($v){
        return $v==='OPERATION_WORKER_FIRST';
    }))===1,'exactly one worker claimed the intent');
    opCheck(count(array_filter($outcomes,function($v){
        return $v==='OPERATION_WORKER_REPLAY';
    }))===1,'second worker refused execution');
    opCheck($second->claim($raceId,$digest)['state']==='PENDING',
        'raced pending intent stays nonretryable');
    @unlink($gate);@unlink($gate.'.ready.0');@unlink($gate.'.ready.1');
    opCheck((int)$pdo->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn()===3,
        'only three durable operations');
    echo 'OPERATION_INTENT_JOURNAL_MODX_CI_OK checks='.$checks.PHP_EOL;
} finally {
    $pdo->exec('DROP TABLE IF EXISTS `'.$table.'`');
}
