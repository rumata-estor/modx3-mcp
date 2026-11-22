<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Отдельное CAS-доказательство создания замены уникальной строки только собственного чанка.
 * EN: Unique token replace-across on disposable chunk with CAS replay.
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
function replaceRpc($action,array $data=array()) {
    global $api;
    return disposableCasRpc($api,$action,$data);
}
function replaceCheck($test,$label) {
    global $checks;
    if(!$test)throw new RuntimeException('CAS_CREATE_EVIDENCE_FAILED '.$label);
    $checks++;
}
$fixture=bin2hex(random_bytes(6));$id=0;
function replaceGuard(){
    $state=replaceRpc('get_site_state');
    return array('observed_site_revision'=>(int)$state['site_revision'],
        'strict_site_revision'=>true,'read_set'=>array());
}
function replaceRead($id){
    return replaceRpc('get_element',array('type'=>'chunk','id'=>$id));
}
try{
    // RU: Уникальный маркер исключает затрагивание существующих чанков.
    // EN: Random marker exists solely in our disposable chunk.
    $needle='MCP_ONLY_OLD_'.$fixture;
    $replacement='MCP_ONLY_NEW_'.$fixture;
    $before="prefix_{$fixture}\n{$needle}\nsuffix_{$fixture}";
    $created=replaceRpc('create_element',array('type'=>'chunk',
        'name'=>'MCP_CAS_Replace_'.$fixture,'content'=>$before));
    $id=(int)($created['id']??0);
    replaceCheck($id>0,'disposable chunk created');
    replaceCheck((string)(replaceRead($id)['content']??'')===$before,
        'initial content verified');
    $criteria=array('find'=>$needle,'replacement'=>$replacement,
        'types'=>array('chunk'),'case_sensitive'=>true,'limit'=>5);
    $preview=replaceRpc('replace_across',array_merge($criteria,
        array('dry_run'=>true)));
    replaceCheck((int)($preview['elements']??0)===1,
        'dry-run found exactly one isolated chunk');
    replaceCheck((int)($preview['total_occurrences']??0)===1,
        'dry-run found exactly one substring');
    replaceCheck(empty($preview['search_limit_reached']),
        'search did not reach truncation boundary');
    replaceCheck((string)(replaceRead($id)['content']??'')===$before,
        'dry-run did not modify content');
    $request=array_merge($criteria,array('dry_run'=>false,
        '_runtime_preconditions'=>replaceGuard()));
    $result=replaceRpc('replace_across',$request);
    replaceCheck((int)($result['elements']??0)===1,
        'replacement touched one chunk only');
    replaceCheck((int)($result['total_occurrences']??0)===1,
        'replacement changed one occurrence only');
    $after="prefix_{$fixture}\n{$replacement}\nsuffix_{$fixture}";
    replaceCheck((string)(replaceRead($id)['content']??'')===$after,
        'replacement independently read via MCP');
    $stateBefore=replaceRpc('get_site_state');$stale=false;
    try{replaceRpc('replace_across',$request);}
    catch(ModxMCPClientException $e){
        $stale=strpos($e->getMessage(),'STALE_STATE')!==false;
    }
    replaceCheck($stale,'stale replace-across replay denied');
    $stateAfter=replaceRpc('get_site_state');
    replaceCheck((int)$stateBefore['site_revision']===(int)$stateAfter['site_revision'],
        'stale replay did not advance site revision');
    replaceCheck((string)(replaceRead($id)['content']??'')===$after,
        'stale replay preserved replacement result');
    echo 'CAS_REPLACE_ACROSS_READBACK_STALE_REPLAY_OK'.PHP_EOL;
}finally{
    if($id>0)replaceRpc('delete_element',array('type'=>'chunk','id'=>$id));
}
echo 'CAS_REPLACE_ACROSS_OK checks='.$checks
    .' platform='.($is2?'modx2':'modx3').PHP_EOL;
