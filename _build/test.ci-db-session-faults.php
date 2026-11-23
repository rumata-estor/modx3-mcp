<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Потеря MariaDB-сессии и InnoDB deadlock только в одноразовой CI.
 * EN: Disposable database disconnect, fresh session, and deadlock fault tests.
 */
if(PHP_SAPI!=='cli'||getenv('MODXMCP_DISPOSABLE_CI')!=='1'
 ||strpos((string)getenv('MODX_CONFIG_CORE'),'/home/runner/')!==0
 ||!is_file((string)getenv('MODX_CONFIG_CORE'))
 ||strpos((string)getenv('RUNNER_TEMP'),'/home/runner/')!==0)exit(3);
$mode=$argv[1]??'parent';$work=$argv[2]??'';
if($mode!=='parent'&&(!is_dir($work)
 ||strpos(realpath($work),rtrim(getenv('RUNNER_TEMP'),'/').'/')!==0))exit(4);
require getenv('MODX_CONFIG_CORE');
$is2=!is_file(MODX_CORE_PATH.'src/Revolution/modX.php');
if($is2){require MODX_CORE_PATH.'model/modx/modx.class.php';$modx=new modX();}
else{require MODX_CORE_PATH.'vendor/autoload.php';$modx=\MODX\Revolution\modX::getInstance();}
$modx->initialize('mgr');$modx->setOption('modxmcp.disabled_groups','');
if($mode!=='parent'){
 $cache=$work.'/cache-'.$mode.'/';
 if(!is_dir($cache)&&!mkdir($cache,0770,true))exit(5);
 $modx->setOption('cache_path',$cache);
}
require MODX_CORE_PATH.'components/modxmcp/model/modxmcp.class.php';
$api=new modxMCP($modx);$class=\ModxMcp\Tools\StateSupport::class;
if($mode==='disconnect'){
 $ctx=(new \ModxMcp\Core\Runtime($modx,$api))->context();
 $error='unexpected-success';
 try{$class::withMutationLock($ctx,function()use($ctx,$modx,$class,$work){
  $class::withRevisionTransaction($ctx,function()use($ctx,$modx,$class,$work){
   $class::incrementRevision($ctx);
   file_put_contents($work.'/disconnect-thread',(string)$modx->query('SELECT CONNECTION_ID()')->fetchColumn());
   $end=microtime(true)+15;
   while(!is_file($work.'/disconnect-go')&&microtime(true)<$end)usleep(20000);
   if(!is_file($work.'/disconnect-go'))throw new RuntimeException('CI barrier timeout');
   $class::incrementRevision($ctx); // must fail after KILL CONNECTION
  });
 });}catch(\Throwable $e){$error=$e->getMessage();}
 file_put_contents($work.'/disconnect-result',
  strpos($error,'MUTATION_OUTCOME_UNCERTAIN:')===0?'unknown':'bad:'.substr($error,0,100));
 exit(0);
}
if($mode==='postcommit'){
    // DB commit completed; lose the DB lock session before RELEASE_LOCK.
    $ctx=(new \ModxMcp\Core\Runtime($modx,$api))->context();
    $out='unexpected-success';
    try{$class::withMutationLock($ctx,function()use($ctx,$modx,$class,$work){
        $class::withRevisionTransaction($ctx,function()use($ctx,$class){
            $class::incrementRevision($ctx);
        });
        file_put_contents($work.'/postcommit-thread',
            (string)$modx->query('SELECT CONNECTION_ID()')->fetchColumn());
        $end=microtime(true)+15;
        while(!is_file($work.'/postcommit-go')&&microtime(true)<$end)usleep(20000);
        if(!is_file($work.'/postcommit-go'))throw new RuntimeException('CI barrier timeout');
    });}
    catch(\Throwable $e){$out=strpos($e->getMessage(),
        'MUTATION_OUTCOME_UNCERTAIN:')===0?'unknown':'bad:'.substr($e->getMessage(),0,80);}
    file_put_contents($work.'/postcommit-result',$out);
    exit(0);
}
if($mode==='probe'){
 $ctx=(new \ModxMcp\Core\Runtime($modx,$api))->context();
 try{$class::withMutationLock($ctx,function()use($work){file_put_contents($work.'/probe-ok','yes');});}
 catch(\Throwable $e){file_put_contents($work.'/probe-fail','yes');}exit(0);
}
if($mode==='recover'){
 // Newly authorized command on new PHP/DB session. Never replay lost mutation.
 $s=$api->processRequest('get_site_state','',array());
 $created=$api->processRequest('create_element','',array(
  'type'=>'chunk','name'=>'MCP_DB_Recovery_'.bin2hex(random_bytes(5)),
  'content'=>'new-command-not-replay',
  '_runtime_preconditions'=>array(
   'observed_site_revision'=>(int)$s['site_revision'],
   'strict_site_revision'=>true,'read_set'=>array()
  )
 ));
 file_put_contents($work.'/recover-result',json_encode(array(
  'id'=>(int)($created['id']??0),'before'=>(int)$s['site_revision'],
  'after'=>(int)$api->processRequest('get_site_state','',array())['site_revision']
 )));
 exit(0);
}
if($mode==='deadlock'){
 $table=trim(file_get_contents($work.'/table'));
 if(!preg_match('/\A[a-zA-Z0-9_]+\z/D',$table))exit(6);
 $ctx=(new \ModxMcp\Core\Runtime($modx,$api))->context();$result='unexpected';
 try{$class::withMutationLock($ctx,function()use($ctx,$modx,$class,$work,$table){
  $class::withRevisionTransaction($ctx,function()use($ctx,$modx,$class,$work,$table){
   $q=$modx->prepare('UPDATE '.$table.' SET v=v+1 WHERE id=?');
   if(!$q||!$q->execute(array(1)))throw new RuntimeException('row 1 lock failed');
   $class::incrementRevision($ctx);
   file_put_contents($work.'/deadlock-ready','yes');
   $end=microtime(true)+15;
   while(!is_file($work.'/deadlock-go')&&microtime(true)<$end)usleep(20000);
   if(!is_file($work.'/deadlock-go'))throw new RuntimeException('CI barrier timeout');
   file_put_contents($work.'/deadlock-attempt','yes');
   $q=$modx->prepare('UPDATE '.$table.' SET v=v+1 WHERE id=?');
   if(!$q||!$q->execute(array(2)))throw new RuntimeException('row 2 lock failed');
  });
 });$result='committed';}
 catch(\Throwable $e){$s=$e->getMessage();
  if(strpos($s,'MUTATION_OUTCOME_UNCERTAIN:')===0)$result='unknown';
  elseif(strpos($s,'SQLSTATE')!==false||strpos($s,'Deadlock')!==false)$result='raw-sql';
  else $result='other:'.substr($s,0,55);
 }
 file_put_contents($work.'/deadlock-result',$result);exit(0);
}
if($mode!=='parent')exit(7);
$work=rtrim(getenv('RUNNER_TEMP'),'/').'/mcp-db-fault-'.bin2hex(random_bytes(5));
if(!mkdir($work,0770,true))throw new RuntimeException('CI temp missing');
function checkDb($ok,$why){if(!$ok)throw new RuntimeException('MCP_DB_FAULT_FAILED '.$why);}
function waitDb($file,$seconds=15){$end=microtime(true)+$seconds;
 while(!is_file($file)&&microtime(true)<$end)usleep(25000);
 checkDb(is_file($file),'timeout '.basename($file));}
