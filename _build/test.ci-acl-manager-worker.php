<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Только disposable CI: независимый MODX Manager изменяет ACL-объект.
 * EN: Disposable CI only: independent MODX Manager changes ACL target.
 */
if (PHP_SAPI !== 'cli' || getenv('MODXMCP_DISPOSABLE_CI') !== '1'
    || strpos((string)getenv('MODX_CONFIG_CORE'), '/home/runner/') !== 0
    || !is_file((string)getenv('MODX_CONFIG_CORE'))
    || count($argv)!==4) {
    fwrite(STDERR,"Refusing ACL Manager writer outside disposable CI.\n");
    exit(3);
}
require getenv('MODX_CONFIG_CORE');
$is2=!is_file(MODX_CORE_PATH.'src/Revolution/modX.php');
if($is2){require MODX_CORE_PATH.'model/modx/modx.class.php';$modx=new modX();}
else{require MODX_CORE_PATH.'vendor/autoload.php';$modx=\MODX\Revolution\modX::getInstance();}
$modx->initialize('mgr');
$classes=array(
    'user_group'=>$is2?'modUserGroup':'MODX\Revolution\modUserGroup',
    'role'=>$is2?'modUserGroupRole':'MODX\Revolution\modUserGroupRole',
    'user'=>$is2?'modUserProfile':'MODX\Revolution\modUserProfile'
);
$type=$argv[1];
$id=filter_var($argv[2],FILTER_VALIDATE_INT);
if(!isset($classes[$type]) || !$id || $id<=0)exit(4);
$criteria=$type==='user'?array('internalKey'=>$id):$id;
$object=$modx->getObject($classes[$type],$criteria);
if(!$object)throw new RuntimeException('Disposable Manager ACL target not found');
$field=$type==='role'?'authority':($type==='user'?'fullname':'name');
$object->set($field,$type==='role'?(int)$argv[3]:$argv[3]);
if(!$object->save())throw new RuntimeException('Disposable Manager ACL save failed');
echo 'DISPOSABLE_MANAGER_ACL_EDIT_OK '.$type.PHP_EOL;
