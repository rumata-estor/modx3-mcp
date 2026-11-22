<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Только disposable CI: подготовка InnoDB-индекса для проверки защиты ACL.
 * EN: Disposable CI only: prepare membership index for ACL locking regression.
 */
if(PHP_SAPI!=='cli' || getenv('MODXMCP_DISPOSABLE_CI')!=='1'
    || strpos((string)getenv('MODX_CONFIG_CORE'),'/home/runner/')!==0
    || !is_file((string)getenv('MODX_CONFIG_CORE')))exit(3);
require getenv('MODX_CONFIG_CORE');
$is2=!is_file(MODX_CORE_PATH.'src/Revolution/modX.php');
if($is2){require MODX_CORE_PATH.'model/modx/modx.class.php';$modx=new modX();}
else{require MODX_CORE_PATH.'vendor/autoload.php';$modx=\MODX\Revolution\modX::getInstance();}
$modx->initialize('mgr');
$class=$is2?'modUserGroupMember':'MODX\Revolution\modUserGroupMember';
$table=$modx->getTableName($class);
$check=$modx->query('SHOW TABLE STATUS LIKE '.$modx->quote(str_replace('`','',$table)));
$info=$check?$check->fetch(PDO::FETCH_ASSOC):null;
if(!is_array($info)||strtoupper((string)$info['Engine'])!=='INNODB')
    throw new RuntimeException('Disposable membership fixture must use InnoDB');
$indexes=$modx->query('SHOW INDEX FROM '.$table);
$exists=false;
foreach($indexes->fetchAll(PDO::FETCH_ASSOC) as $index){
    if($index['Column_name']==='user_group' && (int)$index['Seq_in_index']===1)
        $exists=true;
}
if(!$exists){
    // No production DDL. This exists solely to test the required release migration.
    if(!$modx->exec('CREATE INDEX `idx_modxmcp_ci_user_group` ON '.$table.' (`user_group`)')){
        // PDO::exec returns 0 for successful index creation.
    }
}
$verify=$modx->query('SHOW INDEX FROM '.$table);
$exists=false;
foreach($verify->fetchAll(PDO::FETCH_ASSOC) as $index){
    if($index['Column_name']==='user_group' && (int)$index['Seq_in_index']===1)
        $exists=true;
}
if(!$exists)throw new RuntimeException('Could not create disposable member group index');
echo 'ACL_DISPOSABLE_MEMBERSHIP_INDEX_READY platform='.($is2?'modx2':'modx3').PHP_EOL;
