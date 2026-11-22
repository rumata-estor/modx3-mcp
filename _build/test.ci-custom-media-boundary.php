<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Реальный MODX 2/3: локальный Media Source не меняет поведение.
 * EN: Disposable real MODX 2/3 local media-source regression.
 */
if (PHP_SAPI !== 'cli' || getenv('MODXMCP_DISPOSABLE_CI') !== '1'
    || strpos((string)getenv('MODX_CONFIG_CORE'),'/home/runner/')!==0
    || strpos((string)getenv('RUNNER_TEMP'),'/home/runner/')!==0
    || !is_file((string)getenv('MODX_CONFIG_CORE'))) exit(3);
require getenv('MODX_CONFIG_CORE');
$is2 = !is_file(MODX_CORE_PATH.'src/Revolution/modX.php');
if($is2){require_once MODX_CORE_PATH.'model/modx/modx.class.php';$modx=new modX();}
else{require_once MODX_CORE_PATH.'vendor/autoload.php';
    $modx=\MODX\Revolution\modX::getInstance();}
$modx->initialize('mgr');
$modx->setOption('modxmcp.disabled_groups','');
$modx->setOption('modxmcp.allow_root_filesystem_read',true);
require MODX_CORE_PATH.'components/modxmcp/model/modxmcp.class.php';
require __DIR__.'/test.cas-mcp-helper.php';
$api=new modxMCP($modx);
$checks=0;
function cmRpc($action,array $data=array()){
    global $api;return disposableCasRpc($api,$action,$data);
}
function cmCheck($valid,$label){
    global $checks;
    if(!$valid)throw new RuntimeException('CI_MEDIA_BOUNDARY_FAIL '.$label);
    $checks++;
}
$marker=bin2hex(random_bytes(5));
$root=getenv('RUNNER_TEMP').'/mcp-media-boundary-'.$marker;
$sourceId=0;
$relative='file-'.$marker.'.txt';
$secret='MEDIA_DRIVER_TEST_'.$marker;
cmCheck(@mkdir($root,0700,true),'create isolated temp directory');
try {
    $created=cmRpc('create_media_source',array(
        'name'=>'MCP_Media_Boundary_'.$marker,
        'description'=>'temporary disposable local file source',
        'properties'=>array('basePath'=>$root.'/','baseUrl'=>'/mcp-ci-'.$marker.'/')
    ));
    $sourceId=(int)($created['id']??0);
    cmCheck($sourceId>0,'local source created through MCP');
    $source=cmRpc('get_media_source',array('id'=>$sourceId));
    $classKey=(string)($source['class_key']??'');
    cmCheck(\ModxMcp\Tools\MediaSourceSupport::isBuiltinLocalFileSource(
        new class($classKey) {
            private $key;
            public function __construct($key){$this->key=$key;}
            public function get($field){return $field==='class_key'?$this->key:'';}
        }
    ),'MODX built-in class key recognized');
    $file=cmRpc('create_media_file',array(
        'source'=>$sourceId,'path'=>'/','name'=>$relative,'content'=>$secret
    ));
    cmCheck(!empty($file['created']),'file created via native source driver');
    $read=cmRpc('read_media_source_file',array('id'=>$sourceId,'path'=>$relative));
    $plain=($read['encoding']??'')==='base64'
        ? base64_decode($read['content']) : ($read['content']??'');
    cmCheck($plain===$secret,'local file read via MCP preserved');
    $list=cmRpc('list_media_source_files',array('id'=>$sourceId,'path'=>'/'));
    $found=false;
    foreach(($list['entries']??array()) as $entry) {
        if(($entry['name']??'')===$relative)$found=true;
    }
    cmCheck($found,'local file listed via MCP');
    $deleted=cmRpc('delete_media_file',array('source'=>$sourceId,'path'=>$relative));
    cmCheck(!empty($deleted['deleted']),'file removed via driver');
    $sourceDeleted=cmRpc('delete_media_source',array('id'=>$sourceId));
    $sourceId=0;
    cmCheck(!empty($sourceDeleted),'source deleted via MCP');
    echo 'CI_MEDIA_LOCAL_SOURCE_MODX'.($is2?'2':'3').'_OK checks='.$checks.PHP_EOL;
}finally{
    if($sourceId>0){
        try{cmRpc('delete_media_file',array('source'=>$sourceId,'path'=>$relative));}
        catch(Throwable $ignored){}
        try{cmRpc('delete_media_source',array('id'=>$sourceId));}
        catch(Throwable $ignored){}
    }
    if(is_file($root.'/'.$relative))unlink($root.'/'.$relative);
    if(is_dir($root))rmdir($root);
}
