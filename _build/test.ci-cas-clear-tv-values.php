<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Отдельное CAS-доказательство очистки значения собственной TV.
 * EN: Disposable TV value clear evidence.
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
function tvRpc($action,array $data=array()) {
    global $api;
    return disposableCasRpc($api,$action,$data);
}
function tvCheck($test,$label) {
    global $checks;
    if(!$test)throw new RuntimeException('CAS_CREATE_EVIDENCE_FAILED '.$label);
    $checks++;
}
$fixture=bin2hex(random_bytes(6));
$tvName='mcp_ci_clear_'.$fixture;
$tvId=0;
$resourceId=0;
function tvGuard(){
    $state=tvRpc('get_site_state');
    return array('observed_site_revision'=>(int)$state['site_revision'],
        'strict_site_revision'=>true,'read_set'=>array());
}
function tvRows($id){
    return tvRpc('list_tv_values',array('tv_id'=>$id,'limit'=>100));
}
try {
    // RU: Используем только собственный уникальный TV и тестовую страницу.
    // EN: Never operate on shared site TV definitions or resources.
    $templates=tvRpc('list_elements',array('type'=>'template','limit'=>50));
    tvCheck(is_array($templates) && count($templates)>0,'template list available');
    $templateId=(int)$templates[0]['id'];
    tvCheck($templateId>0,'valid template ID');
    $tv=tvRpc('create_element',array('type'=>'tv','name'=>$tvName,
        'field_type'=>'text','templates'=>array($templateId)));
    $tvId=(int)($tv['id']??0);
    tvCheck($tvId>0,'isolated TV created');
    $resource=tvRpc('create_element',array('type'=>'resource',
        'name'=>'MCP CI Clear TV '.$fixture,'alias'=>'mcp-tv-clear-'.$fixture,
        'template'=>$templateId,'context_key'=>'web',
        'published'=>0,'hidemenu'=>1,'parent'=>0));
    $resourceId=(int)($resource['id']??0);
    tvCheck($resourceId>0,'isolated unpublished resource created');
    $value='unique_ci_'.$fixture;
    tvRpc('update_resource_tvs',array('resource_id'=>$resourceId,
        'tvs'=>array($tvName=>$value)));
    $initial=tvRows($tvId);
    tvCheck((int)($initial['total']??0)===1,'exactly one stored TV row');
    tvCheck((int)($initial['values'][0]['resource_id']??0)===$resourceId,
        'stored TV row belongs to isolated resource');
    tvCheck(($initial['values'][0]['value']??'')===$value,
        'isolated raw TV value persisted');
    // RU: Предпросмотр не удаляет записи.
    // EN: Dry run must not change stored data.
    $dry=tvRpc('clear_tv_values',array('tv_id'=>$tvId,'confirm'=>false));
    tvCheck(($dry['confirmed']??null)===false,'dry-run not confirmed');
    tvCheck((int)($dry['would_delete']??0)===1,'one row previewed');
    $still=tvRows($tvId);
    tvCheck((int)$still['total']===1,'dry-run did not remove row');
    $request=array('tv_id'=>$tvId,'confirm'=>true,
        '_runtime_preconditions'=>tvGuard());
    $result=tvRpc('clear_tv_values',$request);
    tvCheck(($result['confirmed']??null)===true,'confirmed clear accepted');
    tvCheck((int)($result['deleted']??0)===1,'exactly one row removed');
    $after=tvRows($tvId);
    tvCheck((int)($after['total']??-1)===0,'zero stored TV rows after clear');
    tvCheck(empty($after['values']),'no stored TV values remain');
    $page=tvRpc('get_element',array('type'=>'resource','id'=>$resourceId));
    tvCheck(empty($page['published']),'resource remained unpublished');
    $stateBefore=tvRpc('get_site_state');
    $denied=false;
    try{tvRpc('clear_tv_values',$request);}
    catch(ModxMCPClientException $e){
        $denied=strpos($e->getMessage(),'STALE_STATE')!==false;
    }
    tvCheck($denied,'stale TV clear replay denied');
    $stateAfter=tvRpc('get_site_state');
    tvCheck((int)$stateBefore['site_revision']===(int)$stateAfter['site_revision'],
        'stale replay consumed no revision');
    tvCheck((int)tvRows($tvId)['total']===0,'stale replay left zero stored rows');
    echo 'CAS_CLEAR_TV_VALUES_READBACK_STALE_REPLAY_OK'.PHP_EOL;
}finally{
    if($resourceId>0)tvRpc('delete_element',array('type'=>'resource',
        'id'=>$resourceId,'dry_run'=>false));
    if($tvId>0){
        // RU: MODX запрещает удалять TV, связанную с шаблоном.
        // EN: Detach disposable TV from template before deleting its definition.
        tvRpc('update_element',array('type'=>'tv','id'=>$tvId,
            'templates'=>array()));
        tvRpc('delete_element',array('type'=>'tv','id'=>$tvId));
    }
}
echo 'CAS_CLEAR_TV_VALUES_EVIDENCE_OK checks='.$checks
    .' platform='.($is2?'modx2':'modx3').PHP_EOL;
