<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Только одноразовая CI: без индекса все ACL membership writes отказывают.
 * EN: Disposable CI: without the lock index no group/membership write may run.
 */
if (PHP_SAPI !== 'cli' || getenv('MODXMCP_DISPOSABLE_CI') !== '1'
    || strpos((string)getenv('MODX_CONFIG_CORE'),'/home/runner/') !== 0
    || !is_file((string)getenv('MODX_CONFIG_CORE'))) exit(3);
require getenv('MODX_CONFIG_CORE');
$is2=!is_file(MODX_CORE_PATH.'src/Revolution/modX.php');
if($is2){require MODX_CORE_PATH.'model/modx/modx.class.php';$modx=new modX();}
else{require MODX_CORE_PATH.'vendor/autoload.php';$modx=\MODX\Revolution\modX::getInstance();}
$modx->initialize('mgr');
$modx->setOption('modxmcp.disabled_groups','');
require MODX_CORE_PATH.'components/modxmcp/model/modxmcp.class.php';
require __DIR__.'/test.cas-mcp-helper.php';
$api=new modxMCP($modx);
$class=$is2?'modUserGroupMember':'MODX\\Revolution\\modUserGroupMember';
$table=$modx->getTableName($class);
$indexes=$modx->query('SHOW INDEX FROM '.$table);
$indexed=false;
if(!$indexes)throw new RuntimeException('Missing membership index metadata');
foreach($indexes->fetchAll(PDO::FETCH_ASSOC) as $index) {
    if($index['Column_name']==='user_group' && (int)$index['Seq_in_index']===1) $indexed=true;
}
if($indexed){echo 'ACL_MEMBERSHIP_INDEX_FAIL_CLOSED_OK indexed=yes preexisting=yes'.PHP_EOL;exit(0);}
$state=$api->processRequest('get_site_state','',array());
$revision=(int)$state['site_revision'];
$guard=array('observed_site_revision'=>$revision,
    'strict_site_revision'=>true,'read_set'=>array());
$actions=array(
    'update_user_group'=>array('id'=>2147483646,'name'=>'NEVER_WRITE'),
    'delete_user_group'=>array('id'=>2147483646),
    'add_user_to_group'=>array('usergroup'=>2147483646,'user'=>2147483645,'role'=>1),
    'update_group_member'=>array('usergroup'=>2147483646,'user'=>2147483645,'role'=>1),
    'remove_user_from_group'=>array('usergroup'=>2147483646,'user'=>2147483645),
);
foreach($actions as $name=>$args) {
    $args['_runtime_preconditions']=$guard;
    $denied=false;
    try { $api->processRequest($name,'',$args); }
    catch (ModxMCPClientException $e) {
        $denied=strpos($e->getMessage(),'CAS_REQUIRED:')===0;
    }
    if(!$denied)throw new RuntimeException('ACL_MISSING_INDEX_ALLOWED_'.$name);
    $after=$api->processRequest('get_site_state','',array());
    if((int)$after['site_revision']!==$revision)
        throw new RuntimeException('ACL_MISSING_INDEX_SHIFTED_REVISION_'.$name);
}
echo 'ACL_MEMBERSHIP_INDEX_FAIL_CLOSED_OK actions='.count($actions).PHP_EOL;
