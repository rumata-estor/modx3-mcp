<?php
/** Only the disposable MODX installation in GitHub CI may run this. */
$root=getenv('RUNNER_TEMP');
$cfg=getenv('MODX_CONFIG_CORE');
$platform=getenv('BUILD_PLATFORM');
if (getenv('GITHUB_ACTIONS')!=='true' ||
    !in_array($platform,array('modx2','modx3'),true) ||
    !is_string($root) || $root==='' || !is_string($cfg) ||
    realpath($cfg)!==realpath($root.'/modx-site/config.core.php')) {
    throw new RuntimeException('disposable CI-only test');
}
require $cfg;
if ($platform==='modx3') {
    require MODX_CORE_PATH.'vendor/autoload.php';
    $modx=\MODX\Revolution\modX::getInstance();
    $chunkClass='MODX\\Revolution\\modChunk';
    $settingClass='MODX\\Revolution\\modSystemSetting';
    $userClass='MODX\\Revolution\\modUser';
    $site='modx3-alex-palochkin';
} else {
    require MODX_CORE_PATH.'model/modx/modx.class.php';
    $modx=new modX();
    $chunkClass='modChunk';
    $settingClass='modSystemSetting';
    $userClass='modUser';
    $site='modx2-alex-palochkin';
}
$modx->initialize('mgr');
require_once __DIR__.'/../core/components/modxmcp/endpoint/chunk-cas-processor-v2.php';
require_once __DIR__.'/../core/components/modxmcp/endpoint/permit-nonce-ledger-db.php';
$db=new PDO('mysql:host=127.0.0.1;dbname=modx_build;charset=utf8mb4',
    'modx_ci','ci-throwaway-only-user',array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
                                              PDO::ATTR_EMULATE_PREPARES=>false));
$nonceTable='modxmcp_v2_nonce_ci';
$auditTable='modxmcp_v2_audit_ci';
$db->exec('DROP TABLE IF EXISTS `'.$nonceTable.'`');
$db->exec('DROP TABLE IF EXISTS `'.$auditTable.'`');
$db->exec('CREATE TABLE `'.$nonceTable.'` (nonce_sha256 CHAR(64) PRIMARY KEY) ENGINE=InnoDB');
$db->exec('CREATE TABLE `'.$auditTable.'` (nonce_sha256 CHAR(64) PRIMARY KEY, '.
    'chunk_id INT NOT NULL, before_sha256 CHAR(64) NOT NULL, '.
    'after_sha256 CHAR(64) NOT NULL, site_revision INT NOT NULL) ENGINE=InnoDB');
$set=function($key,$value) use ($modx,$settingClass) {
    $row=$modx->getObject($settingClass,array('key'=>$key));
    if (!$row) {
        $row=$modx->newObject($settingClass);
        $row->fromArray(array('key'=>$key,'value'=>$value,'xtype'=>'textfield',
            'namespace'=>'modxmcp','area'=>'modxmcp:runtime'),'',true,true);
    } else { $row->set('value',$value); }
    if (!$row->save()) { throw new RuntimeException('failed disposable setting'); }
};
$set('modxmcp.external_authorization_required','1');
$set('modxmcp.auto_static','0');
$set('modxmcp.site_revision','0');
$modx->user=$modx->getObject($userClass,array('username'=>'testadmin'));
$chunk=$modx->newObject($chunkClass);
$chunk->set('name','CAS_V2_CI_'.bin2hex(random_bytes(4)));
$chunk->set('snippet','before');
$chunk->set('static',0);
if (!$chunk->save()) { throw new RuntimeException('disposable chunk missing'); }
$id=(int)$chunk->get('id');
$trusted=array('site_id'=>$site,'contour'=>'test','audience'=>'isolated-ci',
               'auto_static'=>false);
