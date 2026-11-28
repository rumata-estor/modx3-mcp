<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Отдельное CAS-доказательство создания медиаисточника без операций с файлами.
 * EN: Disposable media source definition CAS lifecycle, no file operations.
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
function mediaRpc($action,array $data=array()) {
    global $api;
    return disposableCasRpc($api,$action,$data);
}
function mediaCheck($test,$label) {
    global $checks;
    if(!$test)throw new RuntimeException('CAS_CREATE_EVIDENCE_FAILED '.$label);
    $checks++;
}
$fixture=bin2hex(random_bytes(5));
$name='MCP_Disposable_Source_'.$fixture;
$id=0;
function mediaGuard(){
    $state=mediaRpc('get_site_state');
    return array('observed_site_revision'=>(int)$state['site_revision'],
        'strict_site_revision'=>true,'read_set'=>array());
}
function mediaReplay($action,array $request){
    $before=mediaRpc('get_site_state');
    $denied=false;
    try{mediaRpc($action,$request);}
    catch(ModxMCPClientException $e){$denied=strpos($e->getMessage(),'STALE_STATE')!==false;}
    mediaCheck($denied,$action.' stale CAS replay denied');
    $after=mediaRpc('get_site_state');
    mediaCheck((int)$before['site_revision']===(int)$after['site_revision'],
        $action.' stale replay did not increment revision');
}
try {
    // RU: Ограничиваем источник только одноразовым RUNNER_TEMP. Нет файловых операций.
    // EN: Source definition points only at the disposable runner sandbox.
    $sandbox=rtrim((string)getenv('RUNNER_TEMP'),'/').'/modxmcp-media-'.$fixture.'/';
    mediaCheck(strpos($sandbox,'/home/runner/')===0,
        'media sandbox restricted to disposable runner');
    $create=array('name'=>$name,'description'=>'disposable source',
        'properties'=>array('basePath'=>$sandbox,'baseUrl'=>'/mcp-disposable-'.$fixture.'/','allowedFileTypes'=>'txt'),
        '_runtime_preconditions'=>mediaGuard());
    $created=mediaRpc('create_media_source',$create);
    $id=(int)($created['id']??0);
    mediaCheck($id>0,'source create returned ID');
    $read=mediaRpc('get_media_source',array('id'=>$id));
    mediaCheck(is_array($read) && (int)($read['id']??0)===$id
        && (string)($read['name']??'')===$name,
        'created source independent readback');
    mediaReplay('create_media_source',$create);
    echo 'CAS_MEDIA_SOURCE_CREATE_READBACK_STALE_REPLAY_OK'.PHP_EOL;

    $update=array('id'=>$id,'name'=>$name,
        'description'=>'disposable updated',
        '_runtime_preconditions'=>mediaGuard());
    mediaRpc('update_media_source',$update);
    $read=mediaRpc('get_media_source',array('id'=>$id));
    mediaCheck(($read['description']??'')==='disposable updated',
        'updated media source readback');
    mediaReplay('update_media_source',$update);
    echo 'CAS_MEDIA_SOURCE_UPDATE_READBACK_STALE_REPLAY_OK'.PHP_EOL;
    // RU: Только временный источник; не изменяем глобальный upload_files.
    // EN: Disposable source only; no global upload_files mutation.
    mkdir($sandbox,0700,true);
    $denied=false;
    try {
        mediaRpc('create_media_file',array('source'=>$id,'path'=>'/','name'=>'denied.tpl','content'=>'DISPOSABLE'));
    } catch (ModxMCPClientException $e) {
        $denied=strpos($e->getMessage(),'MEDIA_TPL_WRITE_REFUSED:')===0
            && strpos($e->getMessage(),'upload_files')!==false;
    }
    mediaCheck($denied,'native restricted .tpl write has safe guidance');
    mediaCheck(!file_exists($sandbox.'denied.tpl'),'restricted file was not created');
    rmdir($sandbox);
    echo 'MEDIA_TPL_NATIVE_REFUSAL_DIAGNOSTIC_OK'.PHP_EOL;


    $delete=array('id'=>$id,'_runtime_preconditions'=>mediaGuard());
    mediaRpc('delete_media_source',$delete);
    $id=0;
    mediaReplay('delete_media_source',$delete);
    $list=mediaRpc('list_media_sources');
    mediaCheck(is_array($list),'media sources list available');
    foreach($list as $row){
        mediaCheck(!is_array($row)||($row['name']??'')!==$name,
            'deleted source absent from list');
    }
    echo 'CAS_MEDIA_SOURCE_DELETE_READBACK_STALE_REPLAY_OK'.PHP_EOL;
} finally {
    if($id>0)mediaRpc('delete_media_source',array('id'=>$id));
}
echo 'CAS_MEDIA_SOURCE_LIFECYCLE_OK checks='.$checks
    .' platform='.($is2?'modx2':'modx3').PHP_EOL;
