<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Проверка «ci-protected-auth»; тестовый код не запускается на production.
 * EN: «ci-protected-auth» regression test; not executed in production.
 */
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
function guardTarget($type,$id) {
    global $api;
    $state=$api->processRequest('get_site_state','',array(
        'read_set'=>array(array('role'=>'target','type'=>$type,'id'=>$id))
    ));
    if (!isset($state['read_set'][0]['fingerprint'])
        || strlen($state['read_set'][0]['fingerprint'])!==64) {
        throw new RuntimeException('MCP fingerprint observation was not available');
    }
    return array(
        'observed_site_revision'=>(int)$state['site_revision'],
        'strict_site_revision'=>true,
        'read_set'=>$state['read_set'],
    );
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
$enabled=false;
try {
    // RU: Независимый PHP-процесс исключает утечку состояния от теста ресурсов.
    // EN: Fresh PHP process isolates context-processor side effects.
    $pub=runController(array('generate',$private));
    must(strlen($pub)===64,'separate signing key generated');
    securitySetting('modxmcp.permit_public_key',$pub);
    securitySetting('modxmcp.permit_audience','ci-protected');
    securitySetting('modxmcp.permit_site_id',$site);
    securitySetting('modxmcp.permit_contour','test');
    securitySetting('modxmcp.external_authorization_required','1');
    $enabled=true;
    must(\ModxMcp\Security\ProtectedAuthorization::required($modx),
        'protected policy enabled on separate context process');
    // RU: Подписанный context setting: отклонённый ключ не меняет CAS,
    // RU: а два независимых permit с одним снимком не могут оба победить.
    // EN: Signed context setting denies before CAS; two distinct permits
    // EN: with the same observed revision cannot both commit.
    // RU: disposable fixture включает контексты, отключённые по умолчанию.
    // EN: Enable the context capability only in this disposable test fixture.
    securitySetting('modxmcp.disabled_groups','');
    $modx->setOption('modxmcp.disabled_groups','');
    securitySetting('modxmcp.permit_allowed_actions',
        'create_element,update_element,delete_element,update_system_setting,'
        .'run_processor,create_context_setting,update_context_setting,delete_context_setting');
    $protectedContextKey='modxmcp.external_authorization_required';
    $deniedContext=array('context_key'=>'web','key'=>$protectedContextKey,
        'value'=>'0','_runtime_preconditions'=>guardNow());
    $contextDeniedBefore=(int)guardNow()['observed_site_revision'];
    mustDeny('create_context_setting','',$deniedContext,
        signedCall('create_context_setting','',$deniedContext),
        'Managing modxmcp.* settings');
    must((int)guardNow()['observed_site_revision']===$contextDeniedBefore,
        'signed protected context key rejected without CAS increment');
    $contextKey='ci_signed_context_'.bin2hex(random_bytes(6));
    $contextValue='SIGNED_INITIAL';
    $contextCreate=array('context_key'=>'web','key'=>$contextKey,
        'value'=>$contextValue,'_runtime_preconditions'=>guardNow());
    $createdContext=rpc('create_context_setting','',$contextCreate,
        signedCall('create_context_setting','',$contextCreate));
    must(is_array($createdContext),'signed context fixture created');
    $contextRead=$api->processRequest('get_context_setting','',array(
        'context_key'=>'web','key'=>$contextKey));
    must(($contextRead['value']??null)===$contextValue,
        'signed context creation readback via MCP');
    $sameSnapshot=guardNow();
    $firstClient=array('context_key'=>'web','key'=>$contextKey,
        'value'=>'CLIENT_A_WINS','_runtime_preconditions'=>$sameSnapshot);
    $secondClient=array('context_key'=>'web','key'=>$contextKey,
        'value'=>'CLIENT_B_MUST_NOT_OVERWRITE','_runtime_preconditions'=>$sameSnapshot);
    $firstSigned=signedCall('update_context_setting','',$firstClient);
    $secondSigned=signedCall('update_context_setting','',$secondClient);
    must($firstSigned['permit']['claims']['nonce']
        !==$secondSigned['permit']['claims']['nonce'],
        'competing clients have distinct signed nonces');
    $clientA=rpc('update_context_setting','',$firstClient,$firstSigned);
    must(is_array($clientA),'first independently signed context update committed');
    $afterClientA=(int)guardNow()['observed_site_revision'];
    must($afterClientA===(int)$sameSnapshot['observed_site_revision']+1,
        'winning signed context update increments CAS once');
    $clientBDenied=false;
    try{rpc('update_context_setting','',$secondClient,$secondSigned);}
    catch(\ModxMCPClientException $e){
        $clientBDenied=strpos($e->getMessage(),'STALE_STATE')!==false;
    }
    must($clientBDenied,'second signed client denied stale context revision');
    must((int)guardNow()['observed_site_revision']===$afterClientA,
        'stale signed client never increments revision');
    $contextAfter=$api->processRequest('get_context_setting','',array(
        'context_key'=>'web','key'=>$contextKey));
    must(($contextAfter['value']??null)==='CLIENT_A_WINS',
        'signed loser did not overwrite first client');
    mustDeny('update_context_setting','',$firstClient,$firstSigned);
    must((int)guardNow()['observed_site_revision']===$afterClientA,
        'replayed signed context mutation cannot consume CAS');
    $contextDelete=array('context_key'=>'web','key'=>$contextKey,
        '_runtime_preconditions'=>guardNow());
    rpc('delete_context_setting','',$contextDelete,
        signedCall('delete_context_setting','',$contextDelete));
    $contextMissing=false;
    try{$contextAfterDelete=$api->processRequest('get_context_setting','',array(
        'context_key'=>'web','key'=>$contextKey));
        $contextMissing=!is_array($contextAfterDelete)
            || !array_key_exists('value',$contextAfterDelete);
    }catch(\ModxMCPClientException $e){$contextMissing=true;}
    must($contextMissing,'signed context fixture removed via MCP');
    echo 'PROTECTED_SIGNED_CONTEXT_CAS_CONFLICT_AND_REPLAY_OK'.PHP_EOL;

    echo 'PROTECTED_SIGNED_CONTEXT_DISPATCHER_E2E_OK platform='
        .($is2?'modx2':'modx3').' checks='.$checks.PHP_EOL;
} finally {
    if($enabled)securitySetting('modxmcp.external_authorization_required','0');
    if(is_file($private))@unlink($private);
}
