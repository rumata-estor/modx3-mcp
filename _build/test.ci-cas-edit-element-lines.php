<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Отдельное CAS-доказательство создания изменения строк собственного чанка.
 * EN: Disposable chunk line edit CAS and expected content guard.
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
function linesRpc($action,array $data=array()) {
    global $api;
    return disposableCasRpc($api,$action,$data);
}
function linesCheck($test,$label) {
    global $checks;
    if(!$test)throw new RuntimeException('CAS_CREATE_EVIDENCE_FAILED '.$label);
    $checks++;
}
$fixture=bin2hex(random_bytes(5));$id=0;
function linesGuard(){
    $state=linesRpc('get_site_state');
    return array('observed_site_revision'=>(int)$state['site_revision'],
        'strict_site_revision'=>true,'read_set'=>array());
}
function linesRead($id){
    return linesRpc('get_element',array('type'=>'chunk','id'=>$id));
}
try{
    // RU: Используем только текстовый тестовый чанк; не исполняем код.
    // EN: Edit only a temporary plain-text chunk; never render it.
    $initial="first_{$fixture}\nsecond_{$fixture}\nthird_{$fixture}";
    $created=linesRpc('create_element',array('type'=>'chunk',
        'name'=>'MCP_CAS_EditLines_'.$fixture,'content'=>$initial));
    $id=(int)($created['id']??0);
    linesCheck($id>0,'created isolated chunk');
    linesCheck((string)(linesRead($id)['content']??'')===$initial,
        'initial content independently read via MCP');
    $replacement='updated_'.$fixture;
    $request=array('type'=>'chunk','id'=>$id,
        'edits'=>array(array('start_line'=>2,'end_line'=>2,
            'expect'=>'second_'.$fixture,'replacement'=>$replacement)),
        '_runtime_preconditions'=>linesGuard());
    $result=linesRpc('edit_element_lines',$request);
    linesCheck((int)($result['edits_applied']??0)===1,
        'one line edit applied');
    $expected="first_{$fixture}\n{$replacement}\nthird_{$fixture}";
    linesCheck((string)(linesRead($id)['content']??'')===$expected,
        'exact line substitution read through MCP');
    $stateBefore=linesRpc('get_site_state');
    $stale=false;
    try{linesRpc('edit_element_lines',$request);}
    catch(ModxMCPClientException $e){
        $stale=strpos($e->getMessage(),'STALE_STATE')!==false;
    }
    linesCheck($stale,'stale line edit replay denied');
    $stateAfter=linesRpc('get_site_state');
    linesCheck((int)$stateBefore['site_revision']===(int)$stateAfter['site_revision'],
        'stale edit did not advance revision');
    $bad=array('type'=>'chunk','id'=>$id,
        'edits'=>array(array('start_line'=>2,'end_line'=>2,
            'expect'=>'wrong_line_never_present','replacement'=>'DANGEROUS')),
        '_runtime_preconditions'=>linesGuard());
    $expectDenied=false;
    try{linesRpc('edit_element_lines',$bad);}
    catch(ModxMCPClientException $e){$expectDenied=true;}
    linesCheck($expectDenied,'mismatched expected source denied');
    linesCheck((string)(linesRead($id)['content']??'')===$expected,
        'denied edit left content unchanged');
    echo 'CAS_EDIT_ELEMENT_LINES_READBACK_STALE_REPLAY_OK'.PHP_EOL;
}finally{
    if($id>0)linesRpc('delete_element',array('type'=>'chunk','id'=>$id));
}
echo 'CAS_EDIT_ELEMENT_LINES_OK checks='.$checks
    .' platform='.($is2?'modx2':'modx3').PHP_EOL;
