<?php
/** Disposable MariaDB 10.11 InnoDB nonce durability/replay probe. */
if (getenv('GITHUB_ACTIONS')!=='true' ||
    getenv('MODXMCP_CI_DB_HOST')!=='127.0.0.1' ||
    getenv('MODXMCP_CI_DB_NAME')!=='modx_build' ||
    getenv('MODXMCP_CI_DB_USER')!=='modx_ci' ||
    getenv('MODXMCP_CI_DB_PASSWORD')!=='ci-throwaway-only-user') {
    throw new RuntimeException('refusing non-CI nonce test');
}
require_once __DIR__.'/../core/components/modxmcp/endpoint/permit-nonce-ledger-db.php';
function nonceDb() {
    return new PDO('mysql:host=127.0.0.1;dbname=modx_build;charset=utf8mb4',
        'modx_ci','ci-throwaway-only-user',array(
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES=>false));
}
function denyNonce($callback, $why) {
    try { $callback(); }
    catch (Exception $e) { return; }
    throw new RuntimeException('nonce ledger admitted: '.$why);
}
$db=nonceDb();
$table='modxmcp_ci_nonces';
$db->exec('DROP TABLE IF EXISTS `'.$table.'`');
$db->exec('CREATE TABLE `'.$table.'` ('
    .'nonce_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,'
    .'reserved_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP'
    .') ENGINE=InnoDB');
try {
    $ledger=new ModxMcpPermitNonceLedgerDb($db,$table);
    $nonce=str_repeat('a',32);
    $ledger->reserve($nonce);
    $exists=(int)$db->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn();
    if ($exists!==1) { throw new RuntimeException('nonce not persisted'); }
    denyNonce(function() use ($ledger,$nonce){$ledger->reserve($nonce);},'same instance replay');
    $independent=new ModxMcpPermitNonceLedgerDb(nonceDb(),$table);
    denyNonce(function() use ($independent,$nonce){$independent->reserve($nonce);},
              'independent connection replay');
    denyNonce(function() use ($independent){$independent->reserve('invalid');},
              'invalid nonce');
    $db->beginTransaction();
    try {
        denyNonce(function() use ($ledger){$ledger->reserve(str_repeat('b',32));},
                  'transaction-bound nonce was rolled back');
    } finally {
        $db->rollBack();
    }
    if ((int)$db->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn()!==1) {
        throw new RuntimeException('unsafe extra nonce stored');
    }
    echo "INNODB_NONCE_ONE_USE_COMMITTED_OK\n";
} finally {
    $db->exec('DROP TABLE IF EXISTS `'.$table.'`');
}
