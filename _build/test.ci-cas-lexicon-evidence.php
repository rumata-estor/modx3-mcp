<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Отдельное CAS-доказательство создания одноразового словаря и отмены override.
 * EN: Disposable lexicon override/revert CAS evidence with isolated namespace.
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
function lexRpc($action,array $data=array()) {
    global $api;
    return disposableCasRpc($api,$action,$data);
}
function lexCheck($test,$label) {
    global $checks;
    if(!$test)throw new RuntimeException('CAS_CREATE_EVIDENCE_FAILED '.$label);
    $checks++;
}
$fixture=bin2hex(random_bytes(5));
$ns='mcpdisposablelex'.$fixture;
$key='mcp_cas_lex_'.$fixture;
$createdNamespace=false;
function lexGuard(){
    $state=lexRpc('get_site_state');
    return array('observed_site_revision'=>(int)$state['site_revision'],
        'strict_site_revision'=>true,'read_set'=>array());
}
function lexReplay($action,array $request){
    $before=lexRpc('get_site_state');$denied=false;
    try{lexRpc($action,$request);}
    catch(ModxMCPClientException $e){$denied=strpos($e->getMessage(),'STALE_STATE')!==false;}
    lexCheck($denied,$action.' stale replay denied');
    $after=lexRpc('get_site_state');
    lexCheck((int)$before['site_revision']===(int)$after['site_revision'],
        $action.' stale replay consumed no revision');
}
function lexFind($ns,$key){
    $list=lexRpc('list_lexicon_entries',array(
        'namespace'=>$ns,'topic'=>'default','language'=>'en',
        'search'=>$key,'limit'=>100));
    lexCheck(isset($list['results'])&&is_array($list['results']),
        'lexicon list available');
    return array_values(array_filter($list['results'],function($entry)use($key){
        return is_array($entry) && ($entry['name']??'')===$key;
    }));
}
try{
    // RU: Собственное namespace, никакие встроенные переводы не меняем.
    // EN: Unique namespace; never edit core translations.
    lexRpc('create_namespace',array('name'=>$ns,'path'=>'core/components/'));
    $createdNamespace=true;
    $request=array('name'=>$key,'value'=>'MCP_'.$fixture,
        'namespace'=>$ns,'topic'=>'default','language'=>'en',
        '_runtime_preconditions'=>lexGuard());
    lexRpc('set_lexicon_entry',$request);
    $found=lexFind($ns,$key);
    lexCheck(count($found)===1,'override exists exactly once');
    lexCheck(($found[0]['value']??'')==='MCP_'.$fixture,
        'override readback value persisted');
    lexReplay('set_lexicon_entry',$request);
    lexCheck(count(lexFind($ns,$key))===1,'stale override no duplicate');
    echo 'CAS_LEXICON_SET_READBACK_STALE_REPLAY_OK'.PHP_EOL;

    $revert=array('name'=>$key,'namespace'=>$ns,'topic'=>'default',
        'language'=>'en','_runtime_preconditions'=>lexGuard());
    lexRpc('revert_lexicon_entry',$revert);
    lexReplay('revert_lexicon_entry',$revert);
    lexCheck(count(lexFind($ns,$key))===0,
        'reverted database override absent');
    echo 'CAS_LEXICON_REVERT_READBACK_STALE_REPLAY_OK'.PHP_EOL;
}finally{
    if($createdNamespace)lexRpc('delete_namespace',array('name'=>$ns));
}
echo 'CAS_LEXICON_EVIDENCE_OK checks='.$checks
    .' platform='.($is2?'modx2':'modx3').PHP_EOL;
