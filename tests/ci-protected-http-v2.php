<?php
/**
 * Test-only real HTTP proof through the installed MODX MCP v2 CAS route.
 * Creates only a disposable chunk and system-setting ROWS in official
 * throwaway CI MODX. NO new tables or production credentials.
 */
$root=getenv('RUNNER_TEMP');
$config=getenv('MODX_CONFIG_CORE');
$platform=getenv('BUILD_PLATFORM');
$mode=isset($argv[1]) ? $argv[1] : '';
if (PHP_SAPI!=='cli' || getenv('GITHUB_ACTIONS')!=='true' ||
    !in_array($platform,array('modx2','modx3'),true) ||
    !in_array($mode,array('prepare','exercise'),true) ||
    !is_string($root) || !$root || !is_string($config) ||
    realpath($config)!==realpath($root.'/modx-site/config.core.php') ||
    getenv('MODX_DOCUMENT_ROOT')!==$root.'/modx-site') {
    throw new RuntimeException('refusing non-disposable HTTP CAS test');
}
require $config;
if ($platform==='modx3') {
    require MODX_CORE_PATH.'vendor/autoload.php';
    $modx=\MODX\Revolution\modX::getInstance();
    $settingClass='MODX\\Revolution\\modSystemSetting';
    $chunkClass='MODX\\Revolution\\modChunk';
    $site='modx3-alex-palochkin';
} else {
    require MODX_CORE_PATH.'model/modx/modx.class.php';
    $modx=new modX();
    $settingClass='modSystemSetting';
    $chunkClass='modChunk';
    $site='modx2-alex-palochkin';
}
$modx->initialize('mgr');
require_once __DIR__.'/../core/components/modxmcp/endpoint/permit-verifier-v2.php';
$fixture=$root.'/modxmcp-http-v2-ci.json';
$changeSetting=function($key,$value) use($modx,$settingClass) {
    $row=$modx->getObject($settingClass,array('key'=>$key));
    if (!$row) {
        $row=$modx->newObject($settingClass);
        $row->fromArray(array('key'=>$key,'value'=>(string)$value,
            'xtype'=>'textfield','namespace'=>'modxmcp','area'=>'modxmcp:runtime'),
            '',true,true);
    } else {
        $row->set('value',(string)$value);
    }
    if (!$row->save()) { throw new RuntimeException('unable to save disposable setting'); }
};
$settings=array(
    'modxmcp.external_authorization_required'=>'1',
    'modxmcp.auto_static'=>'0',
    'modxmcp.site_revision'=>'0',
    'modxmcp.permit_audience'=>'ci-v2-http',
    'modxmcp.permit_site_id'=>$site,
    'modxmcp.permit_contour'=>'test',
);
if ($mode==='prepare') {
    if (file_exists($fixture)) {
        throw new RuntimeException('CI protected CAS HTTP fixture already exists');
    }
    $dir=getenv('HOME').'/.modxmcp-http-ci-'.bin2hex(random_bytes(8));
    if (!mkdir($dir,0700) || !mkdir($dir.'/used',0700)) {
        throw new RuntimeException('cannot initialize private CI permit files');
    }
    $key=random_bytes(32);
    file_put_contents($dir.'/signing-key',bin2hex($key));
    chmod($dir.'/signing-key',0600);
    $settings['modxmcp.permit_key_file']=$dir.'/signing-key';
    $settings['modxmcp.permit_nonce_directory']=$dir.'/used';
    $original=array();
    foreach ($settings as $name=>$value) {
        $entry=$modx->getObject($settingClass,array('key'=>$name));
        $original[$name]=$entry ?
            array('present'=>true,'value'=>(string)$entry->get('value')) :
            array('present'=>false);
        $changeSetting($name,$value);
    }
    $chunk=$modx->newObject($chunkClass);
    $chunk->set('name','CAS_V2_HTTP_CI_'.bin2hex(random_bytes(6)));
    $chunk->set('snippet','Original HTTP CI body');
    $chunk->set('static',0);
    if (!$chunk->save()) { throw new RuntimeException('cannot create HTTP CI chunk'); }
    $id=(int)$chunk->get('id');
    if ($id<=0) { throw new RuntimeException('invalid disposable chunk ID'); }
    // Seed the partitions the normal MODX cache refresh must invalidate.
    // No unusual filesystem operations or schema changes are required.
    $cache=$modx->getCacheManager();
    $probe='CAS_V2_HTTP_CACHE_'.bin2hex(random_bytes(4));
    $partitionKeys=array();
    $partitions=array('media_sources','scripts','default','resource');
    if ($platform==='modx2') { $partitions[]='action_map'; }
    foreach ($partitions as $part) {
        $partKey=(string)$modx->getOption('cache_'.$part.'_key',null,$part);
        $partitionKeys[$part]=$partKey;
        $marker='stale';
        if (!$cache->set($probe,$marker,0,array('cache_key'=>$partKey))) {
            throw new RuntimeException('cannot seed disposable cache partition');
        }
    }
    $results=array();
    if (!$cache->refresh(array('system_settings'=>array()),$results)) {
        throw new RuntimeException('cannot refresh disposable settings cache');
    }

    $before='Original HTTP CI body';
    $after='CAS HTTP CI verified body';
    $raw=json_encode(array('action'=>'update_element','type'=>'chunk',
        'data'=>array('id'=>$id,'content'=>$after)),
        JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|
        JSON_UNESCAPED_LINE_TERMINATORS);
    $baseline=array('element_type'=>'chunk','id'=>$id,
        'storage_field'=>'snippet','static'=>false,
        'before_content'=>$before,'before_sha256'=>hash('sha256',$before),
        'after_sha256'=>hash('sha256',$after));
    $task='TASK-20261104-140000-abcd';
    $claims=array('version'=>'2','audience'=>'ci-v2-http','site_id'=>$site,
        'contour'=>'test','task_id'=>$task,'plan_digest'=>str_repeat('a',64),
        'snapshot_digest'=>ModxMcpCasPermitVerifierV2::observationDigest(
            $site,$task,$baseline),
        'operation_id'=>'op-1','action'=>'update_element',
        'element_type'=>'chunk','chunk_id'=>(string)$id,
        'before_sha256'=>$baseline['before_sha256'],
        'after_sha256'=>$baseline['after_sha256'],
        'request_digest'=>hash('sha256',$raw),
        'nonce'=>bin2hex(random_bytes(16)),
        'expires_at'=>(string)(time()+55));
    $permit=array('claims'=>$claims,'baseline'=>$baseline,
        'mac'=>ModxMcpCasPermitWireV2::sign($claims,$key));
    $tokenSetting=$modx->getObject($settingClass,array('key'=>'modxmcp.api_token'));
    $token=$tokenSetting ? (string)$tokenSetting->get('value') : '';
    if (!preg_match('/\A[0-9a-f]{64}\z/D',$token)) {
        throw new RuntimeException('no disposable CI token');
    }
    $payload=array(
        'site'=>$site,'id'=>$id,'before'=>$before,'after'=>$after,
        'raw'=>$raw,'permit'=>$permit,
        'token'=>$token,'directory'=>$dir,'original'=>$original,
        'probe'=>$probe,'partition_keys'=>$partitionKeys,
    );
    if (file_put_contents($fixture,json_encode($payload))===false ||
        !chmod($fixture,0600)) {
        throw new RuntimeException('cannot persist temporary private CI fixture');
    }
    echo "CAS_V2_HTTP_CI_PREPARED $platform\n";
    exit(0);
}
if (!is_file($fixture) || (fileperms($fixture)&0077)!==0) {
    throw new RuntimeException('missing or nonprivate CI fixture');
}
$stored=json_decode(file_get_contents($fixture),true);
if (!is_array($stored) || $stored['site']!==$site ||
    !is_dir($stored['directory']) || !is_file($stored['directory'].'/signing-key')) {
    throw new RuntimeException('CI fixture identity mismatch');
}
$endpoint='http://127.0.0.1:8081/assets/components/modxmcp/api.php';
function casHttpPost($endpoint,$token,$raw,$permit=null) {
    $headers=array('Content-Type: application/json; charset=utf-8',
                   'X-MCP-Token: '.$token);
    if ($permit!==null) {
        $headers[]='X-SiteAgent-Permit: '.base64_encode(
            json_encode($permit,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    }
    $ch=curl_init($endpoint);
    curl_setopt_array($ch,array(CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$raw,
        CURLOPT_HTTPHEADER=>$headers,CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>20));
    $response=curl_exec($ch);
    $status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if (!is_string($response)) {
        throw new RuntimeException('disposable CAS HTTP request failed');
    }
    $decoded=json_decode($response,true);
    if (!is_array($decoded)) {
        throw new RuntimeException('disposable CAS HTTP response invalid');
    }
    return array($status,$decoded);
}
$chunkTable=trim((string)$modx->getTableName($chunkClass),'`');
$revisionTable=trim((string)$modx->getTableName($settingClass),'`');
$query=$modx->prepare('SELECT snippet,static FROM `'.$chunkTable.'` WHERE id=?');
$readChunk=function() use($query,$stored) {
    $query->execute(array($stored['id']));
    return $query->fetch(PDO::FETCH_ASSOC);
};
try {
    $source=MODX_CORE_PATH.'components/modxmcp/endpoint/chunk-cas-processor-v2.php';
    if (!is_file($source)) {
        throw new RuntimeException('installed transport omitted signed CAS processor');
    }
    list($http,$reply)=casHttpPost(
        $endpoint,$stored['token'],$stored['raw'],$stored['permit']);
    if ($http!==200 || $reply['success']!==true ||
        !isset($reply['data']['chunk_id']) ||
        $reply['data']['chunk_id']!==$stored['id'] ||
        $readChunk()['snippet']!==$stored['after']) {
        throw new RuntimeException('signed MODX HTTP CAS write/readback failed (HTTP '.$http.')');
    }
    $revision=$modx->prepare('SELECT value FROM `'.$revisionTable.'` WHERE `key`=?');
    $revision->execute(array('modxmcp.site_revision'));
    if ((string)$revision->fetchColumn()!=='1') {
        throw new RuntimeException('HTTP CAS did not persist revision in same transaction');
    }
    foreach ($stored['partition_keys'] as $part=>$key) {
        if ($modx->getCacheManager()->get($stored['probe'],array('cache_key'=>$key))!==null) {
            throw new RuntimeException('HTTP CAS cache was not invalidated: '.$part);
        }
    }
    if (count(glob($stored['directory'].'/used/*.used'))!==1) {
        throw new RuntimeException('HTTP CAS nonce reservation missing');
    }
    echo "CAS_V2_SIGNED_HTTP_WRITE_AND_READBACK_OK $platform\n";

    list($http,$reply)=casHttpPost(
        $endpoint,$stored['token'],$stored['raw'],$stored['permit']);
    if ($http<400 || !empty($reply['success']) ||
        $readChunk()['snippet']!==$stored['after']) {
        throw new RuntimeException('HTTP replay was not rejected');
    }
    $replay=$stored['permit'];
    $replay['claims']['nonce']=bin2hex(random_bytes(16));
    $key=hex2bin(trim(file_get_contents($stored['directory'].'/signing-key')));
    $replay['mac']=ModxMcpCasPermitWireV2::sign($replay['claims'],$key);
    list($http,$reply)=casHttpPost($endpoint,$stored['token'],$stored['raw'],$replay);
    if ($http<400 || !empty($reply['success']) ||
        $readChunk()['snippet']!==$stored['after']) {
        throw new RuntimeException('HTTP stale baseline was not rejected');
    }
    if (count(glob($stored['directory'].'/used/*.used'))!==2) {
        throw new RuntimeException('HTTP CAS replay/stale nonce semantics changed');
    }
    list($http,$reply)=casHttpPost(
        $endpoint,$stored['token'],$stored['raw'],null);
    if ($http<400 || !empty($reply['success'])) {
        throw new RuntimeException('unsigned HTTP write was accepted');
    }
    echo "CAS_V2_HTTP_REPLAY_STALE_UNSIGNED_DENIED_OK $platform\n";
} finally {
    $modx->removeObject($chunkClass,array('id'=>$stored['id']));
    foreach ($stored['original'] as $name=>$old) {
        if ($old['present']) {
            $changeSetting($name,$old['value']);
        } else {
            $modx->removeObject($settingClass,array('key'=>$name));
        }
    }
    $modx->getCacheManager()->refresh(array('system_settings'=>array()));
    foreach (glob($stored['directory'].'/used/*.used') as $name) { unlink($name); }
    unlink($stored['directory'].'/signing-key');
    rmdir($stored['directory'].'/used');
    rmdir($stored['directory']);
    unlink($fixture);
}
