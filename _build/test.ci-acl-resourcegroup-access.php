<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Полный жизненный цикл ACL группы ресурсов, только disposable GitHub CI.
 * EN: Resource-group ACL grant/revoke and CAS replay regression, disposable CI only.
 */
if (PHP_SAPI !== 'cli' || getenv('MODXMCP_DISPOSABLE_CI') !== '1'
    || strpos((string)getenv('MODX_CONFIG_CORE'), '/home/runner/') !== 0
    || !is_file((string)getenv('MODX_CONFIG_CORE'))) {
    fwrite(STDERR, "Refusing resource-group ACL test outside disposable GitHub CI.\n");
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
$resourceGroupId=0;
$policyId=0;
$aclId=0;
$attemptedGrant=false;
$fixture=bin2hex(random_bytes(5));
function aclCheck($ok,$reason) {
    global $checks;
    if (!$ok) throw new RuntimeException('ACL_RESOURCEGROUP_LIVE_FAILED: '.$reason);
    $checks++;
}
function aclRpc($action,array $data=array()){
    global $api;
    return disposableCasRpc($api,$action,$data);
}
function aclRules($groupId) {
    $list=aclRpc('list_resourcegroup_access',array(
        'usergroup'=>$groupId,'limit'=>500
    ));
    aclCheck(isset($list['results']) && is_array($list['results']),
        'resourcegroup ACL list response');
    return $list['results'];
}
function aclMatchingRules($rows,$groupId,$policyId) {
    global $resourceGroupId;
    return array_values(array_filter($rows,function($row) use($groupId,$policyId,$resourceGroupId){
        return is_array($row)
            && (int)($row['principal'] ?? 0)===$groupId
            && (int)($row['policy'] ?? 0)===$policyId
            && (int)($row['target'] ?? 0)===$resourceGroupId
            && (string)($row['context_key'] ?? '')==='web';
    }));
}
try {
    $policies=aclRpc('list_access_policies',array('limit'=>500));
    aclCheck(isset($policies['results']) && is_array($policies['results']),
        'policy list via MCP');
    foreach($policies['results'] as $policy) {
        if(is_array($policy) && (string)($policy['name'] ?? '')==='Resource') {
            $policyId=(int)$policy['id'];break;
        }
    }
    aclCheck($policyId>0,'default Resource policy exists');
    // RU: create_resource_group не должен выдавать анонимные права через
    // RU: скрытые access_anon/access_contexts без grant_resourcegroup_access.
    // EN: A generic resource-group creation cannot bypass the independently
    // EN: authorized resourcegroup access grant with hidden access_* fields.
    $deniedName='MCP_Denied_AutoGrant_'.$fixture;
    $autoGrantDenied=false;
    try {
        aclRpc('create_resource_group',array(
            'name'=>$deniedName,'access_anon'=>1,'access_contexts'=>array('web')
        ));
    } catch(ModxMCPClientException $e) {
        $autoGrantDenied=strpos($e->getMessage(),'AUTH_DENIED:')===0;
    }
    aclCheck($autoGrantDenied,'hidden resourcegroup auto-access denied');
    $groupsAfterDenied=aclRpc('list_resource_groups',array(
        'query'=>$deniedName,'limit'=>50
    ));
    aclCheck(isset($groupsAfterDenied['results'])
        && is_array($groupsAfterDenied['results']),
        'resource-group readback after denied auto-grant');
    foreach($groupsAfterDenied['results'] as $row) {
        aclCheck(!is_array($row) || ($row['name'] ?? '')!==$deniedName,
            'denied auto-grant created no resource group');
    }
    $group=aclRpc('create_user_group',array(
        'name'=>'MCP_Disposable_ResourceACL_'.$fixture
    ));
    $groupId=(int)($group['id'] ?? 0);
    aclCheck($groupId>0,'disposable group created');
    $rgroup=aclRpc('create_resource_group',array(
        'name'=>'MCP_Disposable_RGroup_'.$fixture
    ));
    $resourceGroupId=(int)($rgroup['id'] ?? 0);
    aclCheck($resourceGroupId>0,'disposable resource group created');
    $before=aclRules($groupId);
    aclCheck(count(aclMatchingRules($before,$groupId,$policyId))===0,
        'no preexisting ACL for disposable group');
    $snapshot=aclRpc('get_site_state');
    $grant=array(
        'principal'=>$groupId,'target'=>$resourceGroupId,'context_key'=>'web',
        'policy'=>$policyId,'authority'=>999,
        '_runtime_preconditions'=>array(
            'observed_site_revision'=>(int)$snapshot['site_revision'],
            'strict_site_revision'=>true,'read_set'=>array()
        ),
    );
    $attemptedGrant=true;
    $created=aclRpc('grant_resourcegroup_access',$grant);
    $aclId=(int)($created['id'] ?? 0);
    $actual=aclMatchingRules(aclRules($groupId),$groupId,$policyId);
    aclCheck(count($actual)===1,'grant persisted exactly once');
    $aclId=(int)$actual[0]['id'];
    aclCheck($aclId>0 && (int)$actual[0]['authority']===999,
        'only limited authority 999 was granted');
    $denied=false;
    try {aclRpc('grant_resourcegroup_access',$grant);}
    catch(ModxMCPClientException $e){
        $denied=strpos($e->getMessage(),'STALE_STATE')!==false;
    }
    aclCheck($denied,'stale grant replay rejected before processor');
    $still=aclMatchingRules(aclRules($groupId),$groupId,$policyId);
    aclCheck(count($still)===1 && (int)$still[0]['id']===$aclId,
        'stale replay did not duplicate grant');
    // RU: Штатный MODX update требует полный набор полей даже при смене
    // RU: только authority. Проверяем изменение без замены principal/target.
    // EN: Core update needs all ACL fields. Update authority only while
    // EN: preserving principal, context and policy through complete input.
    $updateSnapshot=aclRpc('get_site_state');
    $update=array(
        'id'=>$aclId,'principal'=>$groupId,'target'=>$resourceGroupId,'context_key'=>'web',
        'policy'=>$policyId,'authority'=>998,
        '_runtime_preconditions'=>array(
            'observed_site_revision'=>(int)$updateSnapshot['site_revision'],
            'strict_site_revision'=>true,'read_set'=>array()
        ),
    );
    aclRpc('update_resourcegroup_access',$update);
    $afterUpdate=aclMatchingRules(aclRules($groupId),$groupId,$policyId);
    aclCheck(count($afterUpdate)===1 && (int)$afterUpdate[0]['authority']===998
        && (int)$afterUpdate[0]['id']===$aclId,
        'resourcegroup ACL authority updated without new grant');
    $staleUpdateDenied=false;
    try {aclRpc('update_resourcegroup_access',$update);}
    catch(ModxMCPClientException $e){
        $staleUpdateDenied=strpos($e->getMessage(),'STALE_STATE')!==false;
    }
    aclCheck($staleUpdateDenied,'resourcegroup ACL update stale CAS denied');
    $afterStale=aclMatchingRules(aclRules($groupId),$groupId,$policyId);
    aclCheck(count($afterStale)===1 && (int)$afterStale[0]['authority']===998,
        'rejected update preserved final authority');
    aclRpc('revoke_resourcegroup_access',array('id'=>$aclId));
    $aclId=0;
    aclCheck(count(aclMatchingRules(aclRules($groupId),$groupId,$policyId))===0,
        'revoked grant no longer readable');
    aclRpc('delete_resource_group',array('id'=>$resourceGroupId));
    $resourceGroupId=0;
    aclRpc('delete_user_group',array('id'=>$groupId));
    $groupId=0;
    echo 'ACL_RESOURCEGROUP_ACCESS_LIFECYCLE_OK platform='
        .($is2?'modx2':'modx3').' checks='.$checks.PHP_EOL;
} finally {
    if ($groupId>0 && $attemptedGrant) {
        // RU: После неопределённого результата сперва читаем состояние MCP.
        // EN: Reconcile through MCP, never blindly repeat grant or assume rollback.
        try {
            $rows=aclMatchingRules(aclRules($groupId),$groupId,$policyId);
            foreach($rows as $row){
                if((int)($row['id'] ?? 0)>0){
                    aclRpc('revoke_resourcegroup_access',array('id'=>(int)$row['id']));
                }
            }
        } catch(Throwable $e) {
            fwrite(STDERR,'ACL_CONTEXT_CLEANUP_FAILED: '.$e->getMessage().PHP_EOL);
            throw $e;
        }
    }
    if($resourceGroupId>0)aclRpc('delete_resource_group',array('id'=>$resourceGroupId));
    if($groupId>0)aclRpc('delete_user_group',array('id'=>$groupId));
}
