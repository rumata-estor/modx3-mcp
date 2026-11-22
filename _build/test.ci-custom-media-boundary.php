<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Проверка стандартного media source после ограничений custom-драйверов.
 * EN: Disposable stock MODX file source regression after custom driver guard.
 */
if (PHP_SAPI!=='cli' || getenv('MODXMCP_DISPOSABLE_CI')!=='1'
    || strpos((string)getenv('MODX_CONFIG_CORE'),'/home/runner/')!==0
    || strpos((string)getenv('RUNNER_TEMP'),'/home/runner/')!==0
    || !is_file((string)getenv('MODX_CONFIG_CORE')))exit(3);
require getenv('MODX_CONFIG_CORE');
$is2=!is_file(MODX_CORE_PATH.'src/Revolution/modX.php');
if($is2){require_once MODX_CORE_PATH.'model/modx/modx.class.php';$modx=new modX();}
else{require_once MODX_CORE_PATH.'vendor/autoload.php';
    $modx=\MODX\Revolution\modX::getInstance();}
$modx->initialize('mgr');
$modx->setOption('modxmcp.disabled_groups','');
require MODX_CORE_PATH.'components/modxmcp/model/modxmcp.class.php';
$api=new modxMCP($modx);
$checks=0;
function localCheck($yes,$what){
    global $checks;
    if(!$yes)throw new RuntimeException('STOCK_LOCAL_MEDIA_FAIL '.$what);
    $checks++;
}
$source=$api->processRequest('get_media_source','',array('id'=>1));
localCheck(is_array($source) && isset($source['class_key']),'stock source metadata');
localCheck(\ModxMcp\Tools\MediaSourceSupport::isBuiltinLocalFileSource(
    new class($source['class_key']) {
        private $classKey;
        public function __construct($key){$this->classKey=$key;}
        public function get($field){return $field==='class_key'?$this->classKey:'';}
    }
),'actual MODX class key recognized');
$blocked=false;
try{$api->processRequest('list_media_source_files','',array('id'=>1));}
catch(ModxMCPClientException $e){
    $blocked=strpos($e->getMessage(),'allow_root_filesystem_read')!==false;
}
localCheck($blocked,'default root browsing remains denied');
$modx->setOption('modxmcp.allow_root_filesystem_read',true);
$list=$api->processRequest('list_media_source_files','',array('id'=>1));
localCheck(is_array($list) && isset($list['entries'])
    && is_array($list['entries']),'stock source listing allowed');
$path='components/modxmcp/api.php';
$root=rtrim((string)$source['root_path'],'/').'/';
localCheck(is_file($root.$path),'known disposable MCP asset installed');
$result=$api->processRequest('read_media_source_file','',array(
    'id'=>1,'path'=>$path,'bytes'=>256
));
$text=isset($result['content']) ? (string)$result['content'] : '';
if(($result['encoding']??'')==='base64')$text=base64_decode($text);
localCheck(strpos($text,'<?php')===0,'stock asset readable only when allowed');
localCheck(($result['returned_bytes']??0)>0,'bounded read returned data');
echo 'STOCK_MEDIA_SOURCE_MODX'.($is2?'2':'3').'_OK checks='.$checks.PHP_EOL;
