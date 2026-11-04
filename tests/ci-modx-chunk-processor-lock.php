<?php
/**
 * CI-only MODX processor / xPDO transaction identity probe.
 * Not an endpoint or a live-site operation. Disposable CMS in GitHub Actions.
 */
$root=getenv('RUNNER_TEMP');
$config=getenv('MODX_CONFIG_CORE');
$platform=getenv('BUILD_PLATFORM');
if (getenv('GITHUB_ACTIONS') !== 'true' ||
    !in_array($platform,array('modx2','modx3'),true) ||
    !is_string($root) || !$root || !is_string($config) ||
    realpath($config) !== realpath($root.'/modx-site/config.core.php')) {
    throw new RuntimeException('Refuse non-disposable MODX runtime');
}
require $config;
if ($platform==='modx3') {
    require MODX_CORE_PATH.'vendor/autoload.php';
    $modx=\MODX\Revolution\modX::getInstance();
    $chunkClass='MODX\\Revolution\\modChunk';
    $userClass='MODX\\Revolution\\modUser';
    $processor='MODX\\Revolution\\Processors\\Element\\Chunk\\Update';
} else {
    require MODX_CORE_PATH.'model/modx/modx.class.php';
    $modx=new modX();
    $chunkClass='modChunk';
    $userClass='modUser';
    $processor='element/chunk/update';
}
$modx->initialize('mgr');
if (!method_exists($modx,'getConnection')) {
    throw new RuntimeException('MODX database connection not exposed');
}
$db=$modx->getConnection();
if (!($db instanceof PDO)) {
    throw new RuntimeException('MODX connection is not PDO');
}
$table=$modx->getTableName($chunkClass);
if (!is_string($table) || !preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/D',$table)) {
    throw new RuntimeException('unexpected chunk table name');
}
$user=$modx->getObject($userClass,array('username'=>'testadmin'));
if (!$user) { throw new RuntimeException('disposable MODX admin unavailable'); }
$modx->user=$user;
$name='MODXMCP_CAS_CI_'.bin2hex(random_bytes(5));
$before='Before locked processor test';
$after='After locked processor test';
$chunk=$modx->newObject($chunkClass);
$chunk->set('name',$name);
$chunk->set('snippet',$before);
$chunk->set('static',0);
if (!$chunk->save()) { throw new RuntimeException('could not create disposable chunk'); }
$id=(int)$chunk->get('id');
$get=$db->prepare('SELECT snippet FROM `'.$table.'` WHERE id=?');
try {
    if ($modx->beginTransaction() === false) {
        throw new RuntimeException('transaction unavailable');
    }
    try {
        $lock=$db->prepare('SELECT snippet, static FROM `'.$table.'` WHERE id=? FOR UPDATE');
        $lock->execute(array($id));
        $original=$lock->fetch(PDO::FETCH_ASSOC);
        if (!is_array($original) || $original['snippet']!==$before || (int)$original['static']!==0) {
            throw new RuntimeException('locked baseline not exact');
        }
        $response=$modx->runProcessor($processor,array(
            'id'=>$id,'name'=>$name,'snippet'=>$after,'static'=>0
        ));
        if (!$response || $response->isError()) {
            throw new RuntimeException('disposable MODX chunk processor failed');
        }
    } finally {
        $modx->rollback();
    }
    $get->execute(array($id));
    $persisted=$get->fetchColumn();
    if ($persisted!==$before) {
        throw new RuntimeException('MODX update processor bypassed locked transaction');
    }
    echo 'MODX_CHUNK_PROCESSOR_LOCK_ROLLBACK_OK '.$platform."\n";
} finally {
    $modx->removeObject($chunkClass,array('id'=>$id));
}
