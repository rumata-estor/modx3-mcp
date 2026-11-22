<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Отдельное CAS-доказательство создания изменения свойства собственного чанка.
 * EN: Disposable chunk one-property update and expected-value CAS evidence.
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
function epRpc($action,array $data=array()) {
    global $api;
    return disposableCasRpc($api,$action,$data);
}
function epCheck($test,$label) {
    global $checks;
    if(!$test)throw new RuntimeException('CAS_CREATE_EVIDENCE_FAILED '.$label);
    $checks++;
}
$fixture=bin2hex(random_bytes(5));
$id=0;
function epGuard(){
    $state=epRpc('get_site_state');
    return array('observed_site_revision'=>(int)$state['site_revision'],
        'strict_site_revision'=>true,'read_set'=>array());
}
function epProperty($id,$name){
    $read=epRpc('get_element',array('type'=>'chunk','id'=>$id));
    $props=isset($read['properties'])?$read['properties']:array();
    if(is_string($props))$props=json_decode($props,true);
    epCheck(is_array($props),'chunk properties read as array');
    return isset($props[$name])&&is_array($props[$name])
        ? $props[$name] : null;
}
try{
    // RU: Создаём чанк только через MCP. Определение свойств фикстуры
    // RU: устанавливаем штатным API disposable MODX (нет MCP create property).
    // EN: MCP creates the chunk; native disposable API seeds property definitions.
    $created=epRpc('create_element',array('type'=>'chunk',
        'name'=>'MCP_Property_Update_'.$fixture,
        'content'=>'STATIC_ONLY_'.$fixture));
    $id=(int)($created['id']??0);
    epCheck($id>0,'disposable chunk created through MCP');
    $class=$is2?'modChunk':'MODX\\Revolution\\modChunk';
    $chunk=$modx->getObject($class,$id);
    epCheck($chunk!==null,'disposable chunk native object available');
    $fixtureProperties=array(
        'mcp_edit'=>array('name'=>'mcp_edit','type'=>'textfield',
            'value'=>'ORIGINAL_'.$fixture,'desc'=>'disposable test property'),
        'mcp_keep'=>array('name'=>'mcp_keep','type'=>'textfield',
            'value'=>'KEEP_'.$fixture,'desc'=>'independent sentinel'),
    );
    $chunk->set('properties',$fixtureProperties);
    epCheck($chunk->save()!==false,'seed two disposable properties');
    $first=epProperty($id,'mcp_edit');
    epCheck(($first['value']??'')==='ORIGINAL_'.$fixture,
        'initial value independently read through MCP');
    epCheck((epProperty($id,'mcp_keep')['value']??'')==='KEEP_'.$fixture,
        'sentinel property independently readable');

    $request=array('type'=>'chunk','id'=>$id,
        'property_name'=>'mcp_edit','expected_value'=>'ORIGINAL_'.$fixture,
        'value'=>'UPDATED_'.$fixture,'_runtime_preconditions'=>epGuard());
    $result=epRpc('update_element_property',$request);
    epCheck(!empty($result['changed']),'element property update applied');
    epCheck((epProperty($id,'mcp_edit')['value']??'')==='UPDATED_'.$fixture,
        'modified property persisted through MCP readback');
    epCheck((epProperty($id,'mcp_keep')['value']??'')==='KEEP_'.$fixture,
        'unrelated property preserved');
    $after=epRpc('get_site_state');
    epCheck((int)$after['site_revision']
        ===(int)$request['_runtime_preconditions']['observed_site_revision']+1,
        'property update advanced revision once');
    $stale=false;
    try{epRpc('update_element_property',$request);}
    catch(ModxMCPClientException $e){
        $stale=strpos($e->getMessage(),'STALE_STATE')!==false;
    }
    epCheck($stale,'old snapshot rejected on replay');
    $afterReplay=epRpc('get_site_state');
    epCheck((int)$afterReplay['site_revision']===(int)$after['site_revision'],
        'stale replay consumed no revision');
    epCheck((epProperty($id,'mcp_edit')['value']??'')==='UPDATED_'.$fixture,
        'stale replay left new value untouched');
    $wrongExpected=false;
    $bad=array('type'=>'chunk','id'=>$id,'property_name'=>'mcp_edit',
        'expected_value'=>'ORIGINAL_'.$fixture,
        'value'=>'OVERWRITTEN_'.$fixture,'_runtime_preconditions'=>epGuard());
    try{epRpc('update_element_property',$bad);}
    catch(ModxMCPClientException $e){
        $wrongExpected=strpos($e->getMessage(),'STALE_STATE')!==false;
    }
    epCheck($wrongExpected,'per-property expected_value mismatch rejected');
    epCheck((epProperty($id,'mcp_keep')['value']??'')==='KEEP_'.$fixture,
        'wrong expected_value did not change other property');
    echo 'CAS_ELEMENT_PROPERTY_UPDATE_READBACK_STALE_REPLAY_OK'.PHP_EOL;
}finally{
    if($id>0)epRpc('delete_element',array('type'=>'chunk','id'=>$id));
}
echo 'CAS_ELEMENT_PROPERTY_EVIDENCE_OK checks='.$checks
    .' platform='.($is2?'modx2':'modx3').PHP_EOL;
