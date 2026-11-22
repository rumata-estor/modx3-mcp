<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Только disposable CI — READ COMMITTED должен давать ранний отказ ACL.
 * EN: Disposable CI only: READ COMMITTED cannot satisfy ACL range lock.
 */
if (PHP_SAPI!=='cli' || getenv('MODXMCP_DISPOSABLE_CI')!=='1'
    || strpos((string)getenv('MODX_CONFIG_CORE'),'/home/runner/')!==0
    || !is_file((string)getenv('MODX_CONFIG_CORE')))exit(3);
require getenv('MODX_CONFIG_CORE');
$is2=!is_file(MODX_CORE_PATH.'src/Revolution/modX.php');
if($is2){require MODX_CORE_PATH.'model/modx/modx.class.php';$modx=new modX();}
else{require MODX_CORE_PATH.'vendor/autoload.php';$modx=\MODX\Revolution\modX::getInstance();}
$modx->initialize('mgr');
$modx->setOption('modxmcp.disabled_groups','');
require MODX_CORE_PATH.'components/modxmcp/model/modxmcp.class.php';
require __DIR__.'/test.cas-mcp-helper.php';
$api=new modxMCP($modx);
$group=disposableCasRpc($api,'create_user_group',array(
    'name'=>'mcp_acl_iso_'.bin2hex(random_bytes(5))));
$id=(int)$group['id'];
try {
    $state=$api->processRequest('get_site_state','',array());
    $revision=(int)$state['site_revision'];
    if($modx->exec('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED')===false)
        throw new RuntimeException('Cannot configure disposable isolation');
    try {
        $guard=array('observed_site_revision'=>$revision,
            'strict_site_revision'=>true,'read_set'=>array());
        $cases=array(
            'update_user_group'=>array('id'=>$id,'name'=>'FORBIDDEN_CHANGED'),
            'delete_user_group'=>array('id'=>$id),
            'add_user_to_group'=>array('usergroup'=>$id,'user'=>2147483645,'role'=>1),
            'update_group_member'=>array('usergroup'=>$id,'user'=>2147483645,'role'=>1),
            'remove_user_from_group'=>array('usergroup'=>$id,'user'=>2147483645)
        );
        foreach($cases as $action=>$data) {
            $data['_runtime_preconditions']=$guard;
            $denied=false;
            try { $api->processRequest($action,'',$data); }
            catch(ModxMCPClientException $e) {
                $denied=strpos($e->getMessage(),'CAS_REQUIRED:')===0;
            }
            if(!$denied)throw new RuntimeException('ACL_READ_COMMITTED_ALLOWED_'.$action);
            $after=$api->processRequest('get_site_state','',array());
            if((int)$after['site_revision']!==$revision)
                throw new RuntimeException('ACL_READ_COMMITTED_SHIFTED_REVISION_'.$action);
        }
    } finally {
        if($modx->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ')===false)
            throw new RuntimeException('Cannot restore disposable isolation');
    }
    $check=disposableCasRpc($api,'get_user_group',array('id'=>$id));
    if((int)$check['id']!==$id)throw new RuntimeException('ACL_READ_COMMITTED_MODIFIED_GROUP');
    echo 'ACL_READ_COMMITTED_FAIL_CLOSED_OK actions=5'.PHP_EOL;
} finally {
    if($id>0)disposableCasRpc($api,'delete_user_group',array('id'=>$id));
}
