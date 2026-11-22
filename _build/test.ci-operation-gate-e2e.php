<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Реальный MODX MCP + подписанный permit + журнал повторов без двойной записи.
 * EN: Real MODX MCP with signed permits and no duplicate replay dispatch.
 */
/**
 * Disposable-only end-to-end test. Writes site security settings through
 * MODX Manager APIs; creates a throwaway unpublished page only through MCP.
 * The operation table is test-only and removed before completion.
 */
if(PHP_SAPI!=='cli' || getenv('MODXMCP_DISPOSABLE_CI')!=='1'
    || strpos((string)getenv('MODX_CONFIG_CORE'),'/home/runner/')!==0
    || !is_file((string)getenv('MODX_CONFIG_CORE')))exit(3);
require getenv('MODX_CONFIG_CORE');
$is2=!is_file(MODX_CORE_PATH.'src/Revolution/modX.php');
if($is2){require_once MODX_CORE_PATH.'model/modx/modx.class.php';$modx=new modX();}
else{require_once MODX_CORE_PATH.'vendor/autoload.php';
    $modx=\MODX\Revolution\modX::getInstance();}
$modx->initialize('mgr');
require_once MODX_CORE_PATH.'components/modxmcp/model/modxmcp.class.php';
$api=new modxMCP($modx);
$site='ci-idempotency-'.bin2hex(random_bytes(5));
$private=getenv('RUNNER_TEMP').'/ci-idempotency-key-'.bin2hex(random_bytes(5));
$node=__DIR__.'/test.ci-ed25519-controller.cjs';
$created=false;$enabled=false;$resourceId=0;$uncertainResourceId=0;$checks=0;
function opE2eCheck($ok,$msg){
    global $checks;
    if(!$ok)throw new RuntimeException('OPERATION_GATE_E2E_FAIL '.$msg);
    $checks++;
}
function opE2eSetting($key,$value){
    global $modx,$is2;
    $class=$is2?'modSystemSetting':'MODX\\Revolution\\modSystemSetting';
    $row=$modx->getObject($class,array('key'=>$key));
    if(!$row){$row=$modx->newObject($class);
        $row->set('key',$key);$row->set('xtype','textfield');
        $row->set('namespace','modxmcp');$row->set('area','modxmcp:security');}
    $row->set('value',(string)$value);
    opE2eCheck($row->save(),'security setting saved');
}
function opE2eNode($args,$stdin=''){
    $cmd=array_merge(array('node',__DIR__.'/test.ci-ed25519-controller.cjs'),$args);
    $process=proc_open($cmd,array(
        0=>array('pipe','r'),1=>array('pipe','w'),2=>array('pipe','w')
    ),$pipes);
    if(!is_resource($process))throw new RuntimeException('Cannot start disposable signer');
    fwrite($pipes[0],$stdin);fclose($pipes[0]);
    $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);
    fclose($pipes[1]);fclose($pipes[2]);
    opE2eCheck(proc_close($process)===0,'CI signer '.$err);
    return trim($out);
}
function opE2eRequest($action,$data,$type=''){
    global $api,$private,$site;
    $raw=json_encode(array('action'=>$action,'type'=>$type,'data'=>$data),
        JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    $scope=$type!==''?$type:(isset($data['type'])?$data['type']:'none');
    $permit=json_decode(opE2eNode(array('sign',$private,$site,$action,$scope),$raw),true);
    if(!is_array($permit))throw new RuntimeException('Signed permit invalid');
    return $api->processRequest($action,$type,$data,array(
        'raw_body'=>$raw,'permit'=>$permit
    ));
}
function opE2eGuard(){
    global $api;
    $state=$api->processRequest('get_site_state','',array());
    return array('observed_site_revision'=>(int)$state['site_revision'],
        'strict_site_revision'=>true,'read_set'=>array());
}
function opE2eId(){
    $h=bin2hex(random_bytes(16));
    return substr($h,0,8).'-'.substr($h,8,4).'-4'.substr($h,13,3)
        .'-8'.substr($h,17,3).'-'.substr($h,20,12);
}
$table=\ModxMcp\Security\ProtectedAuthorization::ledgerTable($modx);
$table=substr($table,0,-strlen('modxmcp_permit_nonces')).'modxmcp_operation_intents';
opE2eCheck((bool)preg_match('/\A[a-zA-Z][a-zA-Z0-9_]{0,63}\z/D',$table),
    'valid prefixed operation table');
try{
    opE2eCheck($modx->exec('CREATE TABLE `'.$table.'` ('
        .' operation_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,'
        .' request_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,'
        .' state VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,'
        .' created_at BIGINT UNSIGNED NOT NULL,'
        .' updated_at BIGINT UNSIGNED NOT NULL,'
        .' PRIMARY KEY(operation_hash)) ENGINE=InnoDB')!==false,
        'create isolated operation table');
    $created=true;
    $pub=opE2eNode(array('generate',$private));
    opE2eCheck(strlen($pub)===64,'signing public key');
    opE2eSetting('modxmcp.permit_public_key',$pub);
    opE2eSetting('modxmcp.permit_audience','ci-protected');
    opE2eSetting('modxmcp.permit_site_id',$site);
    opE2eSetting('modxmcp.permit_contour','test');
    opE2eSetting('modxmcp.permit_allowed_actions','create_element,delete_element');
    opE2eSetting('modxmcp.operation_journal_enabled','1');
    opE2eSetting('modxmcp.external_authorization_required','1');
    $enabled=true;
    $marker=bin2hex(random_bytes(5));
    $data=array(
        'type'=>'resource','name'=>'CI_Operation_'.$marker,
        'alias'=>'ci-operation-'.$marker,'content'=>'TEST',
        'published'=>0,'hidemenu'=>1,'template'=>0,'parent'=>0,
        '_operation_id'=>opE2eId(),
        '_runtime_preconditions'=>opE2eGuard()
    );
    $before=(int)$api->processRequest('get_site_state','',array())['site_revision'];
    $first=opE2eRequest('create_element',$data);
    $resourceId=(int)($first['id']??0);
    opE2eCheck($resourceId>0 && ($first['_operation_status']??'')==='SUCCEEDED',
        'signed operation created exactly one unpublished resource');
    // Simulate network response loss: discard the reply, sign a NEW permit.
    $replay=false;
    try{opE2eRequest('create_element',$data);}
    catch(ModxMCPClientException $e){
        $replay=strpos($e->getMessage(),'OPERATION_REPLAY_BLOCKED: prior state=SUCCEEDED')===0;
    }
    opE2eCheck($replay,'signed retry returned status without second dispatch');
    $after=(int)$api->processRequest('get_site_state','',array())['site_revision'];
    opE2eCheck($after===$before+1,'replay did not consume another site revision');
    $found=$api->processRequest('get_element','',array('type'=>'resource','id'=>$resourceId));
    opE2eCheck((int)($found['id']??0)===$resourceId
        && ($found['content']??'')==='TEST','independent MCP readback');
    $changed=$data;$changed['name']='CI_ReusedID_'.$marker;
    $denied=false;
    try{opE2eRequest('create_element',$changed);}
    catch(ModxMCPClientException $e){
        $denied=strpos($e->getMessage(),'OPERATION_JOURNAL_UNAVAILABLE')===0;
    }
    opE2eCheck($denied,'same ID with new payload denied');
    opE2eCheck((int)$api->processRequest('get_site_state','',array())['site_revision']===$after,
        'mismatch left revision unchanged');
    // A previously accepted PENDING intent means the first worker may
    // have crashed. It may NEVER authorize the same business action twice.
    $pending=$data;$pending['name']='CI_Pending_'.$marker;
    $pending['alias']='ci-pending-'.$marker;
    $pending['_operation_id']=opE2eId();
    $pending['_runtime_preconditions']=opE2eGuard();
    $digest=\ModxMcp\Security\MutationOperationLedger::requestDigest(
        $site.'|test','create_element','',$pending);
    $pdo=new PDO('mysql:host=127.0.0.1;dbname=modx_build;charset=utf8mb4',
        'modx_ci','ci-throwaway-only-user',
        array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION));
    $ledger=new \ModxMcp\Security\MutationOperationLedger($pdo,$table);
    opE2eCheck($ledger->claim($pending['_operation_id'],$digest)['fresh'],
        'intent reserved before synthetic process crash');
    $pendingDenied=false;
    try{opE2eRequest('create_element',$pending);}
    catch(ModxMCPClientException $e){
        $pendingDenied=strpos($e->getMessage(),'OPERATION_REPLAY_BLOCKED: prior state=PENDING')===0;
    }
    opE2eCheck($pendingDenied,'pending crash cannot create duplicate');
    opE2eCheck((int)$api->processRequest('get_site_state','',array())['site_revision']===$after,
        'pending crash did not reserve revision again');
    // RU: Реальная запись ресурса завершена, но журнал не может подтвердить
    // RU: SUCCEEDED: никакого ложного успеха и повторной мутации.
    // EN: The real mutation commits, yet journal SUCCEEDED fails: never
    // EN: report success or allow duplicate dispatch on a new signed permit.
    $failureMarker=bin2hex(random_bytes(6));
    $failedData=array('type'=>'resource','name'=>'CI_UNCERTAIN_'.$failureMarker,
        'alias'=>'ci-uncertain-'.$failureMarker,'content'=>'CONFIRMED_BY_READBACK',
        'published'=>0,'hidemenu'=>1,'template'=>0,'parent'=>0,
        '_operation_id'=>opE2eId(), '_runtime_preconditions'=>opE2eGuard());
    $beforeFailure=(int)$failedData['_runtime_preconditions']['observed_site_revision'];
    $trigger='ci_operation_finish_'.bin2hex(random_bytes(5));
    $triggerArmed=false;
    try {
        $quote=chr(39);
        $sql='CREATE TRIGGER `'.$trigger.'` BEFORE UPDATE ON `'.$table.'`'
            .' FOR EACH ROW BEGIN IF NEW.state='.$quote.'SUCCEEDED'.$quote
            .' THEN SIGNAL SQLSTATE '.$quote.'45000'.$quote
            .' SET MESSAGE_TEXT='.$quote.'CI_OPERATION_FINISH_UNAVAILABLE'.$quote
            .'; END IF; END';
        opE2eCheck($modx->exec($sql)!==false,'disposable finish rejection installed');
        $triggerArmed=true;
        $outcomeUncertain=false;
        try {opE2eRequest('create_element',$failedData);}
        catch(ModxMCPClientException $e){
            $outcomeUncertain=strpos($e->getMessage(),
                'MUTATION_OUTCOME_UNCERTAIN:')===0;
        }
        opE2eCheck($outcomeUncertain,'journal failure returns uncertain, never success');
        $listed=$api->processRequest('list_resources','',array(
            'query'=>$failedData['alias'],'limit'=>50));
        $matching=array_values(array_filter($listed['results'],
            function($row)use($failedData){
                return ($row['alias']??'')===$failedData['alias'];
            }));
        opE2eCheck(count($matching)===1,'readback shows one committed mutation');
        $uncertainResourceId=(int)$matching[0]['id'];
        $actual=$api->processRequest('get_element','',array(
            'type'=>'resource','id'=>$uncertainResourceId));
        opE2eCheck(($actual['content']??'')==='CONFIRMED_BY_READBACK'
            && (int)$actual['published']===0,
            'MCP confirmed committed unpublished resource');
        $failedDigest=\ModxMcp\Security\MutationOperationLedger::requestDigest(
            $site.'|test','create_element','',$failedData);
        opE2eCheck($ledger->get($failedData['_operation_id'],$failedDigest)['state']
            ==='PENDING','failed finish retained durable PENDING');
        $again=false;
        try{opE2eRequest('create_element',$failedData);}
        catch(ModxMCPClientException $e){
            $again=strpos($e->getMessage(),
                'OPERATION_REPLAY_BLOCKED: prior state=PENDING')===0;
        }
        opE2eCheck($again,'new signed permit cannot repeat ambiguous operation');
        opE2eCheck((int)$api->processRequest('get_site_state','',array())['site_revision']
            ===$beforeFailure+1,'ambiguous replay did not reserve another revision');
        $afterReplay=$api->processRequest('list_resources','',array(
            'query'=>$failedData['alias'],'limit'=>50));
        opE2eCheck((int)$afterReplay['total']===1,
            'MCP shows no duplicate after failed journal confirmation');
    } finally {
        if($triggerArmed)$modx->exec('DROP TRIGGER IF EXISTS `'.$trigger.'`');
    }
    echo 'SIGNED_OPERATION_FINISH_FAILURE_MODX'.($is2?'2':'3')
        .'_OK state=PENDING duplicate=0'.PHP_EOL;
    echo 'SIGNED_OPERATION_REPLAY_MODX'.($is2?'2':'3').'_OK checks='.$checks.PHP_EOL;
} finally {
    if($uncertainResourceId>0 && $enabled){
        try {
            $del=array('type'=>'resource','id'=>$uncertainResourceId,
                'dry_run'=>false,'_runtime_preconditions'=>opE2eGuard());
            opE2eRequest('delete_element',$del);
        }catch(Throwable $ignored){}
    }
    if($resourceId>0 && $enabled){
        try{
            $del=array('type'=>'resource','id'=>$resourceId,
                'dry_run'=>false,'_runtime_preconditions'=>opE2eGuard());
            opE2eRequest('delete_element',$del);
        }catch(Throwable $ignored){}
    }
    if($enabled){
        opE2eSetting('modxmcp.operation_journal_enabled','0');
        opE2eSetting('modxmcp.external_authorization_required','0');
    }
    if($created)$modx->exec('DROP TABLE IF EXISTS `'.$table.'`');
    if(is_file($private))unlink($private);
}
