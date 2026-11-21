<?php
/**
 * End-to-end dispatcher-bound Ed25519 permit test in disposable MODX2/3.
 * Regular resource mutations, readback and soft delete all use MODX MCP.
 * Only temporary *security installation settings* are set via MODX Manager
 * internal APIs; permit nonce table is provisioned via explicit CLI script.
 */
if(PHP_SAPI!=='cli' || getenv('MODXMCP_DISPOSABLE_CI')!=='1'
    || strpos((string)getenv('MODX_CONFIG_CORE'),'/home/runner/')!==0
    || !is_file((string)getenv('MODX_CONFIG_CORE')))exit(3);
require_once getenv('MODX_CONFIG_CORE');
$is2=!is_file(MODX_CORE_PATH.'src/Revolution/modX.php');
if($is2){require_once MODX_CORE_PATH.'model/modx/modx.class.php';$modx=new modX();}
else{require_once MODX_CORE_PATH.'vendor/autoload.php';$modx=\MODX\Revolution\modX::getInstance();}
$modx->initialize('mgr');
require_once MODX_CORE_PATH.'components/modxmcp/model/modxmcp.class.php';
$api=new modxMCP($modx);
$site='ci-protected-'.($is2 ? 'modx2':'modx3');
$private=getenv('RUNNER_TEMP').'/ed25519-private-'.bin2hex(random_bytes(6)).'.pem';
$node=__DIR__.'/test.ci-ed25519-controller.cjs';
$checks=0;
function must($result,$message) {
    global $checks;
    if(!$result)throw new RuntimeException('PROTECTED_AUTH_FAIL '.$message);
    $checks++;
}
function mustDeny($action,$type,$data,$context,$reason='AUTH_DENIED') {
    global $api,$checks;
    try{$api->processRequest($action,$type,$data,$context);}
    catch(\ModxMCPClientException $e){
        if(strpos($e->getMessage(),$reason)===false)throw $e;
        $checks++;return;
    }
    throw new RuntimeException('PROTECTED_AUTH_FAIL accepted unauthorized '.$action);
}
function runController(array $args,$stdin='') {
    $cmd=array_merge(array('node',__DIR__.'/test.ci-ed25519-controller.cjs'),$args);
    $pipes=array();$process=proc_open($cmd,array(
        0=>array('pipe','r'),1=>array('pipe','w'),2=>array('pipe','w')
    ),$pipes);
    if(!is_resource($process))throw new RuntimeException('Could not start CI controller');
    fwrite($pipes[0],$stdin);fclose($pipes[0]);
    $stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);
    fclose($pipes[1]);fclose($pipes[2]);
    if(proc_close($process)!==0)throw new RuntimeException('CI controller failed: '.$stderr);
    return trim($stdout);
}
function securitySetting($key,$value) {
    global $modx,$is2;
    $cls=$is2?'modSystemSetting':'MODX\\Revolution\\modSystemSetting';
    $setting=$modx->getObject($cls,array('key'=>$key));
    if(!$setting){
        $setting=$modx->newObject($cls);
        $setting->set('key',$key);
        $setting->set('xtype','textfield');
        $setting->set('namespace','modxmcp');
        $setting->set('area','modxmcp:security');
    }
    $setting->set('value',(string)$value);
    if(!$setting->save())throw new RuntimeException('Could not configure disposable CI security mode');
}
function guardNow() {
    global $api;
    $state=$api->processRequest('get_site_state','',array());
    return array('observed_site_revision'=>(int)$state['site_revision'],
        'strict_site_revision'=>true,'read_set'=>array());
}
function signedCall($action,$type,array $data) {
    global $private,$site;
    $request=array('action'=>$action,'type'=>$type,'data'=>$data);
    $raw=json_encode($request,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $scopeType=$type!=='' ? $type : (isset($data['type'])?$data['type']:'none');
    $envelope=json_decode(runController(
        array('sign',$private,$site,$action,$scopeType),$raw),true);
    if(!is_array($envelope))throw new RuntimeException('Invalid CI signer output');
    return array('raw_body'=>$raw,'permit'=>$envelope);
}
function rpc($action,$type,array $data,$envelope) {
    global $api;
    return $api->processRequest($action,$type,$data,$envelope);
}
$keyReady=false;$enabled=false;
try {
    $pub=runController(array('generate',$private));
    must(strlen($pub)===64,'Ed25519 public key generated');
    securitySetting('modxmcp.permit_public_key',$pub);
    securitySetting('modxmcp.permit_audience','ci-protected');
    securitySetting('modxmcp.permit_site_id',$site);
    securitySetting('modxmcp.permit_contour','test');
    // Security mode is enabled by the disposable Manager test fixture.
    // No MCP instruction is allowed to change modxmcp.* security settings.
    securitySetting('modxmcp.external_authorization_required','1');
    $enabled=true;
    must(\ModxMcp\Security\ProtectedAuthorization::required($modx),'DB policy really enabled');

    $name='Ed25519 protected CI '.bin2hex(random_bytes(5));
    $data=array(
        'type'=>'resource','name'=>$name,'alias'=>'ed25519-ci-'.bin2hex(random_bytes(5)),
        'content'=>'BEFORE','template'=>0,'parent'=>0,
        'published'=>0,'hidemenu'=>1,'_runtime_preconditions'=>guardNow(),
    );
    mustDeny('create_element','',$data,null);
    $badRaw=signedCall('create_element','',$data);
    $badRaw['raw_body'].=' ';
    mustDeny('create_element','',$data,$badRaw);
    $badSite=signedCall('create_element','',$data);
    $badSite['permit']['claims']['site_id']='other-site';
    mustDeny('create_element','',$data,$badSite);
    $badSignature=signedCall('create_element','',$data);
    $badSignature['permit']['signature']=str_repeat('0',128);
    mustDeny('create_element','',$data,$badSignature);
    $crossOp=signedCall('create_element','',$data);
    mustDeny('update_element','',$data,$crossOp);
    $badType=signedCall('create_element','',$data);
    $badType['permit']['claims']['element_type']='chunk';
    mustDeny('create_element','',$data,$badType);
    $legacy=array('raw_body'=>'{}','permit'=>array('claims'=>array(),'mac'=>str_repeat('a',64)));
    mustDeny('create_element','',$data,$legacy);

    $valid=signedCall('create_element','',$data);
    $result=rpc('create_element','',$data,$valid);
    $id=(int)$result['id'];
    must($id>0,'signed protected resource creation');
    mustDeny('create_element','',$data,$valid);

    $read=$api->processRequest('get_element','',array('type'=>'resource','id'=>$id));
    must((int)$read['published']===0 && (int)$read['hidemenu']===1,'MCP-only readback');

    $update=array('type'=>'resource','id'=>$id,'content'=>'AFTER',
        '_runtime_preconditions'=>guardNow());
    $updated=rpc('update_element','',$update,signedCall('update_element','',$update));
    $read=$api->processRequest('get_element','',array('type'=>'resource','id'=>$id));
    must(is_array($read) && $read['content']==='AFTER','protected update via MCP');

    $stale=array('type'=>'resource','id'=>$id,'content'=>'BAD',
        '_runtime_preconditions'=>array(
            'observed_site_revision'=>0,'strict_site_revision'=>true,'read_set'=>array()
        ));
    try{rpc('update_element','',$stale,signedCall('update_element','',$stale));
        throw new RuntimeException('STALE_STATE unexpectedly permitted');}
    catch(\ModxMCPClientException $e) {
        must(strpos($e->getMessage(),'STALE_STATE')!==false,'CAS still enforced with valid permit');
    }

    // Change to protected trust configuration is prohibited, even with a
    // cryptographically valid permit.
    $unsafe=array('key'=>'modxmcp.external_authorization_required',
        'value'=>'0','_runtime_preconditions'=>guardNow());
    mustDeny('update_system_setting','',$unsafe,
        signedCall('update_system_setting','',$unsafe));

    $delete=array('type'=>'resource','id'=>$id,'dry_run'=>false,
        '_runtime_preconditions'=>guardNow());
    rpc('delete_element','',$delete,signedCall('delete_element','',$delete));
    $deleted=$api->processRequest('get_element','',array('type'=>'resource','id'=>$id));
    must(!empty($deleted['deleted']),'protected soft-delete via MCP');

    echo 'PROTECTED_ED25519_DISPATCHER_INTEGRATION_OK platform='
        .($is2?'modx2':'modx3').' checks='.$checks.PHP_EOL;
} finally {
    if($enabled){
        securitySetting('modxmcp.external_authorization_required','0');
    }
    if(is_file($private))@unlink($private);
}
