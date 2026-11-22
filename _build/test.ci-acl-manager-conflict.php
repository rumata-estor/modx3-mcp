<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Менеджер меняет ACL вне MCP при прежней site_revision; старый CAS запрещён.
 * EN: Independent Manager ACL edit without revision change must deny stale MCP writes.
 */
if (PHP_SAPI !== 'cli' || getenv('MODXMCP_DISPOSABLE_CI') !== '1'
    || strpos((string)getenv('MODX_CONFIG_CORE'),'/home/runner/') !== 0
    || !is_file((string)getenv('MODX_CONFIG_CORE')))exit(3);
require getenv('MODX_CONFIG_CORE');
$is2=!is_file(MODX_CORE_PATH.'src/Revolution/modX.php');
if($is2){require MODX_CORE_PATH.'model/modx/modx.class.php';$modx=new modX();}
else{require MODX_CORE_PATH.'vendor/autoload.php';$modx=\MODX\Revolution\modX::getInstance();}
$modx->initialize('mgr');
require_once MODX_CORE_PATH.'components/modxmcp/model/modxmcp.class.php';
require_once __DIR__.'/test.cas-mcp-helper.php';
$api=new modxMCP($modx);
$modx->setOption('modxmcp.disabled_groups','');
$fixture=bin2hex(random_bytes(6));
$checks=0;
$targets=array();
function aclManagerCheck($ok,$label) {
    global $checks;
    if(!$ok)throw new RuntimeException('ACL_MANAGER_CAS_CONFLICT_FAILED '.$label);
    $checks++;
}
function aclManagerRpc($action,array $data=array()) {
    global $modx,$api;
    $modx->setOption('modxmcp.disabled_groups','');
    return disposableCasRpc($api,$action,$data);
}
function aclManagerSnapshot($type,$id) {
    $state=aclManagerRpc('get_site_state',array('read_set'=>array(
        array('role'=>'target','type'=>$type,'id'=>$id)
    )));
    aclManagerCheck(!empty($state['read_set'][0]['fingerprint']),
        'server-issued ACL target fingerprint '.$type);
    return $state;
}
function aclManagerEdit($type,$id,$value) {
    $cmd=escapeshellarg(PHP_BINARY).' '
        .escapeshellarg(__DIR__.'/test.ci-acl-manager-worker.php').' '
        .escapeshellarg($type).' '.escapeshellarg((string)$id).' '
        .escapeshellarg((string)$value);
    $output=array();$rc=-1;
    exec($cmd.' 2>&1',$output,$rc);
    if($rc!==0)throw new RuntimeException('ACL_MANAGER_CHILD_FAILED '.$type.': '.implode(' ', $output));
}
function aclManagerGuard($state) {
    return array('observed_site_revision'=>(int)$state['site_revision'],
        'strict_site_revision'=>true,'read_set'=>$state['read_set']);
}
try {
    $group=aclManagerRpc('create_user_group',array('name'=>'mcpCasGroup'.$fixture));
    $targets['user_group']=(int)$group['id'];
    $role=aclManagerRpc('create_role',array('name'=>'mcpCasRole'.$fixture,'authority'=>988));
    $targets['role']=(int)$role['id'];
    // RU: Не тестируем изменение существующего пользователя администратора.
    // EN: Create an inactive disposable user; never touch a real admin.
    $user=aclManagerRpc('create_user',array(
        'username'=>'mcpcas'.$fixture,'fullname'=>'Initial '.$fixture,
        'email'=>'mcpcas'.$fixture.'@example.invalid',
        'password'=>'CI-'.bin2hex(random_bytes(16)).'9Q!',
        'passwordnotifymethod'=>'s','active'=>0
    ));
    $targets['user']=(int)$user['id'];
    $cases=array(
        'user_group'=>array('action'=>'update_user_group','field'=>'name',
            'changed'=>'ManagerGroup'.$fixture,'after'=>'McpGroup'.$fixture),
        'role'=>array('action'=>'update_role','field'=>'authority',
            'changed'=>987,'after'=>986),
        'user'=>array('action'=>'update_user','field'=>'fullname',
            'changed'=>'ManagerUser'.$fixture,'after'=>'McpUser'.$fixture),
    );
    foreach($cases as $type=>$case) {
        $id=$targets[$type];
        aclManagerCheck($id>0,'fixture created '.$type);
        $before=aclManagerSnapshot($type,$id);
        aclManagerEdit($type,$id,$case['changed']);
        $current=aclManagerRpc('get_site_state');
        aclManagerCheck((int)$current['site_revision']===(int)$before['site_revision'],
            'Manager edit does not increment MCP site revision '.$type);
        $args=array_merge(array('id'=>$id,$case['field']=>$case['after']),
            array('_runtime_preconditions'=>aclManagerGuard($before)));
        $denied=false;
        try {aclManagerRpc($case['action'],$args);}
        catch(\ModxMCPClientException $e) {
            $denied=strpos($e->getMessage(),'STALE_STATE')!==false;
        }
        aclManagerCheck($denied,'independent Manager edit rejected '.$type);
        // RU: Удаление со старым отпечатком также не должно уничтожить объект.
        // EN: A stale target fingerprint must also block destructive deletes.
        $deleteActions=array(
            'user'=>'delete_user','role'=>'delete_role',
            'user_group'=>'delete_user_group'
        );
        $deleteDenied=false;
        try {
            aclManagerRpc($deleteActions[$type],array(
                'id'=>$id,'_runtime_preconditions'=>aclManagerGuard($before)
            ));
        } catch(\ModxMCPClientException $e) {
            $deleteDenied=strpos($e->getMessage(),'STALE_STATE')!==false;
        }
        aclManagerCheck($deleteDenied,'stale delete blocked after Manager edit '.$type);
        $read=aclManagerRpc('get_'.$type,array('id'=>$id));
        aclManagerCheck((string)($read[$case['field']]??'')===(string)$case['changed'],
            'Manager edit preserved '.$type);
        // RU: Не повторяем запись после конфликта в этом негативном тесте.
        // EN: Positive updates are tested separately; never blind-retry here.
    }
    echo 'ACL_MANAGER_EXTERNAL_EDIT_TARGET_CAS_OK platform='
        .($is2?'modx2':'modx3').' checks='.$checks.PHP_EOL;
} finally {
    foreach(array('user'=>'delete_user','role'=>'delete_role',
        'user_group'=>'delete_user_group') as $type=>$action) {
        if(!empty($targets[$type])) {
            try {aclManagerRpc($action,array('id'=>$targets[$type]));}
            catch(\Throwable $e) {
                fwrite(STDERR,'ACL_TEST_CLEANUP_FAILED '.$type.PHP_EOL);
                throw $e;
            }
        }
    }
}
