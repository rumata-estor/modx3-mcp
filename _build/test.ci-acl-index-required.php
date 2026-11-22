<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Без нужного индекса ACL-операция должна отказать до записи.
 * EN: Without a safe membership index, protected group updates fail closed.
 */
if(PHP_SAPI!=='cli' || getenv('MODXMCP_DISPOSABLE_CI')!=='1'
    || strpos((string)getenv('MODX_CONFIG_CORE'),'/home/runner/')!==0
    || !is_file((string)getenv('MODX_CONFIG_CORE')))exit(3);
require getenv('MODX_CONFIG_CORE');
$is2=!is_file(MODX_CORE_PATH.'src/Revolution/modX.php');
if($is2){require MODX_CORE_PATH.'model/modx/modx.class.php';$modx=new modX();}
else{require MODX_CORE_PATH.'vendor/autoload.php';$modx=\MODX\Revolution\modX::getInstance();}
$modx->initialize('mgr');
require MODX_CORE_PATH.'components/modxmcp/model/modxmcp.class.php';
require __DIR__.'/test.cas-mcp-helper.php';
$api=new modxMCP($modx);
$name='mcpIndexedGuard'.bin2hex(random_bytes(5));
$groupId=0;
try {
    $group=disposableCasRpc($api,'create_user_group',array('name'=>$name));
    $groupId=(int)$group['id'];
    $snapshot=disposableCasRpc($api,'get_site_state',array('read_set'=>array(
        array('role'=>'target','type'=>'user_group','id'=>$groupId)
    )));
    $guard=array('observed_site_revision'=>(int)$snapshot['site_revision'],
        'strict_site_revision'=>true,'read_set'=>$snapshot['read_set']);
    $class=$is2?'modUserGroupMember':'MODX\Revolution\modUserGroupMember';
    $table=$modx->getTableName($class);
    $index=$modx->query('SHOW INDEX FROM '.$table);
    $indexed=false;
    foreach($index->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if($row['Column_name']==='user_group' && (int)$row['Seq_in_index']===1)
            $indexed=true;
    }
    if(!$indexed) {
        $denied=false;
        try {
            disposableCasRpc($api,'update_user_group',array(
                'id'=>$groupId,'name'=>'MCP_UNSAFE_INDEX_WRITE',
                '_runtime_preconditions'=>$guard));
        }catch(ModxMCPClientException $e){
            $denied=strpos($e->getMessage(),'CAS_REQUIRED:')===0;
        }
        if(!$denied)throw new RuntimeException('ACL_MISSING_INDEX_ALLOWED_MUTATION');
        $read=disposableCasRpc($api,'get_user_group',array('id'=>$groupId));
        if((string)$read['name']!==$name)
            throw new RuntimeException('ACL_MISSING_INDEX_MUTATED_GROUP');
    }
    echo 'ACL_MEMBERSHIP_INDEX_FAIL_CLOSED_OK indexed='.($indexed?'yes':'no').PHP_EOL;
} finally {
    if($groupId)disposableCasRpc($api,'delete_user_group',array('id'=>$groupId));
}
