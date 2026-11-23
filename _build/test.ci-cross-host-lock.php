<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Два отдельных PHP-узла с разными flock и общей disposable MariaDB.
 * EN: Isolated PHP workers with distinct local flock paths and one test database.
 */
/** No production sites, credentials, files, or installed packages are touched. */
if (PHP_SAPI!=='cli'
    || getenv('MODXMCP_DISPOSABLE_CI')!=='1'
    || strpos((string)getenv('MODX_CONFIG_CORE'),'/home/runner/')!==0
    || !is_file((string)getenv('MODX_CONFIG_CORE'))
    || strpos((string)getenv('RUNNER_TEMP'),'/home/runner/')!==0) {
    fwrite(STDERR,"Refusing cross-host test outside disposable CI.\n");
    exit(3);
}
$mode = isset($argv[1]) ? (string)$argv[1] : 'parent';
$work = isset($argv[2]) ? (string)$argv[2] : '';
$node = isset($argv[3]) ? (string)$argv[3] : '';
if ($mode!=='parent' && (!is_dir($work)
    || strpos(realpath($work),(string)getenv('RUNNER_TEMP').'/')!==0
    || !in_array($node,array('a','b'),true))) exit(4);
require getenv('MODX_CONFIG_CORE');
$is2 = !is_file(MODX_CORE_PATH.'src/Revolution/modX.php');
if ($is2) {
    require_once MODX_CORE_PATH.'model/modx/modx.class.php';
    $modx=new modX();
} else {
    require_once MODX_CORE_PATH.'vendor/autoload.php';
    $modx=\MODX\Revolution\modX::getInstance();
}
$modx->initialize('mgr');
$modx->setOption('modxmcp.disabled_groups','');
if ($mode!=='parent') {
    $cache=$work.'/cache-'.$node.'/';
    if (!is_dir($cache) && !mkdir($cache,0770,true)) exit(5);
    // RU: Изолируем flock, но оставляем ту же БД MODX.
    // EN: Simulate separate hosts: separate local locks, identical MODX database.
    $modx->setOption('cache_path',$cache);
}
require_once MODX_CORE_PATH.'components/modxmcp/model/modxmcp.class.php';
$api=new modxMCP($modx);
if ($mode==='mutate') {
    $config=json_decode((string)file_get_contents($work.'/config.json'),true);
    file_put_contents($work.'/ready-'.$node, (string)getmypid());
    $deadline=microtime(true)+18;
    while (!is_file($work.'/go') && microtime(true)<$deadline) usleep(10000);
    if (!is_file($work.'/go')) exit(6);
    $out=array('state'=>'unexpected');
    try {
        $created=$api->processRequest('create_element','',array(
            'type'=>'chunk', 'name'=>$config['prefix'].'_'.$node,
            'content'=>'crosshost-'.$node,
            '_runtime_preconditions'=>array(
                'observed_site_revision'=>(int)$config['revision'],
                'strict_site_revision'=>true,'read_set'=>array()
            )
        ));
        $out=array('state'=>'success','id'=>(int)($created['id']??0));
    } catch (\Throwable $error) {
        $message=$error->getMessage();
        $out=array('state'=>strpos($message,'STALE_STATE')!==false?'stale':'error',
            'code'=>substr($message,0,55));
    }
    file_put_contents($work.'/result-'.$node.'.json',json_encode($out));
    exit(0);
}
if ($mode==='update') {
    $config=json_decode((string)file_get_contents($work.'/update-config.json'),true);
    file_put_contents($work.'/ready-update-'.$node,(string)getmypid());
    $deadline=microtime(true)+18;
    while (!is_file($work.'/go-update') && microtime(true)<$deadline) usleep(10000);
    if (!is_file($work.'/go-update')) exit(6);
    try {
        $api->processRequest('update_element','',array(
            'type'=>'chunk','id'=>(int)$config['id'],
            'content'=>'crosshost-update-'.$node,
            '_runtime_preconditions'=>array(
                'observed_site_revision'=>(int)$config['revision'],
                'strict_site_revision'=>true,
                'read_set'=>$config['read_set']
            )
        ));
        $out=array('state'=>'success');
    } catch (\Throwable $error) {
        $message=$error->getMessage();
        $out=array('state'=>strpos($message,'STALE_STATE')!==false?'stale':'error',
            'code'=>substr($message,0,55));
    }
    file_put_contents($work.'/result-update-'.$node.'.json',json_encode($out));
    exit(0);
}
if ($mode==='hold' || $mode==='probe' || $mode==='timeout') {
    $runtime=new \ModxMcp\Core\Runtime($modx,$api);
    $ctx=$runtime->context();
    $lock=\ModxMcp\Tools\StateSupport::class;
    try {
        $lock::withMutationLock($ctx,function()use($mode,$work,$lock,$ctx) {
            if ($mode==='probe' || $mode==='timeout') {
                file_put_contents($work.'/'.$mode.'-acquired','yes');
                return;
            }
            // RU: Только собственная ревизия, без изменения данных сайта.
            // EN: Uncommitted revision reservation is rolled back by the DB on SIGKILL.
            $lock::withRevisionTransaction($ctx,function()use($work,$lock,$ctx) {
                $lock::incrementRevision($ctx);
                file_put_contents($work.'/hold-acquired',(string)getmypid());
                sleep(35); // parent SIGKILLs this CI child
            });
        });
    } catch (\Throwable $error) {
        if ($mode==='timeout' && strpos($error->getMessage(),
            'CAS_LOCK_UNAVAILABLE:')===0) {
            file_put_contents($work.'/timeout-denied','yes');
            exit(0);
        }
        fwrite(STDERR,"CROSSHOST_WORKER_FAILURE ".substr($error->getMessage(),0,120)."\n");
        exit(9);
    }
    exit(0);
}
if ($mode!=='parent') exit(7);
$work=rtrim(getenv('RUNNER_TEMP'),'/').'/mcp-crosshost-'.bin2hex(random_bytes(6));
if (!mkdir($work,0770,true)) throw new RuntimeException('Cannot create disposable work area');
function crossCheck($ok,$reason) {
    if (!$ok) throw new RuntimeException('CROSSHOST_LOCK_TEST_FAILED: '.$reason);
}
function crossWait($path,$seconds=15) {
    $deadline=microtime(true)+$seconds;
    while (!is_file($path) && microtime(true)<$deadline) usleep(25000);
    crossCheck(is_file($path),'timeout waiting for '.basename($path));
}
function crossStart($mode,$work,$node) {
    $proc=proc_open(array(PHP_BINARY,__FILE__,$mode,$work,$node),
        array(0=>array('pipe','r'),1=>array('pipe','w'),2=>array('pipe','w')),$pipes);
    crossCheck(is_resource($proc),'could not spawn worker '.$mode);
    fclose($pipes[0]);
    return array($proc,$pipes);
}
function crossClose($job) {
    $out=stream_get_contents($job[1][1]);
    $err=stream_get_contents($job[1][2]);
    fclose($job[1][1]);fclose($job[1][2]);
    return array(proc_close($job[0]),$out,$err);
}
function crossGuard($api) {
    $state=$api->processRequest('get_site_state','',array());
    return array('observed_site_revision'=>(int)$state['site_revision'],
        'strict_site_revision'=>true,'read_set'=>array());
}
$createdIds=array();$trigger=null;$pdo=null;$jobs=array();
try {
    $start=$api->processRequest('get_site_state','',array());
    $revision=(int)$start['site_revision'];
    $config=array('prefix'=>'MCP_CrossHost_'.bin2hex(random_bytes(5)),
        'revision'=>$revision);
    file_put_contents($work.'/config.json',json_encode($config));
    // RU: Расширяем окно гонки CAS->increment, не меняя пользовательские данные.
    // EN: Delay revision update to deterministically expose cross-node CAS races.
    $pdo=new \PDO('mysql:host=127.0.0.1;dbname=modx_build;charset=utf8mb4',
        'modx_ci','ci-throwaway-only-user',
        array(\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION));
    $trigger='mcp_crosshost_'.bin2hex(random_bytes(5));
    $pdo->exec('CREATE TRIGGER `'.$trigger.'` BEFORE UPDATE ON `modx_system_settings`'
        .' FOR EACH ROW BEGIN IF NEW.`key`=\'modxmcp.site_revision\' THEN'
        .' DO SLEEP(0.7); END IF; END');
    foreach(array('a','b') as $node) $jobs[$node]=crossStart('mutate',$work,$node);
    crossWait($work.'/ready-a');crossWait($work.'/ready-b');
    file_put_contents($work.'/go','start');
    crossWait($work.'/result-a.json');crossWait($work.'/result-b.json');
    $results=array();
    foreach(array('a','b') as $node) {
        $out=crossClose($jobs[$node]);unset($jobs[$node]);
        crossCheck($out[0]===0,'worker '.$node.' failed '.$out[2]);
        $results[$node]=json_decode(file_get_contents($work.'/result-'.$node.'.json'),true);
        if (($results[$node]['state']??'')==='success') {
            $id=(int)($results[$node]['id']??0);
            if ($id>0) $createdIds[]=$id;
        }
    }
    // Keep the fault-injection delay for the same-target update race.
    $states=array_column($results,'state');
    sort($states);
    crossCheck($states===array('stale','success'),
        'two hosts using same revision must produce one success and one STALE_STATE; actual='
        .json_encode($results));
    crossCheck(count($createdIds)===1,'one persisted chunk only');
    crossCheck((int)$api->processRequest('get_site_state','',array())['site_revision']
        ===$revision+1,'exactly one CAS increment');
    $read=$api->processRequest('get_element','',array('type'=>'chunk','id'=>$createdIds[0]));
    crossCheck(strpos((string)($read['name']??''),$config['prefix'])===0,
        'independent MCP readback of winning chunk');
    echo 'CROSSHOST_TWO_ISOLATED_FLOCKS_SINGLE_CAS_WINNER_OK'.PHP_EOL;
    // RU: Два узла редактируют один чанк с одним снимком id/fingerprint.
    // EN: Two hosts race on one existing target using the same CAS fingerprint.
    $snapshot=$api->processRequest('get_site_state','',array('read_set'=>array(
        array('role'=>'target','type'=>'chunk','id'=>$createdIds[0])
    )));
    file_put_contents($work.'/update-config.json',json_encode(array(
        'id'=>$createdIds[0],
        'revision'=>(int)$snapshot['site_revision'],
        'read_set'=>$snapshot['read_set']
    )));
    foreach(array('a','b') as $node) $jobs[$node]=crossStart('update',$work,$node);
    crossWait($work.'/ready-update-a');crossWait($work.'/ready-update-b');
    file_put_contents($work.'/go-update','start');
    crossWait($work.'/result-update-a.json');crossWait($work.'/result-update-b.json');
    $updateResults=array();
    foreach(array('a','b') as $node) {
        $out=crossClose($jobs[$node]);unset($jobs[$node]);
        crossCheck($out[0]===0,'update worker '.$node.' failed '.$out[2]);
        $updateResults[$node]=json_decode(
            file_get_contents($work.'/result-update-'.$node.'.json'),true);
    }
    $updateStates=array_column($updateResults,'state');sort($updateStates);
    crossCheck($updateStates===array('stale','success'),
        'two writers to same chunk must give one stale and one successful update: '
        .json_encode($updateResults));
    $winner=($updateResults['a']['state']==='success')?'a':'b';
    $updated=$api->processRequest('get_element','',
        array('type'=>'chunk','id'=>$createdIds[0]));
    crossCheck((string)($updated['snippet']??($updated['content']??''))
        ==='crosshost-update-'.$winner,'same-target winner content readback');
    crossCheck((int)$api->processRequest('get_site_state','',array())['site_revision']
        ===$revision+2,'two contention rounds produced only two CAS increments');
    echo 'CROSSHOST_SAME_TARGET_FINGERPRINT_ONE_WRITER_OK'.PHP_EOL;
    // Drop fault-injection trigger before testing clean crash recovery.
    $pdo->exec('DROP TRIGGER IF EXISTS `'.$trigger.'`');$trigger=null;
    // Crash while holding the session lock and an uncommitted revision update.
    $baseline=(int)$api->processRequest('get_site_state','',array())['site_revision'];
    $jobs['hold']=crossStart('hold',$work,'a');
    crossWait($work.'/hold-acquired');
    // A blocked host must fail closed within the finite GET_LOCK timeout.
    $jobs['timeout']=crossStart('timeout',$work,'b');
    crossWait($work.'/timeout-denied',10);
    $timeoutExit=crossClose($jobs['timeout']);unset($jobs['timeout']);
    crossCheck($timeoutExit[0]===0 && !is_file($work.'/timeout-acquired'),
        'lock timeout must refuse mutation before callback');
    echo 'CROSSHOST_LOCK_TIMEOUT_FAILS_CLOSED_OK'.PHP_EOL;
    $jobs['probe']=crossStart('probe',$work,'b');
    usleep(400000);
    crossCheck(!is_file($work.'/probe-acquired'),
        'second server entered while first held global transaction lock');
    crossCheck(proc_terminate($jobs['hold'][0],9),
        'could not SIGKILL isolated PHP worker');
    $holdExit=crossClose($jobs['hold']);unset($jobs['hold']);
    crossWait($work.'/probe-acquired',12);
    $probeExit=crossClose($jobs['probe']);unset($jobs['probe']);
    crossCheck($probeExit[0]===0,'lock not reacquired after SIGKILL '.$probeExit[2]);
    $after=(int)$api->processRequest('get_site_state','',array())['site_revision'];
    crossCheck($after===$baseline,'uncommitted revision rolled back after SIGKILL');
    echo 'CROSSHOST_SIGKILL_RELEASES_DB_LOCK_AND_ROLLS_BACK_OK'.PHP_EOL;
} finally {
    if($trigger!==null && $pdo) $pdo->exec('DROP TRIGGER IF EXISTS `'.$trigger.'`');
    foreach ($jobs as $job) {
        @proc_terminate($job[0],9);
        crossClose($job);
    }
    foreach($createdIds as $id) {
        $api->processRequest('delete_element','',array('type'=>'chunk','id'=>$id,
            '_runtime_preconditions'=>crossGuard($api)));
    }
}
