<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Независимый Manager меняет членство только в одноразовом CI.
 * EN: Independent Manager changes disposable group membership only.
 */
if(PHP_SAPI!=='cli' || getenv('MODXMCP_DISPOSABLE_CI')!=='1'
    || strpos((string)getenv('MODX_CONFIG_CORE'),'/home/runner/')!==0
    || !is_file((string)getenv('MODX_CONFIG_CORE'))
    || !(count($argv)===4 || count($argv)===5))exit(3);
require getenv('MODX_CONFIG_CORE');
$is2=!is_file(MODX_CORE_PATH.'src/Revolution/modX.php');
if($is2){require MODX_CORE_PATH.'model/modx/modx.class.php';$modx=new modX();}
else{require MODX_CORE_PATH.'vendor/autoload.php';$modx=\MODX\Revolution\modX::getInstance();}
$modx->initialize('mgr');
$class=$is2?'modUserGroupMember':'MODX\Revolution\modUserGroupMember';
$row=$modx->newObject($class);
$row->set('user_group',(int)$argv[1]);
$row->set('member',(int)$argv[2]);
$row->set('role',(int)$argv[3]);
if(count($argv)===5) {
    $ready=$argv[4];
    if(strpos($ready,rtrim((string)getenv('RUNNER_TEMP'),'/').'/')!==0)exit(5);
    if(!$modx->beginTransaction())throw new RuntimeException('Cannot begin disposable race transaction');
    if(!$row->save())throw new RuntimeException('Disposable Manager race membership save failed');
    file_put_contents($ready,'uncommitted');
    usleep(1800000);
    if(!$modx->commit())throw new RuntimeException('Disposable Manager membership race commit failed');
} else {
    if(!$row->save())throw new RuntimeException('Disposable Manager membership save failed');
}
echo 'DISPOSABLE_MANAGER_MEMBERSHIP_CHANGED_OK'.PHP_EOL;
