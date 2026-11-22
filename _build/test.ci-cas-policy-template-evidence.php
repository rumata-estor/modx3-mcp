<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Отдельное CAS-доказательство создания шаблона политики без выдачи прав.
 * EN: Unassigned access policy template CAS evidence without granting permissions.
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
$templateId=0;
try {
    // RU: Штатный список MODX выводит шаблоны существующих групп.
    // EN: Bind disposable template to a real template group for list readback.
    $existing=casCreateRpc('list_access_policy_templates',array('limit'=>500));
    $groupId=0;
    foreach($existing['results'] as $row) {
        if(is_array($row) && (int)($row['template_group']??0)>0) {
            $groupId=(int)$row['template_group'];
            break;
        }
    }
    casCreateCheck($groupId>0,'existing template group available');
    $name='MCP_Disposable_Template_'.$fixture;
    $state=casCreateRpc('get_site_state');
    $guard=array('observed_site_revision'=>(int)$state['site_revision'],
        'strict_site_revision'=>true,'read_set'=>array());
    $request=array('name'=>$name,'description'=>'isolated CI only',
        'template_group'=>$groupId,
        '_runtime_preconditions'=>$guard);
    $created=casCreateRpc('create_access_policy_template',$request);
    $templateId=(int)($created['id']??0);
    casCreateCheck($templateId>0,'policy created with unique ID');
    $actual=casCreateRpc('get_access_policy_template',array('id'=>$templateId));
    casCreateCheck(is_array($actual)
        && (int)($actual['id']??0)===$templateId
        && (string)($actual['name']??'')===$name
        && (int)($actual['template_group']??0)===$groupId,
        'policy independent identity readback');
    $after=casCreateRpc('get_site_state');
    casCreateCheck((int)$after['site_revision']
        ===(int)$state['site_revision']+1,'policy CAS revision +1');
    $stale=false;
    try {casCreateRpc('create_access_policy_template',$request);}
    catch(ModxMCPClientException $e){
        $stale=strpos($e->getMessage(),'STALE_STATE')!==false;
    }
    casCreateCheck($stale,'policy create stale CAS denied');
    $afterReplay=casCreateRpc('get_site_state');
    casCreateCheck((int)$afterReplay['site_revision']
        ===(int)$after['site_revision'],'stale policy replay did not advance revision');
    $list=casCreateRpc('list_access_policy_templates',array('limit'=>500));
    casCreateCheck(isset($list['results']) && is_array($list['results']),
        'policies list available');
    $matches=0;
    foreach($list['results'] as $row) {
        if(is_array($row) && (string)($row['name']??'')===$name)$matches++;
    }
    if($matches!==1) {
        // RU: Только безопасная метаинформация одноразовых тестовых записей.
        // EN: Log schema keys only, never values or credentials.
        $first=isset($list['results'][0]) && is_array($list['results'][0])
            ? array_keys($list['results'][0]) : array();
        fwrite(STDERR,'POLICY_TEMPLATE_LIST_DIAG matches='.$matches
            .' rows='.count($list['results'])
            .' fields='.implode(',', $first).PHP_EOL);
    }
    casCreateCheck($matches===1,'stale replay produced no duplicate policy');
    echo 'CAS_POLICY_TEMPLATE_CREATE_EVIDENCE_OK platform='.($is2?'modx2':'modx3').PHP_EOL;
    // RU: Изменяем только описание отдельной, никому не выданной политики.
    // EN: Update only an unassigned disposable policy description.
    $updateState=casCreateRpc('get_site_state');
    $updateGuard=array('observed_site_revision'=>(int)$updateState['site_revision'],
        'strict_site_revision'=>true,'read_set'=>array());
    $updateRequest=array('id'=>$templateId,'name'=>$name,
        'description'=>'modified disposable policy',
        '_runtime_preconditions'=>$updateGuard);
    casCreateRpc('update_access_policy_template',$updateRequest);
    $updated=casCreateRpc('get_access_policy_template',array('id'=>$templateId));
    casCreateCheck((string)($updated['description']??'')
        ==='modified disposable policy','policy update independent readback');
    $afterUpdate=casCreateRpc('get_site_state');
    casCreateCheck((int)$afterUpdate['site_revision']
        ===(int)$updateState['site_revision']+1,'policy update CAS revision +1');
    $staleUpdate=false;
    try {casCreateRpc('update_access_policy_template',$updateRequest);}
    catch(ModxMCPClientException $e){
        $staleUpdate=strpos($e->getMessage(),'STALE_STATE')!==false;
    }
    casCreateCheck($staleUpdate,'stale policy update replay denied');
    $updated=casCreateRpc('get_access_policy_template',array('id'=>$templateId));
    casCreateCheck((string)($updated['description']??'')
        ==='modified disposable policy','stale update did not overwrite policy');
    echo 'CAS_POLICY_TEMPLATE_UPDATE_EVIDENCE_OK'.PHP_EOL;

    // RU: Удаление с фиксированным CAS и повтором по старой ревизии.
    // EN: Delete under fixed CAS then reject stale replay.
    $deleteState=casCreateRpc('get_site_state');
    $deleteRequest=array('id'=>$templateId,'_runtime_preconditions'=>array(
        'observed_site_revision'=>(int)$deleteState['site_revision'],
        'strict_site_revision'=>true,'read_set'=>array()));
    casCreateRpc('delete_access_policy_template',$deleteRequest);
    $templateId=0;
    $afterDelete=casCreateRpc('get_site_state');
    casCreateCheck((int)$afterDelete['site_revision']
        ===(int)$deleteState['site_revision']+1,'policy delete CAS revision +1');
    $staleDelete=false;
    try {casCreateRpc('delete_access_policy_template',$deleteRequest);}
    catch(ModxMCPClientException $e){
        $staleDelete=strpos($e->getMessage(),'STALE_STATE')!==false;
    }
    casCreateCheck($staleDelete,'stale policy delete replay denied');
    $afterReplayDelete=casCreateRpc('get_site_state');
    casCreateCheck((int)$afterReplayDelete['site_revision']
        ===(int)$afterDelete['site_revision'],
        'stale policy delete did not advance CAS revision');
    $listAfterDelete=casCreateRpc('list_access_policy_templates',array(
        'query'=>$name,'limit'=>100));
    foreach($listAfterDelete['results'] as $row){
        casCreateCheck(!is_array($row)
            || (string)($row['name']??'')!==$name,
            'deleted policy absent after stale replay');
    }
    echo 'CAS_POLICY_TEMPLATE_DELETE_EVIDENCE_OK'.PHP_EOL;
}finally {
    if($templateId>0)casCreateRpc('delete_access_policy_template',array('id'=>$templateId));
}
