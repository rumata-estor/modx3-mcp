<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Отдельное CAS-доказательство создания набора свойств без привязки к элементам.
 * EN: Unassigned property set CAS lifecycle and stale replay evidence.
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
function psRpc($action,array $data=array()) {
    global $api;
    return disposableCasRpc($api,$action,$data);
}
function psCheck($test,$label) {
    global $checks;
    if(!$test)throw new RuntimeException('CAS_CREATE_EVIDENCE_FAILED '.$label);
    $checks++;
}
$fixture=bin2hex(random_bytes(5));
$id=0;
$name='MCP_Disposable_PropertySet_'.$fixture;
function psGuard(){
    $state=psRpc('get_site_state');
    return array('observed_site_revision'=>(int)$state['site_revision'],
        'strict_site_revision'=>true,'read_set'=>array());
}
function psReplay($action,array $request){
    $before=psRpc('get_site_state');
    $denied=false;
    try {psRpc($action,$request);}
    catch(ModxMCPClientException $e){
        $denied=strpos($e->getMessage(),'STALE_STATE')!==false;
    }
    psCheck($denied,$action.' stale CAS replay denied');
    $after=psRpc('get_site_state');
    psCheck((int)$before['site_revision']===(int)$after['site_revision'],
        $action.' stale replay did not consume revision');
}
try {
    // RU: Набор не привязан к существующим шаблонам, сниппетам или TV.
    // EN: Keep the temporary set unattached to any real site elements.
    $request=array('name'=>$name,'description'=>'disposable initial',
        'properties'=>array('tpl'=>array('value'=>'строка'), 'limit'=>array('type'=>'numberfield','value'=>0)), '_runtime_preconditions'=>psGuard());
    $created=psRpc('create_property_set',$request);
    $id=(int)($created['id']??0);
    psCheck($id>0,'create returned property set id');
    $read=psRpc('get_property_set',array('id'=>$id));
    psCheck(is_array($read)&&(int)($read['id']??0)===$id
        && (string)($read['name']??'')===$name,
        'created property set independent readback');
    psCheck(isset($read['assignments']) && $read['assignments']===array(),
        'no unexpected property set assignment');
    $stored=$read['properties']??null;
    psCheck(is_array($stored) && ($stored['tpl']['name']??null)==='tpl'
        && ($stored['tpl']['type']??null)==='textfield'
        && ($stored['tpl']['value']??null)==='строка'
        && ($stored['limit']['value']??null)===0,'native property serialization roundtrip');
    foreach (array('create_property_set','update_property_set') as $operation) {
        foreach (array('bad',array('tpl'=>array('type'=>'textfield')),
            array('tpl'=>array('value'=>array('bad')))) as $bad) {
            $before=psRpc('get_site_state');
            $denied=false;
            try {
                psRpc($operation,array('id'=>$id,'name'=>$name.'_invalid',
                    'properties'=>$bad,'_runtime_preconditions'=>psGuard()));
            } catch (ModxMCPClientException $e) {
                $denied=strpos($e->getMessage(),'VALIDATION_ERROR:')===0;
            }
            psCheck($denied,$operation.' invalid properties denied');
            $after=psRpc('get_site_state');
            psCheck($before['site_revision']===$after['site_revision'],'invalid request did not reserve CAS');
            $unchanged=psRpc('get_property_set',array('id'=>$id));
            psCheck(($unchanged['properties']??null)===$stored && ($unchanged['name']??null)===$name,'invalid request preserved original row');
        }
    }
    psReplay('create_property_set',$request);
    echo 'CAS_PROPERTY_SET_CREATE_READBACK_STALE_REPLAY_OK'.PHP_EOL;

    $update=array('id'=>$id,'description'=>'disposable updated',
        '_runtime_preconditions'=>psGuard());
    psRpc('update_property_set',$update);
    $read=psRpc('get_property_set',array('id'=>$id));
    psCheck(($read['description']??'')==='disposable updated',
        'updated property set independent readback');
    psCheck(($read['assignments']??null)===array(),
        'update did not assign property set');
    psCheck(($read['properties']??null)===$stored,'metadata update preserves properties');
    psReplay('update_property_set',$update);
    psRpc('update_property_set',array('id'=>$id,'properties'=>$stored));
    $read=psRpc('get_property_set',array('id'=>$id));
    psCheck(($read['properties']??null)===$stored,'native readback can be resubmitted');
    psRpc('update_property_set',array('id'=>$id,'properties'=>array()));
    $read=psRpc('get_property_set',array('id'=>$id));
    psCheck(($read['properties']??null)===array(),'explicit empty map clears properties');

    echo 'CAS_PROPERTY_SET_UPDATE_READBACK_STALE_REPLAY_OK'.PHP_EOL;

    $delete=array('id'=>$id,'_runtime_preconditions'=>psGuard());
    psRpc('delete_property_set',$delete);
    $id=0;
    psReplay('delete_property_set',$delete);
    $list=psRpc('list_property_sets',array('limit'=>500));
    foreach(($list['results']??array()) as $row){
        psCheck(!is_array($row)||($row['name']??'')!==$name,
            'deleted property set absent from list');
    }
    echo 'CAS_PROPERTY_SET_DELETE_READBACK_STALE_REPLAY_OK'.PHP_EOL;
}finally {
    if($id>0)psRpc('delete_property_set',array('id'=>$id));
}
echo 'CAS_PROPERTY_SET_LIFECYCLE_OK checks='.$checks
    .' platform='.($is2?'modx2':'modx3').PHP_EOL;