$key=str_repeat('K',32);
$make=function($before,$after) use ($trusted,$key,$id) {
    $raw=json_encode(array('action'=>'update_element','type'=>'chunk',
        'data'=>array('id'=>$id,'content'=>$after)),
        JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_LINE_TERMINATORS);
    $baseline=array('element_type'=>'chunk','id'=>$id,'storage_field'=>'snippet',
        'static'=>false,'before_content'=>$before,
        'before_sha256'=>hash('sha256',$before),'after_sha256'=>hash('sha256',$after));
    $task='TASK-20261104-140000-abcd';
    $claims=array('version'=>'2','audience'=>'isolated-ci','site_id'=>$trusted['site_id'],
        'contour'=>'test','task_id'=>$task,'plan_digest'=>str_repeat('a',64),
        'snapshot_digest'=>ModxMcpCasPermitVerifierV2::observationDigest(
            $trusted['site_id'],$task,$baseline),
        'operation_id'=>'op-1','action'=>'update_element','element_type'=>'chunk',
        'chunk_id'=>(string)$id,'before_sha256'=>$baseline['before_sha256'],
        'after_sha256'=>$baseline['after_sha256'],
        'request_digest'=>hash('sha256',$raw),'nonce'=>bin2hex(random_bytes(16)),
        'expires_at'=>(string)(time()+40));
    return array($raw,array('claims'=>$claims,'baseline'=>$baseline,
        'mac'=>ModxMcpCasPermitWireV2::sign($claims,$key)));
};
$ledger=new ModxMcpPermitNonceLedgerDb($db,$nonceTable);
$run=function($fixture) use ($modx,$trusted,$key,$ledger,$auditTable) {
    return ModxMcpChunkCasProcessorV2::executeUnconnected(
        $modx,$fixture[1],$fixture[0],$trusted,$key,$ledger,$auditTable);
};
$deny=function($callback,$message) {
    try { $callback(); }
    catch (Exception $expected) { return; }
    throw new RuntimeException('unsafe CAS admitted '.$message);
};
$table=trim((string)$modx->getTableName($chunkClass),'`');
$read=$db->prepare('SELECT snippet,static FROM `'.$table.'` WHERE id=?');
$get=function() use ($read,$id) {
    $read->execute(array($id));
    return $read->fetch(PDO::FETCH_ASSOC);
};
try {
    $first=$make('before','after');
    $cacheUnverified=false;
    try {
        $result=$run($first);
    } catch (RuntimeException $error) {
        if ($error->getMessage()!=='CAS persisted but cache refresh unverified') {
            throw $error;
        }
        // This is an UNKNOWN outcome to the caller: do not resend the
        // permit. Prove its database commit separately and report the gap.
        $cacheUnverified=true;
        $result=null;
    }
    if (($result!==null && ($result['site_revision']!==1 ||
            $result['chunk_id']!==$id)) || $get()['snippet']!=='after') {
        throw new RuntimeException('signed CAS processor did not persist desired bytes');
    }
    if ($cacheUnverified) {
        // Only a disposable CI fixture may collect partition diagnostics.
        // No cached payloads, HTTP headers, tokens or filesystem paths.
        $partitions=array();
        $modx->getCacheManager()->refresh(array(),$partitions);
        $failed=array();
        foreach ($partitions as $name=>$status) {
            if ($status===false) { $failed[]=(string)$name; }
        }
        echo "CAS_CACHE_REFRESH_UNVERIFIED_FAIL_CLOSED $platform ".
            json_encode($failed)."\n";
    }
    if ((int)$db->query('SELECT COUNT(*) FROM `'.$auditTable.'`')->fetchColumn()!==1) {
        throw new RuntimeException('transactional audit was not persisted');
    }
    $deny(function() use ($run,$first) { $run($first); },'replayed nonce');
    $stale=$make('before','forged');
    $deny(function() use ($run,$stale) { $run($stale); },'stale original');
    if ($get()['snippet']!=='after') {
        throw new RuntimeException('stale permit mutated chunk');
    }
    $used=(int)$db->query('SELECT COUNT(*) FROM `'.$nonceTable.'`')->fetchColumn();
    $blocked=$make('after','forbidden');
    $set('modxmcp.auto_static','1');
    $deny(function() use ($run,$blocked) { $run($blocked); },'auto_static flag');
    $set('modxmcp.auto_static','0');
    $set('modxmcp.external_authorization_required','0');
    $deny(function() use ($run,$blocked) { $run($blocked); },'authorization flag');
    $set('modxmcp.external_authorization_required','1');
    if ((int)$db->query('SELECT COUNT(*) FROM `'.$nonceTable.'`')->fetchColumn()!==$used) {
        throw new RuntimeException('disabled mode consumed a permit');
    }
    // With no transactional audit table, the processor change and revision
    // must both roll back; the separately committed nonce must remain used.
    $db->exec('DROP TABLE `'.$auditTable.'`');
    $rollback=$make('after','should-rollback');
    $deny(function() use ($run,$rollback) { $run($rollback); },'missing audit');
    if ($get()['snippet']!=='after') {
        throw new RuntimeException('failed audit allowed a processor mutation');
    }
    $revision=$modx->getObject($settingClass,array('key'=>'modxmcp.site_revision'));
    if (!$revision || (string)$revision->get('value')!=='1') {
        throw new RuntimeException('failed CAS did not rollback revision');
    }
    if ((int)$db->query('SELECT COUNT(*) FROM `'.$nonceTable.'`')->fetchColumn()!==$used+1) {
        throw new RuntimeException('failed processor write released nonce');
    }
    echo "SIGNED_V2_CAS_PROCESSOR_ATOMIC_TEST_OK $platform\n";
} finally {
    $modx->removeObject($chunkClass,array('id'=>$id));
    $db->exec('DROP TABLE IF EXISTS `'.$auditTable.'`');
    $db->exec('DROP TABLE IF EXISTS `'.$nonceTable.'`');
}
