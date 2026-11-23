<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Явная миграция ACL-индекса на существующих установках MODX 2/3.
 * EN: Explicit, idempotent migration. Never called by transport installers.
 */
if (PHP_SAPI !== 'cli') exit(3);
$flags=array_slice($argv,1);
$allowed=array('--check','--apply','--ack-backup','--ack-writes-paused','--ci-disposable');
foreach($flags as $flag) {
    if(!in_array($flag,$allowed,true)) {
        fwrite(STDERR,"ACL_INDEX_MIGRATION_DENIED: unknown option\n");exit(3);
    }
}
$apply=in_array('--apply',$flags,true);
$check=in_array('--check',$flags,true);
$ci=in_array('--ci-disposable',$flags,true);
if(count($flags)!==count(array_unique($flags)) || $apply===$check) {
    fwrite(STDERR,"Usage: --check OR --apply --ack-backup --ack-writes-paused\n");exit(3);
}
if($check && count($flags)!==1)exit(3);
$config=(string)getenv('MODX_CONFIG_CORE');
if($config===''||!is_file($config)) {
    fwrite(STDERR,"ACL_INDEX_MIGRATION_DENIED: configuration unavailable\n");exit(3);
}
if($apply) {
    if($ci) {
        if(count($flags)!==2 || getenv('MODXMCP_DISPOSABLE_CI')!=='1'
            || strpos($config,'/home/runner/')!==0
            || strpos((string)getenv('RUNNER_TEMP'),'/home/runner/')!==0) {
            fwrite(STDERR,"ACL_INDEX_MIGRATION_DENIED: not disposable CI\n");exit(3);
        }
    } elseif(count($flags)!==3
        || !in_array('--ack-backup',$flags,true)
        || !in_array('--ack-writes-paused',$flags,true)) {
        fwrite(STDERR,"ACL_INDEX_MIGRATION_DENIED: backup and paused writers not acknowledged\n");
        exit(3);
    }
}
require_once $config;
$is2=!is_file(MODX_CORE_PATH.'src/Revolution/modX.php');
if($is2) {
    require_once MODX_CORE_PATH.'model/modx/modx.class.php';
    $modx=new modX();
    $memberClass='modUserGroupMember';
    $groupClass='modUserGroup';
} else {
    require_once MODX_CORE_PATH.'vendor/autoload.php';
    $modx=\MODX\Revolution\modX::getInstance();
    $memberClass='MODX\Revolution\modUserGroupMember';
    $groupClass='MODX\Revolution\modUserGroup';
}
$modx->initialize('mgr');
$table=$modx->getTableName($memberClass);
$groupTable=$modx->getTableName($groupClass);
foreach(array($table,$groupTable) as $id) {
    if(!is_string($id) || !preg_match('/\A(?:`[A-Za-z0-9_]+`|[A-Za-z0-9_]+)\z/D',$id)) {
        throw new RuntimeException('ACL_INDEX_MIGRATION_DENIED: unsafe table identifier');
    }
}
$schema=(string)$modx->query('SELECT DATABASE()')->fetchColumn();
if($schema==='' || ($ci&&$schema!=='modx_build')) {
    throw new RuntimeException('ACL_INDEX_MIGRATION_DENIED: wrong database');
}
foreach(array($table,$groupTable) as $id) {
    $stmt=$modx->prepare('SELECT ENGINE FROM information_schema.TABLES '
        .'WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    if(!$stmt || !$stmt->execute(array(trim($id,chr(96))))
        || strtoupper((string)$stmt->fetchColumn())!=='INNODB') {
        throw new RuntimeException('ACL_INDEX_MIGRATION_DENIED: both tables need InnoDB');
    }
}
$col=$modx->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS '
    .'WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
if(!$col || !$col->execute(array(trim($table,chr(96)),'user_group'))
    || (int)$col->fetchColumn()!==1) {
    throw new RuntimeException('ACL_INDEX_MIGRATION_DENIED: missing column');
}
function aclIndexState($modx,$table) {
    $stmt=$modx->query('SHOW INDEX FROM '.$table);
    if(!$stmt)throw new RuntimeException('ACL_INDEX_MIGRATION_DENIED: cannot read indexes');
    $found=false;$collision=false;
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if(!isset($row['Column_name'],$row['Seq_in_index'],$row['Key_name'])) {
            throw new RuntimeException('ACL_INDEX_MIGRATION_DENIED: malformed index metadata');
        }
        $leading=($row['Column_name']==='user_group'
            && (int)$row['Seq_in_index']===1);
        if($leading)$found=true;
        if($row['Key_name']==='idx_modxmcp_cas_user_group' && !$leading) {
            $collision=true;
        }
    }
    return array($found,$collision);
}
list($found,$collision)=aclIndexState($modx,$table);
if($collision)throw new RuntimeException('ACL_INDEX_MIGRATION_DENIED: index name collision');
if($found) {
    echo 'ACL_INDEX_MIGRATION_ALREADY_PRESENT platform='.($is2?'modx2':'modx3').PHP_EOL;
    exit(0);
}
if(!$apply) {
    echo 'ACL_INDEX_MIGRATION_REQUIRED table='.trim($table,chr(96)).PHP_EOL;
    exit(0);
}
if($modx->exec('SET SESSION lock_wait_timeout=5')===false
    || $modx->exec('SET SESSION innodb_lock_wait_timeout=5')===false) {
    throw new RuntimeException('ACL_INDEX_MIGRATION_DENIED: cannot bound wait');
}
// Online-only DDL. Never use COPY/LOCK=SHARED as fallback.
try {
    $sql='ALTER TABLE '.$table
        .' ADD INDEX `idx_modxmcp_cas_user_group` (`user_group`), '
        .'ALGORITHM=INPLACE, LOCK=NONE';
    if($modx->exec($sql)===false)throw new RuntimeException('DDL returned false');
} catch(Throwable $e) {
    throw new RuntimeException('ACL_INDEX_MIGRATION_NOT_APPLIED: online DDL '
        .'failed; inspect schema and retry with writers paused');
}
list($found,$collision)=aclIndexState($modx,$table);
if(!$found || $collision) {
    throw new RuntimeException('ACL_INDEX_MIGRATION_OUTCOME_UNCERTAIN: inspect indexes');
}
echo 'ACL_INDEX_MIGRATION_APPLIED platform='.($is2?'modx2':'modx3').PHP_EOL;