function spawnDb($mode,$work){$p=proc_open(array(PHP_BINARY,__FILE__,$mode,$work),
 array(0=>array('pipe','r'),1=>array('pipe','w'),2=>array('pipe','w')),$pipes);
 checkDb(is_resource($p),'spawn '.$mode);fclose($pipes[0]);return array($p,$pipes);}
function closeDb($j){$out=stream_get_contents($j[1][1]);$err=stream_get_contents($j[1][2]);
 fclose($j[1][1]);fclose($j[1][2]);return array(proc_close($j[0]),$out,$err);}
$pdo=new \PDO('mysql:host=127.0.0.1;dbname=modx_build;charset=utf8mb4',
 'modx_ci','ci-throwaway-only-user',
 array(\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION,\PDO::ATTR_TIMEOUT=>6));
$jobs=array();$cleanup=0;$table='mcp_dbfault_'.bin2hex(random_bytes(5));$created=false;
try{
 $rev=(int)$api->processRequest('get_site_state','',array())['site_revision'];
 $jobs['disconnect']=spawnDb('disconnect',$work);
 waitDb($work.'/disconnect-thread');
 $thread=trim(file_get_contents($work.'/disconnect-thread'));
 checkDb(ctype_digit($thread)&&(int)$thread>0,'thread id');
 $pdo->exec('KILL CONNECTION '.(int)$thread);
 file_put_contents($work.'/disconnect-go','yes');
 waitDb($work.'/disconnect-result');
 $status=trim(file_get_contents($work.'/disconnect-result'));
 $done=closeDb($jobs['disconnect']);unset($jobs['disconnect']);
 checkDb($done[0]===0&&$status==='unknown','disconnect classification '.$status.' '.$done[2]);
 checkDb((int)$api->processRequest('get_site_state','',array())['site_revision']===$rev,
  'uncommitted revision rollback');
 echo 'MCP_DB_KILL_CONNECTION_UNKNOWN_AND_ROLLBACK_OK'.PHP_EOL;
 $jobs['probe']=spawnDb('probe',$work);waitDb($work.'/probe-ok',12);
 $done=closeDb($jobs['probe']);unset($jobs['probe']);
 checkDb($done[0]===0,'fresh DB lock after disconnect');
 echo 'MCP_DB_FRESH_SESSION_REACQUIRES_LOCK_OK'.PHP_EOL;
 $jobs['recover']=spawnDb('recover',$work);waitDb($work.'/recover-result',12);
 $done=closeDb($jobs['recover']);unset($jobs['recover']);
 checkDb($done[0]===0,'fresh operation '.$done[2]);
 $x=json_decode(file_get_contents($work.'/recover-result'),true);
 checkDb(is_array($x)&&($x['id']??0)>0
  &&$x['before']===$rev&&$x['after']===$rev+1,'fresh CAS after reconnect');
 $cleanup=(int)$x['id'];echo 'MCP_DB_FRESH_OPERATION_AFTER_RECONNECT_OK'.PHP_EOL;
 // The transaction already COMMITTED, but its lock/session response is lost.
 $afterRecovery=(int)$api->processRequest('get_site_state','',array())['site_revision'];
 $jobs['postcommit']=spawnDb('postcommit',$work);
 waitDb($work.'/postcommit-thread');
 $postThread=trim(file_get_contents($work.'/postcommit-thread'));
 checkDb(ctype_digit($postThread)&&(int)$postThread>0,'postcommit thread');
 $pdo->exec('KILL CONNECTION '.(int)$postThread);
 file_put_contents($work.'/postcommit-go','yes');
 waitDb($work.'/postcommit-result');
 $postStatus=trim(file_get_contents($work.'/postcommit-result'));
 $done=closeDb($jobs['postcommit']);unset($jobs['postcommit']);
 checkDb($done[0]===0&&$postStatus==='unknown',
  'postcommit disconnect must not report success: '.$postStatus.' '.$done[2]);
 checkDb((int)$api->processRequest('get_site_state','',array())['site_revision']
  ===$afterRecovery+1,'confirmed DB commit remains despite lost response');
 echo 'MCP_DB_POSTCOMMIT_DISCONNECT_UNKNOWN_WITH_PERSISTED_REVISION_OK'.PHP_EOL;
 // SQL setup is a random throwaway lock-inversion table, not CMS data.
 $pdo->exec('CREATE TABLE '.$table.' (id INT PRIMARY KEY,v INT NOT NULL) ENGINE=InnoDB');$created=true;
 $rows=array();for($i=1;$i<=100;$i++)$rows[]='('.$i.',0)';
 $pdo->exec('INSERT INTO '.$table.' VALUES '.implode(',',$rows));
 file_put_contents($work.'/table',$table);
 // RU: Большая транзакция внешнего Manager чаще остаётся, MCP — жертва deadlock.
 // EN: Make the external transaction expensive so the MCP worker is the victim.
 $pdo->beginTransaction();
 $pdo->exec('UPDATE '.$table.' SET v=v+1 WHERE id>=3');
 $pdo->exec('UPDATE '.$table.' SET v=v+1 WHERE id=2');
 $jobs['deadlock']=spawnDb('deadlock',$work);waitDb($work.'/deadlock-ready');
 file_put_contents($work.'/deadlock-go','yes');waitDb($work.'/deadlock-attempt');
 usleep(90000);$externalDeadlock=false;
 try{$pdo->exec('UPDATE '.$table.' SET v=v+1 WHERE id=1');}
 catch(\PDOException $e){$externalDeadlock=true;}
 try{if($pdo->inTransaction())$pdo->rollBack();}catch(\Throwable $e){}
 waitDb($work.'/deadlock-result');
 $d=trim(file_get_contents($work.'/deadlock-result'));
 $done=closeDb($jobs['deadlock']);unset($jobs['deadlock']);
 checkDb($done[0]===0,'deadlock worker '.$done[2]);
 checkDb($externalDeadlock||$d==='unknown'||$d==='raw-sql','actual deadlock required '.$d);
 checkDb($d==='unknown',
  'deadlock must fail closed inside MCP transaction, no raw SQL or silent commit; result='.$d);
 echo 'MCP_DB_INNODB_DEADLOCK_SAFE_CLASSIFICATION_OK'.PHP_EOL;
 @unlink($work.'/probe-ok');$jobs['probe2']=spawnDb('probe',$work);
 waitDb($work.'/probe-ok');$done=closeDb($jobs['probe2']);unset($jobs['probe2']);
 checkDb($done[0]===0,'shared lock freed after deadlock');
 echo 'MCP_DB_DEADLOCK_RECOVERY_LOCK_AVAILABLE_OK'.PHP_EOL;
}finally{
 if($pdo->inTransaction())$pdo->rollBack();
 foreach($jobs as $j){@proc_terminate($j[0],9);closeDb($j);}
 if($created)$pdo->exec('DROP TABLE IF EXISTS '.$table);
 if($cleanup>0){$s=$api->processRequest('get_site_state','',array());
  $api->processRequest('delete_element','',array('type'=>'chunk','id'=>$cleanup,
   '_runtime_preconditions'=>array('observed_site_revision'=>(int)$s['site_revision'],
    'strict_site_revision'=>true,'read_set'=>array())));
 }
}
