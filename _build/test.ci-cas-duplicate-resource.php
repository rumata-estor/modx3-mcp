<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Отдельное CAS-доказательство создания копирования собственной неопубликованной страницы.
 * EN: Disposable resource duplication CAS readback and replay evidence.
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
function copyRpc($action,array $data=array()) {
    global $api;
    return disposableCasRpc($api,$action,$data);
}
function copyCheck($test,$label) {
    global $checks;
    if(!$test)throw new RuntimeException('CAS_CREATE_EVIDENCE_FAILED '.$label);
    $checks++;
}
$fixture=bin2hex(random_bytes(5));
$sourceId=0;$duplicateId=0;
function copyGuard(){
    $site=copyRpc('get_site_state');
    return array('observed_site_revision'=>(int)$site['site_revision'],
        'strict_site_revision'=>true,'read_set'=>array());
}
function copyRead($id){
    return copyRpc('get_element',array('type'=>'resource','id'=>$id));
}
try{
    // RU: Не копируем реальные страницы или вложенных детей.
    // EN: Duplicate only an isolated resource; never recurse into site tree.
    $src=copyRpc('create_element',array('type'=>'resource',
        'name'=>'MCP_CI_Copy_Source_'.$fixture,
        'alias'=>'mcp-copy-source-'.$fixture,
        'template'=>0,'published'=>0,'hidemenu'=>1,
        'content'=>'disposable content '.$fixture,'parent'=>0));
    $sourceId=(int)($src['id']??0);
    copyCheck($sourceId>0,'source created');
    $source=copyRead($sourceId);
    copyCheck(empty($source['published']),'source unpublished');
    $request=array('id'=>$sourceId,
        'name'=>'MCP_CI_Copy_Destination_'.$fixture,
        'duplicate_children'=>false,'published_mode'=>'unpublish',
        '_runtime_preconditions'=>copyGuard());
    $result=copyRpc('duplicate_resource',$request);
    $duplicateId=(int)($result['id']??0);
    copyCheck($duplicateId>0 && $duplicateId!==$sourceId,
        'copy returned new resource ID');
    $copy=copyRead($duplicateId);
    copyCheck((int)($copy['id']??0)===$duplicateId,
        'copy independent MCP readback');
    copyCheck(($copy['content']??'')==='disposable content '.$fixture,
        'content copied precisely');
    copyCheck(empty($copy['published']),'copy stayed unpublished');
    $unchanged=copyRead($sourceId);
    copyCheck(($unchanged['content']??'')===($source['content']??''),
        'source content unchanged');
    copyCheck(empty($unchanged['published']),'source remained unpublished');
    $revisionBefore=copyRpc('get_site_state');
    $denied=false;
    try{copyRpc('duplicate_resource',$request);}
    catch(ModxMCPClientException $e){
        $denied=strpos($e->getMessage(),'STALE_STATE')!==false;
    }
    copyCheck($denied,'stale duplicate replay denied');
    $revisionAfter=copyRpc('get_site_state');
    copyCheck((int)$revisionBefore['site_revision']===(int)$revisionAfter['site_revision'],
        'stale replay did not consume revision');
    $copies=copyRpc('list_elements',array('type'=>'resource',
        'query'=>'MCP_CI_Copy_Destination_'.$fixture,'limit'=>100));
    $matches=0;
    foreach($copies as $row){
        if(is_array($row) && ($row['name']??'')==='MCP_CI_Copy_Destination_'.$fixture){
            $matches++;
        }
    }
    copyCheck($matches===1,'stale replay did not duplicate resource twice');
    echo 'CAS_DUPLICATE_RESOURCE_READBACK_STALE_REPLAY_OK'.PHP_EOL;
} finally {
    if($duplicateId>0)copyRpc('delete_element',array('type'=>'resource',
        'id'=>$duplicateId,'dry_run'=>false));
    if($sourceId>0)copyRpc('delete_element',array('type'=>'resource',
        'id'=>$sourceId,'dry_run'=>false));
}
echo 'CAS_DUPLICATE_RESOURCE_EVIDENCE_OK checks='.$checks
    .' platform='.($is2?'modx2':'modx3').PHP_EOL;
