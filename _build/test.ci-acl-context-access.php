<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Полный жизненный цикл ACL контекста, только disposable GitHub CI.
 * EN: Context ACL grant/revoke and CAS replay regression, disposable CI only.
 */
if (PHP_SAPI !== 'cli' || getenv('MODXMCP_DISPOSABLE_CI') !== '1'
    || strpos((string)getenv('MODX_CONFIG_CORE'), '/home/runner/') !== 0
    || !is_file((string)getenv('MODX_CONFIG_CORE'))) {
    fwrite(STDERR, "Refusing context ACL test outside disposable GitHub CI.\n");
    exit(3);
}
require_once getenv('MODX_CONFIG_CORE');
$is2 = !is_file(MODX_CORE_PATH . 'src/Revolution/modX.php');
if ($is2) {
    require_once MODX_CORE_PATH . 'model/modx/modx.class.php';
    $modx = new modX();
} else {
    require_once MODX_CORE_PATH . 'vendor/autoload.php';
    $modx = \MODX\Revolution\modX::getInstance();
}
$modx->initialize('mgr');
require_once MODX_CORE_PATH . 'components/modxmcp/model/modxmcp.class.php';
require_once __DIR__.'/test.cas-mcp-helper.php';
$api=new modxMCP($modx);
$modx->setOption('modxmcp.disabled_groups','');
$checks=0;
$groupId=0;
$policyId=0;
$aclId=0;
$attemptedGrant=false;
$fixture=bin2hex(random_bytes(5));
function aclCheck($ok,$reason) {
    global $checks;
    if (!$ok) throw new RuntimeException('ACL_CONTEXT_LIVE_FAILED: '.$reason);
    $checks++;
}
function aclRpc($action,array $data=array()){
    global $api;
    return disposableCasRpc($api,$action,$data);
}
function aclRules($groupId) {
    $list=aclRpc('list_context_access',array(
        'usergroup'=>$groupId,'context'=>'web','limit'=>500
    ));
    aclCheck(isset($list['results']) && is_array($list['results']),
        'context ACL list response');
    return $list['results'];
}
function aclMatchingRules($rows,$groupId,$policyId) {
    return array_values(array_filter($rows,function($row) use($groupId,$policyId){
        return is_array($row)
            && (int)($row['principal'] ?? 0)===$groupId
            && (int)($row['policy'] ?? 0)===$policyId
            && (string)($row['target'] ?? '')==='web';
    }));
}
try {
    $policies=aclRpc('list_access_policies',array('limit'=>500));
    aclCheck(isset($policies['results']) && is_array($policies['results']),
        'policy list via MCP');
    foreach($policies['results'] as $policy) {
        if(is_array($policy) && (string)($policy['name'] ?? '')==='Context') {
            $policyId=(int)$policy['id'];break;
        }
    }
    aclCheck($policyId>0,'default Context policy exists');
    $group=aclRpc('create_user_group',array(
        'name'=>'MCP_Disposable_ContextACL_'.$fixture
    ));
    $groupId=(int)($group['id'] ?? 0);
    aclCheck($groupId>0,'disposable group created');
    $before=aclRules($groupId);
    aclCheck(count(aclMatchingRules($before,$groupId,$policyId))===0,
        'no preexisting ACL for disposable group');
    $snapshot=aclRpc('get_site_state');
    $grant=array(
        'principal'=>$groupId,'target'=>'web',
        'policy'=>$policyId,'authority'=>999,
        '_runtime_preconditions'=>array(
            'observed_site_revision'=>(int)$snapshot['site_revision'],
            'strict_site_revision'=>true,'read_set'=>array()
        ),
    );
    $attemptedGrant=true;
    $created=aclRpc('grant_context_access',$grant);
    $aclId=(int)($created['id'] ?? 0);
    $actual=aclMatchingRules(aclRules($groupId),$groupId,$policyId);
    aclCheck(count($actual)===1,'grant persisted exactly once');
    $aclId=(int)$actual[0]['id'];
    aclCheck($aclId>0 && (int)$actual[0]['authority']===999,
        'only limited authority 999 was granted');
    $denied=false;
    try {aclRpc('grant_context_access',$grant);}
    catch(ModxMCPClientException $e){
        $denied=strpos($e->getMessage(),'STALE_STATE')!==false;
    }
    aclCheck($denied,'stale grant replay rejected before processor');
    $still=aclMatchingRules(aclRules($groupId),$groupId,$policyId);
    aclCheck(count($still)===1 && (int)$still[0]['id']===$aclId,
        'stale replay did not duplicate grant');
    aclRpc('revoke_context_access',array('id'=>$aclId));
    $aclId=0;
    aclCheck(count(aclMatchingRules(aclRules($groupId),$groupId,$policyId))===0,
        'revoked grant no longer readable');
    aclRpc('delete_user_group',array('id'=>$groupId));
    $groupId=0;
    echo 'ACL_CONTEXT_ACCESS_LIFECYCLE_OK platform='
        .($is2?'modx2':'modx3').' checks='.$checks.PHP_EOL;
} finally {
    if ($groupId>0 && $attemptedGrant) {
        // RU: После неопределённого результата сперва читаем состояние MCP.
        // EN: Reconcile through MCP, never blindly repeat grant or assume rollback.
        try {
            $rows=aclMatchingRules(aclRules($groupId),$groupId,$policyId);
            foreach($rows as $row){
                if((int)($row['id'] ?? 0)>0){
                    aclRpc('revoke_context_access',array('id'=>(int)$row['id']));
                }
            }
        } catch(Throwable $e) {
            fwrite(STDERR,'ACL_CONTEXT_CLEANUP_FAILED: '.$e->getMessage().PHP_EOL);
            throw $e;
        }
    }
    if($groupId>0)aclRpc('delete_user_group',array('id'=>$groupId));
}
