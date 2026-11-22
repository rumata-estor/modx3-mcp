<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Отдельное CAS-доказательство создания политики без выдачи прав.
 * EN: Access policy creation CAS evidence without granting permissions.
 */
if (PHP_SAPI !== 'cli' || getenv('MODXMCP_DISPOSABLE_CI') !== '1'
    || strpos((string)getenv('MODX_CONFIG_CORE'), '/home/runner/') !== 0
    || !is_file((string)getenv('MODX_CONFIG_CORE'))) {
    fwrite(STDERR,'Refusing ACL evidence tests outside disposable CI.'.PHP_EOL);
    exit(3);
}
require getenv('MODX_CONFIG_CORE');
$is2=!is_file(MODX_CORE_PATH.'src/Revolution/modX.php');
if ($is2) {
    require MODX_CORE_PATH.'model/modx/modx.class.php';
    $modx=new modX();
} else {
    require MODX_CORE_PATH.'vendor/autoload.php';
    $modx=\MODX\Revolution\modX::getInstance();
}
$modx->initialize('mgr');
$modx->setOption('modxmcp.disabled_groups','');
require MODX_CORE_PATH.'components/modxmcp/model/modxmcp.class.php';
require __DIR__.'/test.cas-mcp-helper.php';
$api=new modxMCP($modx);
$checks=0;
function casCreateRpc($action,array $data=array()) {
    global $api;
    return disposableCasRpc($api,$action,$data);
}
function casCreateCheck($test,$label) {
    global $checks;
    if(!$test)throw new RuntimeException('CAS_CREATE_EVIDENCE_FAILED '.$label);
    $checks++;
}
$fixture=bin2hex(random_bytes(5));
$policyId=0;
try {
    $templates=casCreateRpc('list_access_policy_templates',array('limit'=>100));
    casCreateCheck(isset($templates['results'])
        && is_array($templates['results']),'policy template list');
    $templateId=0;
    foreach($templates['results'] as $template) {
        if(is_array($template) && (int)($template['id']??0)>0) {
            $templateId=(int)$template['id'];
            break;
        }
    }
    casCreateCheck($templateId>0,'preexisting system template available');
    $name='MCP_Disposable_Policy_'.$fixture;
    $state=casCreateRpc('get_site_state');
    $guard=array('observed_site_revision'=>(int)$state['site_revision'],
        'strict_site_revision'=>true,'read_set'=>array());
    $request=array('name'=>$name,'description'=>'isolated CI only',
        'template'=>$templateId,'data'=>'{}',
        '_runtime_preconditions'=>$guard);
    $created=casCreateRpc('create_access_policy',$request);
    $policyId=(int)($created['id']??0);
    casCreateCheck($policyId>0,'policy created with unique ID');
    $actual=casCreateRpc('get_access_policy',array('id'=>$policyId));
    casCreateCheck(is_array($actual)
        && (int)($actual['id']??0)===$policyId
        && (string)($actual['name']??'')===$name
        && (int)($actual['template']??0)===$templateId,
        'policy independent identity readback');
    $after=casCreateRpc('get_site_state');
    casCreateCheck((int)$after['site_revision']
        ===(int)$state['site_revision']+1,'policy CAS revision +1');
    $stale=false;
    try {casCreateRpc('create_access_policy',$request);}
    catch(ModxMCPClientException $e){
        $stale=strpos($e->getMessage(),'STALE_STATE')!==false;
    }
    casCreateCheck($stale,'policy create stale CAS denied');
    $afterReplay=casCreateRpc('get_site_state');
    casCreateCheck((int)$afterReplay['site_revision']
        ===(int)$after['site_revision'],'stale policy replay did not advance revision');
    $list=casCreateRpc('list_access_policies',array('query'=>$name,'limit'=>100));
    casCreateCheck(isset($list['results']) && is_array($list['results']),
        'policies list available');
    $matches=0;
    foreach($list['results'] as $row) {
        if(is_array($row) && (string)($row['name']??'')===$name)$matches++;
    }
    casCreateCheck($matches===1,'stale replay produced no duplicate policy');
    echo 'CAS_POLICY_CREATE_EVIDENCE_OK platform='.($is2?'modx2':'modx3').PHP_EOL;
}finally {
    if($policyId>0)casCreateRpc('delete_access_policy',array('id'=>$policyId));
}
