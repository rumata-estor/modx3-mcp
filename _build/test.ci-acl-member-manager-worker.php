<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Независимый Manager меняет членство только в одноразовом CI.
 * EN: Independent Manager changes disposable membership in CI only.
 */
if(PHP_SAPI!=='cli' || getenv('MODXMCP_DISPOSABLE_CI')!=='1'
    || strpos((string)getenv('MODX_CONFIG_CORE'),'/home/runner/')!==0
    || !is_file((string)getenv('MODX_CONFIG_CORE'))
    || !in_array(count($argv),array(4,5,6),true))exit(3);
require getenv('MODX_CONFIG_CORE');
$is2=!is_file(MODX_CORE_PATH.'src/Revolution/modX.php');
if($is2){require MODX_CORE_PATH.'model/modx/modx.class.php';$modx=new modX();}
else{require MODX_CORE_PATH.'vendor/autoload.php';$modx=\MODX\Revolution\modX::getInstance();}
$modx->initialize('mgr');
$class=$is2?'modUserGroupMember':'MODX\Revolution\modUserGroupMember';
$mode=count($argv)===6 ? (string)$argv[5] : 'insert';
if(!in_array($mode,array('insert','update_role','delete'),true))exit(4);
$ready=count($argv)>=5 ? $argv[4] : null;
if($ready!==null && strpos($ready,rtrim((string)getenv('RUNNER_TEMP'),'/').'/')!==0)exit(5);
if($ready!==null && !$modx->beginTransaction())
    throw new RuntimeException('Cannot begin disposable Manager transaction');
if($mode==='insert') {
    $row=$modx->newObject($class);
    $row->set('user_group',(int)$argv[1]);
    $row->set('member',(int)$argv[2]);
    $row->set('role',(int)$argv[3]);
} else {
    $row=$modx->getObject($class,array(
        'user_group'=>(int)$argv[1], 'member'=>(int)$argv[2]
    ));
    if(!$row)throw new RuntimeException('Disposable Manager member row missing');
    if($mode==='update_role')$row->set('role',(int)$argv[3]);
}
$ok=$mode==='delete' ? $row->remove() : $row->save();
if(!$ok)throw new RuntimeException('Disposable Manager membership mutation failed: '.$mode);
if($ready!==null) {
    file_put_contents($ready,'uncommitted');
    usleep(1800000);
    if(!$modx->commit())throw new RuntimeException('Disposable Manager commit failed');
}
echo 'DISPOSABLE_MANAGER_MEMBERSHIP_CHANGED_OK mode='.$mode.PHP_EOL;
