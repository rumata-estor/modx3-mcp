<?php
/**
 * CAS v2 failure injection for NON-transactional operations.
 * ALL site data, files and packages are accessed through modxMCP::processRequest.
 * The *only* direct SQL is creating/deleting a trigger in disposable CI MariaDB.
 *
 * Tests failure of revision reservation, successful file mutation/readback,
 * and conservative revision consumption on a post-reservation operation error.
 * No package downloads/installations or arbitrary processors are ever executed.
 */
if (PHP_SAPI !== 'cli' || getenv('MODXMCP_DISPOSABLE_CI') !== '1'
    || strpos((string)getenv('MODX_CONFIG_CORE'), '/home/runner/') !== 0
    || !is_file((string)getenv('MODX_CONFIG_CORE'))) {
    fwrite(STDERR,"Refusing external fault test outside disposable CI.\n");exit(3);
}
require_once getenv('MODX_CONFIG_CORE');
$is2 = !is_file(MODX_CORE_PATH . 'src/Revolution/modX.php');
if ($is2) {
    require_once MODX_CORE_PATH . 'model/modx/modx.class.php';
    $modx = new modX();
} else {
    require_once MODX_CORE_PATH . 'vendor/autoload.php';
    $modx = \MODX\Revolution\modX::getInstance();
}
$modx->initialize('mgr');
$modx->setOption('modxmcp.allow_root_filesystem_read', true);
// The disposable 1.2.3 -> 1.2.7 upgrade has optional package management
// disabled by default. Enable its capability only for this CI *process* so
// we can prove the CAS revision guard rejects the request before the
// package installer is invoked. Do not persist site settings or install any
// package.
$modx->setOption('modxmcp.disabled_groups', '');
require_once MODX_CORE_PATH . 'components/modxmcp/model/modxmcp.class.php';
$api = new modxMCP($modx);
function rpc($action, array $data=array()) {
    global $api;
    return $api->processRequest($action, '', $data);
}
function rev() {
    $state = rpc('get_site_state');
    return (int)$state['site_revision'];
}
function requireCas($ok,$label) {
    global $checked;
    if (!$ok) throw new RuntimeException('EXTERNAL_CAS_ASSERT_FAILED: '.$label);
    $checked++;
}
function getFile($path) {
    try {
        $row = rpc('read_media_source_file',array('id'=>1,'path'=>$path));
        $raw = $row['encoding']==='base64'
            ? base64_decode($row['content'],true)
            : $row['content'];
        if (!is_string($raw)) throw new RuntimeException('Invalid MCP file data');
        return $raw;
    } catch (ModxMCPClientException $e) {
        if (strpos($e->getMessage(), 'File not found')!==false) return null;
        throw $e;
    }
}
function folderPresent($name) {
    $list=rpc('list_media_source_files',array('id'=>1,'path'=>''));
    foreach ($list['entries'] as $row) {
        if ($row['name']===$name) return (bool)$row['is_dir'];
    }
    return false;
}
function absentAtRevisionFault($action,array $args,$label,$read,$expected) {
    global $checked;
    $error=null;
    try{ rpc($action,$args); }
    catch (\Throwable $e){ $error=$e->getMessage(); }
    requireCas(is_string($error)
        && strpos($error,'Failed to persist MODX MCP site revision')!==false,
        $label.' must fail at revision reservation before execution; actual='.(string)$error);
    $now=$read();
    requireCas($now===$expected,$label.' must not mutate external state');
    echo 'CAS_EXTERNAL_DENY_OK '.$label.PHP_EOL;
}
$checked=0;
$key=bin2hex(random_bytes(7));
$dir='cas-fault-'.$key;
$empty='cas-empty-'.$key;
$other='cas-no-create-'.$key;
$base=$dir.'/baseline.txt';
$renamed=$dir.'/renamed.txt';
$created=$dir.'/must-not-exist.txt';
$trigger='cas_external_'.$key;
$injected=false;
$pdo=null;
$hasDir=false;
$hasEmpty=false;
$filesCreated=array();
$ready=false;
try {
    // No direct filesystem preparation. All fixtures are created through MCP.
    rpc('create_media_folder',array('source'=>1,'parent'=>'/','name'=>$dir));
    $hasDir=true;
    rpc('create_media_folder',array('source'=>1,'parent'=>'/','name'=>$empty));
    $hasEmpty=true;
    rpc('create_media_file',array('source'=>1,'path'=>$dir,'name'=>'baseline.txt','content'=>'alpha-'.$key));
    $filesCreated[]=$base;
    requireCas(getFile($base)==='alpha-'.$key,'baseline MCP readback');
    requireCas(folderPresent($dir) && folderPresent($empty),'MCP folder fixture readback');
    $initial=rev();

    $pdo=new \PDO(
        'mysql:host=127.0.0.1;dbname=modx_build;charset=utf8mb4',
        'modx_ci','ci-throwaway-only-user',
        array(\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION)
    );
    $pdo->exec(
        'CREATE TRIGGER `'.$trigger.'` BEFORE UPDATE ON `modx_system_settings`'
        .' FOR EACH ROW BEGIN'
        ." IF NEW.`key`='modxmcp.site_revision' THEN"
        ." SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='CAS_EXTERNAL_REVISION_DENIED';"
        .' END IF; END'
    );
    $injected=true;

    absentAtRevisionFault('create_media_file',
        array('source'=>1,'path'=>$dir,'name'=>'must-not-exist.txt','content'=>'no'),
        'create_media_file',function() use($created){return getFile($created);},null);
    absentAtRevisionFault('update_media_file',
        array('source'=>1,'path'=>$base,'content'=>'bad'),
        'update_media_file',function() use($base){return getFile($base);},'alpha-'.$key);
    absentAtRevisionFault('rename_media_file',
        array('source'=>1,'path'=>$base,'new_name'=>'renamed.txt'),
        'rename_media_file',function() use($base){return getFile($base);},'alpha-'.$key);
    requireCas(getFile($renamed)===null,'rename destination absent');
    absentAtRevisionFault('delete_media_file',
        array('source'=>1,'path'=>$base),
        'delete_media_file',function() use($base){return getFile($base);},'alpha-'.$key);
    absentAtRevisionFault('create_media_folder',
        array('source'=>1,'parent'=>'/','name'=>$other),
        'create_media_folder',function() use($other){return folderPresent($other);},false);
    absentAtRevisionFault('delete_media_folder',
        array('source'=>1,'path'=>$empty),
        'delete_media_folder',function() use($empty){return folderPresent($empty);},true);
    // Package operations and arbitrary processor are ONLY invoked when the
    // revision trigger blocks their execution before any network/process work.
    absentAtRevisionFault('install_package',
        array('package'=>'nonexistent-cas-ci-'.$key,'provider'=>2147483646),
        'install_package_gate',function(){return true;},true);
    absentAtRevisionFault('uninstall_package',
        array('signature'=>'nonexistent-cas-ci-'.$key),
        'uninstall_package_gate',function(){return true;},true);
    absentAtRevisionFault('run_processor',
        array('processor'=>'context/getlist','properties'=>array()),
        'run_processor_gate',function(){return true;},true);
    absentAtRevisionFault('regenerate_token',array(),
        'regenerate_token_gate',function(){return true;},true);
    absentAtRevisionFault('empty_recycle_bin',array(),
        'empty_recycle_bin_gate',function(){return true;},true);

    requireCas(rev()===$initial,'blocked mutations never increment revision');
    echo 'CAS_EXTERNAL_DENIAL_MATRIX_OK=11'.PHP_EOL;
    $pdo->exec('DROP TRIGGER IF EXISTS `'.$trigger.'`');
    $injected=false;

    // Positive controls: the same media tools DO modify the fixture after
    // the fault injector is removed, and each increases revision once.
    $prev=rev();
    rpc('update_media_file',array('source'=>1,'path'=>$base,'content'=>'beta-'.$key));
    requireCas(getFile($base)==='beta-'.$key,'MCP update success readback');
    requireCas(rev()===$prev+1,'MCP update revision increment once');

    $prev=rev();
    rpc('rename_media_file',array('source'=>1,'path'=>$base,'new_name'=>'renamed.txt'));
    $filesCreated=array($renamed);
    requireCas(getFile($base)===null && getFile($renamed)==='beta-'.$key,
        'MCP rename success readback');
    requireCas(rev()===$prev+1,'MCP rename revision increment once');

    $prev=rev();
    rpc('create_media_file',array('source'=>1,'path'=>$dir,'name'=>'must-not-exist.txt','content'=>'fresh-'.$key));
    $filesCreated[]=$created;
    requireCas(getFile($created)==='fresh-'.$key,'MCP create success readback');
    requireCas(rev()===$prev+1,'MCP create revision increment once');

    $prev=rev();
    rpc('delete_media_file',array('source'=>1,'path'=>$created));
    $filesCreated=array($renamed);
    requireCas(getFile($created)===null,'MCP delete success readback');
    requireCas(rev()===$prev+1,'MCP delete revision increment once');

    $prev=rev();
    rpc('delete_media_folder',array('source'=>1,'path'=>$empty));
    $hasEmpty=false;
    requireCas(!folderPresent($empty),'MCP delete folder readback');
    requireCas(rev()===$prev+1,'MCP folder delete revision increment once');

    // Concurrent external write test: independent PHP clients race with the
    // same strict site revision. Shared mutation lock and precondition check
    // must yield exactly one successful writer, one STALE_STATE, and one
    // revision increment. Both candidates change only this disposable file.
    $raceRevision=rev();
    $beforeRace=getFile($renamed);
    $raceWorkers=array();
    $raceGate=getenv('RUNNER_TEMP').'/cas-ext-'.$key.'.gate';
    for($i=0;$i<2;$i++){
        $readyFile=getenv('RUNNER_TEMP').'/cas-ext-'.$key.'-ready-'.$i;
        $nextValue='racer-'.$i.'-'.$key;
        $cmd=array(
            PHP_BINARY,__DIR__.'/test.ci-external-cas-worker.php',
            $renamed,(string)$raceRevision,(string)$beforeRace,
            $nextValue,$readyFile,$raceGate,
        );
        $pipes=array();
        $proc=proc_open($cmd,array(
            0=>array('pipe','r'),1=>array('pipe','w'),2=>array('pipe','w')
        ),$pipes);
        requireCas(is_resource($proc),'start external CAS worker');
        fclose($pipes[0]);
        $raceWorkers[]=array($proc,$pipes,$readyFile,$nextValue);
    }
    $deadline=microtime(true)+15;
    while ((!is_file($raceWorkers[0][2]) || !is_file($raceWorkers[1][2]))
        && microtime(true)<$deadline) usleep(10000);
    requireCas(is_file($raceWorkers[0][2]) && is_file($raceWorkers[1][2]),
        'two media writers ready');
    file_put_contents($raceGate,'go');
    $outcomes=array();
    foreach ($raceWorkers as $worker) {
        $stdout=stream_get_contents($worker[1][1]);
        $stderr=stream_get_contents($worker[1][2]);
        fclose($worker[1][1]);fclose($worker[1][2]);
        $exit=proc_close($worker[0]);
        requireCas($exit===0,'external CAS worker exited cleanly: '.$stderr);
        $outcomes[]=trim($stdout);
    }
    requireCas(count(array_filter($outcomes,function($v){
        return $v==='EXTERNAL_CAS_WORKER_OK';
    }))===1,'exactly one external writer committed');
    requireCas(count(array_filter($outcomes,function($v){
        return $v==='EXTERNAL_CAS_WORKER_STALE';
    }))===1,'other external writer rejected as stale');
    requireCas(rev()===$raceRevision+1,'external race revision incremented once');
    $winner=getFile($renamed);
    requireCas(in_array($winner,array($raceWorkers[0][3],$raceWorkers[1][3]),true),
        'external race persisted exactly one writer');
    rpc('update_media_file',array('source'=>1,'path'=>$renamed,'content'=>$beforeRace,
        '_runtime_preconditions'=>array(
            'observed_site_revision'=>$raceRevision+1,
            'strict_site_revision'=>true,'read_set'=>array(),
        )));
    requireCas(getFile($renamed)===$beforeRace,'external race restored via CAS');
    echo 'CAS_EXTERNAL_PARALLEL_WRITERS_ONE_WINNER_OK'.PHP_EOL;

    $prev=rev();
    $failure=null;
    try {
        rpc('update_media_file',array(
            'source'=>1,'path'=>$dir.'/does-not-exist.txt','content'=>'wrong'));
    } catch (\Throwable $e){$failure=$e->getMessage();}
    requireCas($failure!==null,'post-reservation tool error is reported');
    requireCas(rev()===$prev+1,
        'post-reservation tool failure consumes revision conservatively');
    requireCas(getFile($renamed)==='beta-'.$key,'error did not touch unrelated file');
    echo 'CAS_EXTERNAL_SUCCESS_AND_FAILURE_PATHS_OK'.PHP_EOL;

    // Path traversal and malicious names must be denied by the MCP endpoint,
    // not left to individual MODX media-source implementations. Every call
    // is real MCP; no test fixture escapes its disposable media folder.
    $unsafe=array(
        array('create_media_file',array(
            'source'=>1,'path'=>'../','name'=>'escape-'.$key.'.txt','content'=>'no')),
        array('update_media_file',array(
            'source'=>1,'path'=>$dir.'/../'.$dir.'/renamed.txt','content'=>'no')),
        array('rename_media_file',array(
            'source'=>1,'path'=>$renamed,'new_name'=>'../escape.txt')),
        array('delete_media_file',array(
            'source'=>1,'path'=>$dir.'/../'.$dir.'/renamed.txt')),
        array('create_media_folder',array(
            'source'=>1,'parent'=>'/','name'=>'../escape')),
        array('delete_media_folder',array(
            'source'=>1,'path'=>'../'.$dir)),
    );
    foreach($unsafe as $case) {
        $revision=rev();
        $failure=null;
        try {rpc($case[0],$case[1]);}
        catch(\Throwable $e) {$failure=$e->getMessage();}
        requireCas(is_string($failure)
            && (strpos($failure,'Path traversal')!==false
                || strpos($failure,'Unsafe media')!==false),
            'MCP must reject unsafe '.$case[0].': '.(string)$failure);
        requireCas(rev()===$revision+1,
            'invalid external request consumes one conservative revision');
        requireCas(getFile($renamed)==='beta-'.$key,
            'unsafe '.$case[0].' did not modify valid media file');
    }
    echo 'CAS_MEDIA_TRAVERSAL_DENIED_VIA_MCP=6'.PHP_EOL;
    $ready=true;
} finally {
    if ($injected && $pdo) {
        try { $pdo->exec('DROP TRIGGER IF EXISTS `'.$trigger.'`'); }
        catch (\Throwable $e) { fwrite(STDERR,'EXTERNAL_CI_TRIGGER_CLEANUP_FAILED '.$e->getMessage().PHP_EOL); }
    }
    // Only MCP mutations clean disposable files and directories.
    foreach (array_reverse($filesCreated) as $path) {
        try { rpc('delete_media_file',array('source'=>1,'path'=>$path)); }
        catch (\Throwable $e) {fwrite(STDERR,'EXTERNAL_CI_FILE_CLEANUP_ERROR '.$e->getMessage().PHP_EOL);}
    }
    if ($hasEmpty) {
        try {rpc('delete_media_folder',array('source'=>1,'path'=>$empty));}
        catch (\Throwable $e) {fwrite(STDERR,'EXTERNAL_CI_EMPTY_CLEANUP_ERROR '.$e->getMessage().PHP_EOL);}
    }
    if ($hasDir) {
        try {rpc('delete_media_folder',array('source'=>1,'path'=>$dir));}
        catch (\Throwable $e) {fwrite(STDERR,'EXTERNAL_CI_DIR_CLEANUP_ERROR '.$e->getMessage().PHP_EOL);}
    }
    if ($ready) {
        requireCas(!folderPresent($dir) && !folderPresent($empty)
            && !folderPresent($other),'all disposable media paths removed via MCP');
    }
}
if (!$ready) throw new RuntimeException('External CAS matrix incomplete');
echo 'CAS_EXTERNAL_MCP_ONLY_OK checks='.$checked.PHP_EOL;
