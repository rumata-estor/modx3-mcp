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
$keyReady=false;$enabled=false;
try {
    $pub=runController(array('generate',$private));
    must(strlen($pub)===64,'Ed25519 public key generated');
    securitySetting('modxmcp.permit_public_key',$pub);
    securitySetting('modxmcp.permit_audience','ci-protected');
    securitySetting('modxmcp.permit_site_id',$site);
    securitySetting('modxmcp.permit_contour','test');
    securitySetting('modxmcp.permit_allowed_actions',
        'create_element,update_element,delete_element,update_system_setting');
    // Security mode is enabled by the disposable Manager test fixture.
    // No MCP instruction is allowed to change modxmcp.* security settings.
    securitySetting('modxmcp.external_authorization_required','1');
    $enabled=true;
    must(\ModxMcp\Security\ProtectedAuthorization::required($modx),'DB policy really enabled');

    // RU: Проверяем критическое создание пользователя с устаревшей CAS-ревизией.
    // RU: Реальный процессор не должен запуститься; тест только для disposable CI.
    // EN: Stale signed user creation must be rejected BEFORE the MODX processor.
    // EN: This adversarial test runs only on the disposable CI installation.
    $modx->setOption('modxmcp.disabled_groups','');
    securitySetting('modxmcp.permit_allowed_actions',
        'create_element,update_element,delete_element,update_system_setting,create_user');
    $blockedUsername='CAS_DENIED_USER_'.bin2hex(random_bytes(8));
    $beforeUser=(int)$api->processRequest('get_site_state','',array())['site_revision'];
    $blockedUser=array('username'=>$blockedUsername,
        '_runtime_preconditions'=>array(
            'observed_site_revision'=>$beforeUser+10000,
            'strict_site_revision'=>true,'read_set'=>array()
        ));
    $blocked=false;
    try {
        rpc('create_user','',$blockedUser,signedCall('create_user','',$blockedUser));
    } catch (\ModxMCPClientException $e) {
        $blocked=strpos($e->getMessage(),'STALE_STATE')!==false;
    }
    must($blocked,'critical signed create_user rejected stale CAS');
    must((int)$api->processRequest('get_site_state','',array())['site_revision']
        ===$beforeUser,'denied create_user did not change connector revision');
    // RU: Подписанное разрешение НЕ заменяет точный снимок ACL-объекта.
    // EN: A valid Ed25519 permit cannot waive the ACL target fingerprint.
    securitySetting('modxmcp.permit_allowed_actions',
        'create_element,update_element,delete_element,update_system_setting,'
        .'create_user,update_user,update_user_group,update_role');
    foreach (array('update_user','update_user_group','update_role') as $aclAction) {
        $aclBefore=(int)$api->processRequest('get_site_state','',array())['site_revision'];
        $aclWithoutTarget=array('id'=>987654321,
            '_runtime_preconditions'=>guardNow());
        mustDeny($aclAction,'',$aclWithoutTarget,
            signedCall($aclAction,'',$aclWithoutTarget));
        must((int)$api->processRequest('get_site_state','',array())['site_revision']
            ===$aclBefore,'signed ACL without target fingerprint did not write');
    }
    securitySetting('modxmcp.permit_allowed_actions',
        'create_element,update_element,delete_element,update_system_setting,create_user');

    // RU: Повторно читаем список пользователей только через MODX MCP.
    // EN: Verify absence through the MODX MCP read tool, never direct SQL.
    $users=$api->processRequest('list_users','',array('query'=>$blockedUsername));
    must(is_array($users) && isset($users['results'])
        && is_array($users['results']),'denied-user readback available');
    foreach ($users['results'] as $userRow) {
        must(!is_array($userRow)
            || !isset($userRow['username'])
            || $userRow['username']!==$blockedUsername,
            'denied critical user was not created');
    }
    securitySetting('modxmcp.permit_allowed_actions',
        'create_element,update_element,delete_element,update_system_setting');

    $name='Ed25519 protected CI '.bin2hex(random_bytes(5));
    $data=array(
        'type'=>'resource','name'=>$name,'alias'=>'ed25519-ci-'.bin2hex(random_bytes(5)),
        'content'=>'BEFORE','template'=>0,'parent'=>0,
        'published'=>0,'hidemenu'=>1,'_runtime_preconditions'=>guardNow(),
    );
    mustDeny('create_element','',$data,null);
    // Even a correctly signed permit is insufficient if the site has not
    // independently approved the specific mutation operation.
    $unapproved=array('_runtime_preconditions'=>guardNow());
    $beforePolicy=(int)$api->processRequest('get_site_state','',array())['site_revision'];
    mustDeny('clear_cache','',$unapproved,
        signedCall('clear_cache','',$unapproved));
    must((int)$api->processRequest('get_site_state','',array())['site_revision']
        ===$beforePolicy,'disallowed action did not consume site revision');
    // Even an explicitly allowlisted signed generic processor must be
    // blocked: it could mutate trust anchors or grant unauthorized sudo.
    securitySetting('modxmcp.permit_allowed_actions',
        'create_element,update_element,delete_element,update_system_setting,run_processor');
    $generic=array('processor'=>'security/user/create',
        'properties'=>array('username'=>'FORBIDDEN_GENERIC_'.bin2hex(random_bytes(4))),
        '_runtime_preconditions'=>guardNow());
    $beforeGeneric=(int)$api->processRequest('get_site_state','',array())['site_revision'];
    mustDeny('run_processor','',$generic,
        signedCall('run_processor','',$generic));
    must((int)$api->processRequest('get_site_state','',array())['site_revision']
        ===$beforeGeneric,'forbidden generic processor consumed no revision');
    // The Manager switch alone must not bypass the exact processor policy.
    // Modify only disposable CI configuration, never a real MODX site.
    securitySetting('modxmcp.allow_run_processor','1');
    securitySetting('modxmcp.processor_access_mode','allowlist');
    securitySetting('modxmcp.allowed_processors','system/clearcache');
    mustDeny('run_processor','',$generic,
        signedCall('run_processor','',$generic));
    // RU: Положительный сценарий: разрешённый процессор работает с Ed25519,
    // RU: даже когда кеш MODX ещё хранит старое значение галочки.
    // EN: A signed, allowlisted processor must work despite stale option cache.
    $approvedRead=array('processor'=>'resource/getlist',
        'properties'=>array('limit'=>1),'_runtime_preconditions'=>guardNow());
    securitySetting('modxmcp.allowed_processors','resource/getlist');
    $revisionBeforeApproved=(int)$api->processRequest('get_site_state','',array())['site_revision'];
    $approvedPermit=signedCall('run_processor','',$approvedRead);
    $approvedResult=rpc('run_processor','',$approvedRead,$approvedPermit);
    must(is_array($approvedResult),'signed allowlisted processor executes');
    must((int)$api->processRequest('get_site_state','',array())['site_revision']
        ===$revisionBeforeApproved+1,'approved processor increments CAS revision');
    mustDeny('run_processor','',$approvedRead,$approvedPermit);
    // RU: Подписанный context setting: отклонённый ключ не меняет CAS,
    // RU: а два независимых permit с одним снимком не могут оба победить.
    // EN: Signed context setting denies before CAS; two distinct permits
    // EN: with the same observed revision cannot both commit.
    securitySetting('modxmcp.permit_allowed_actions',
        'create_element,update_element,delete_element,update_system_setting,'
        .'run_processor,create_context_setting,update_context_setting,delete_context_setting');
    $protectedContextKey='modxmcp.external_authorization_required';
    $deniedContext=array('context_key'=>'web','key'=>$protectedContextKey,
        'value'=>'0','_runtime_preconditions'=>guardNow());
    $contextDeniedBefore=(int)guardNow()['observed_site_revision'];
    mustDeny('create_context_setting','',$deniedContext,
        signedCall('create_context_setting','',$deniedContext));
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

    securitySetting('modxmcp.processor_access_mode','disabled');
    mustDeny('run_processor','',$generic,
        signedCall('run_processor','',$generic));
    securitySetting('modxmcp.allow_run_processor','0');
    securitySetting('modxmcp.permit_allowed_actions','');
    mustDeny('create_element','',$data,
        signedCall('create_element','',$data));
    securitySetting('modxmcp.permit_allowed_actions',
        'create_element,update_element,delete_element,update_system_setting');
    securitySetting('modxmcp.external_authorization_required','invalid');
    mustDeny('create_element','',$data,
        signedCall('create_element','',$data));
    securitySetting('modxmcp.external_authorization_required','1');
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

    // RU: Успешный signed run_processor увеличил ревизию сайта.
    // RU: Для следующей операции требуется новый снимок CAS.
    // EN: A successful signed processor call advanced the site revision.
    // EN: Refresh CAS observations before signing the next mutation.
    $data['_runtime_preconditions']=guardNow();
    $valid=signedCall('create_element','',$data);
    $result=rpc('create_element','',$data,$valid);
    $id=(int)$result['id'];
    must($id>0,'signed protected resource creation');
    mustDeny('create_element','',$data,$valid);

    $read=$api->processRequest('get_element','',array('type'=>'resource','id'=>$id));
    must((int)$read['published']===0 && (int)$read['hidemenu']===1,'MCP-only readback');

    // Controlled adversarial operation: a MODX Manager processor edits the
    // disposable resource OUTSIDE MCP. This intentionally simulates the
    // very concurrent human edit that site_revision cannot see; all business
    // changes under test still travel through the protected MCP dispatcher.
    $observedBeforeManager=guardTarget('resource',$id);
    $managerRevision=(int)$api->processRequest('get_site_state','',array())['site_revision'];
    // A genuinely separate PHP process holds its own xPDO cache, exactly
    // like a separate MODX Manager request.
    $worker=array(PHP_BINARY,__DIR__.'/test.ci-manager-external-edit.php',(string)$id);
    $pipes=array();
    $process=proc_open($worker,array(
        0=>array('pipe','r'),1=>array('pipe','w'),2=>array('pipe','w')
    ),$pipes);
    if(!is_resource($process))throw new RuntimeException('Cannot start external Manager test');
    fclose($pipes[0]);
    $out=stream_get_contents($pipes[1]);
    $err=stream_get_contents($pipes[2]);
    fclose($pipes[1]);fclose($pipes[2]);
    if(proc_close($process)!==0 ||
        strpos($out,'DISPOSABLE_MANAGER_OUT_OF_BAND_EDIT_OK')===false) {
        throw new RuntimeException('External Manager fixture failed: '.$err);
    }
    $managerRead=$api->processRequest('get_element','',array(
        'type'=>'resource','id'=>$id
    ));
    must((string)$managerRead['content']==='MANAGER_CHANGED',
        'disposable MODX Manager processor changed the target');
    must(
        (int)$api->processRequest('get_site_state','',array())['site_revision']
            ===$managerRevision,
        'MODX Manager change did not advance connector site revision'
    );
    $conflicting=array('type'=>'resource','id'=>$id,
        'content'=>'WOULD_OVERWRITE_MANAGER',
        '_runtime_preconditions'=>$observedBeforeManager);
    try {
        rpc('update_element','',$conflicting,
            signedCall('update_element','',$conflicting));
        throw new RuntimeException('Manager edit was silently overwritten');
    } catch(\ModxMCPClientException $e) {
        must(strpos($e->getMessage(),'STALE_STATE')!==false,
            'resource fingerprint rejects unseen Manager edit');
    }
    $afterConflict=$api->processRequest('get_element','',array(
        'type'=>'resource','id'=>$id
    ));
    must((string)$afterConflict['content']==='MANAGER_CHANGED',
        'stale signed mutation did not overwrite Manager content');
    echo 'PROTECTED_CAS_MANAGER_EDIT_CONFLICT_DENIED_OK'.PHP_EOL;

    // Real overlapping update race. The independent Manager worker starts an
    // InnoDB transaction and saves (without commit), keeping the target row
    // locked. The signed MCP write must wait for that commit, read the NEW
    // target fingerprint, reject STALE_STATE, and never overwrite the human.
    $racingBefore=guardTarget('resource',$id);
    $revisionBeforeRace=(int)$racingBefore['observed_site_revision'];
    $racingData=array(
        'type'=>'resource','id'=>$id,'content'=>'MCP_MUST_NOT_OVERWRITE',
        '_runtime_preconditions'=>$racingBefore
    );
    $racingEnvelope=signedCall('update_element','',$racingData);
    $readyFile=getenv('RUNNER_TEMP').'/cas-manager-race-'.bin2hex(random_bytes(6));
    $worker=array(PHP_BINARY,__DIR__.'/test.ci-manager-racing-edit.php',
        (string)$id,$readyFile);
    $pipes=array();
    $workerProcess=proc_open($worker,array(
        0=>array('pipe','r'),1=>array('pipe','w'),2=>array('pipe','w')
    ),$pipes);
    if(!is_resource($workerProcess))
        throw new RuntimeException('Could not start overlapping Manager writer');
    fclose($pipes[0]);
    $deadline=microtime(true)+15;
    while(!is_file($readyFile) && microtime(true)<$deadline)usleep(10000);
    must(is_file($readyFile),'Manager writer holds uncommitted resource row');
    $started=microtime(true);
    $raceDenied=false;
    try {
        rpc('update_element','',$racingData,$racingEnvelope);
    } catch(\ModxMCPClientException $e) {
        $raceDenied=strpos($e->getMessage(),'STALE_STATE')!==false;
    }
    $blockedFor=microtime(true)-$started;
    $stdout=stream_get_contents($pipes[1]);
    $stderr=stream_get_contents($pipes[2]);
    fclose($pipes[1]);fclose($pipes[2]);
    $workerExit=proc_close($workerProcess);
    must($workerExit===0
        && strpos($stdout,'DISPOSABLE_MANAGER_ROW_RACE_COMMITTED')!==false,
        'independent racing Manager writer committed: '.$stderr);
    must($blockedFor>=0.5,
        'protected MCP write waited for real Manager row transaction');
    must($raceDenied,'concurrent Manager edit caused stale signed MCP refusal');
    $racingRead=$api->processRequest('get_element','',array(
        'type'=>'resource','id'=>$id
    ));
    must((string)$racingRead['content']==='MANAGER_RACING',
        'concurrent Manager content retained');
    must((int)$api->processRequest('get_site_state','',array())['site_revision']
        ===$revisionBeforeRace,'denied racing MCP write did not increment revision');
    echo 'PROTECTED_CAS_INNODB_ROW_RACE_DENIED_OK'.PHP_EOL;

    $update=array('type'=>'resource','id'=>$id,'content'=>'AFTER',
        '_runtime_preconditions'=>guardTarget('resource',$id));
    $updated=rpc('update_element','',$update,signedCall('update_element','',$update));
    $read=$api->processRequest('get_element','',array('type'=>'resource','id'=>$id));
    must(is_array($read) && $read['content']==='AFTER','protected update via MCP');

    $stale=array('type'=>'resource','id'=>$id,'content'=>'BAD',
        '_runtime_preconditions'=>array(
            'observed_site_revision'=>0,'strict_site_revision'=>true,
            'read_set'=>guardTarget('resource',$id)['read_set']
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
        '_runtime_preconditions'=>guardTarget('resource',$id));
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
