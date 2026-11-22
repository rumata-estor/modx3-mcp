<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Нестандартный Media Source не должен обходиться локальными PHP API.
 * EN: Nonstandard media drivers must not be bypassed with local PHP APIs.
 */
namespace ModxMcp\Tools {
    interface ToolInterface {
        public function name(); public function group(); public function isMutation();
        public function supports($context); public function execute($context, array $data);
    }
    class AuditSupport {
        public static function log($context, $action, $kind, $data) {}
    }
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/FilesystemSupport.php';
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/MediaSourceSupport.php';
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/MediaSourceMutationSupport.php';
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/MediaSourceFileReadTool.php';
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/MediaSourceFilesTool.php';
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/MediaFolderDeleteTool.php';
}
namespace {
class ModxMCPClientException extends \RuntimeException {}
class CustomMediaFakeSource {
    private $classKey;
    private $root;
    public $deletedPath = null;
    public function __construct($classKey, $root) {
        $this->classKey=$classKey; $this->root=$root;
    }
    public function get($key) {
        if ($key==='class_key') return $this->classKey;
        if ($key==='name') return 'CustomMedia';
        if ($key==='id') return 71;
        if ($key==='properties') return array('basePath'=>array('value'=>$this->root));
        return '';
    }
    public function initialize() {}
    public function removeContainer($path) {$this->deletedPath=$path; return true;}
}
class CustomMediaFakePlatform {
    private $major;
    public function __construct($major){$this->major=$major;}
    public function className($which){return 'FakeSource';}
    public function majorVersion(){return $this->major;}
}
class CustomMediaFakeModx {
    private $source; private $allowed;
    public function __construct($source,$allowed){$this->source=$source;$this->allowed=$allowed;}
    public function getObject($class,$selector){return $this->source;}
    public function getOption($name,$opts=null,$default=null) {
        if($name==='modxmcp.allow_root_filesystem_read')return $this->allowed;
        if($name==='base_path')return dirname($this->source->get('properties')['basePath']['value']);
        return $default;
    }
    public function getCacheManager(){return null;}
}
class CustomMediaFakeContext {
    private $modx; private $platform;
    public function __construct($source,$allowed,$major){
        $this->modx=new CustomMediaFakeModx($source,$allowed);
        $this->platform=new CustomMediaFakePlatform($major);
    }
    public function modx(){return $this->modx;}
    public function platform(){return $this->platform;}
}
function customCheck($ok,$what){
    global $checks;
    if (!$ok) throw new \RuntimeException('CUSTOM_MEDIA_BOUNDARY_FAIL '.$what);
    $checks++;
}
function customDenied($callback,$what){
    $blocked=false;
    try{$callback();}catch(ModxMCPClientException $e){
        $blocked=strpos($e->getMessage(),'custom or remote driver')!==false
            || strpos($e->getMessage(),'allow_root_filesystem_read')!==false;
    }
    customCheck($blocked,$what);
}
$checks=0;
$root=sys_get_temp_dir().'/modxmcp-media-driver-'.bin2hex(random_bytes(5));
mkdir($root,0700);
file_put_contents($root.'/secret.txt','DO_NOT_EXPOSE');
$read=new \ModxMcp\Tools\MediaSourceFileReadTool();
$list=new \ModxMcp\Tools\MediaSourceFilesTool();
$del=new \ModxMcp\Tools\MediaFolderDeleteTool();
try {
    $custom=new CustomMediaFakeSource('Vendor\\Remote\\CloudMediaSource',$root);
    foreach(array(2,3) as $major) {
        $context=new CustomMediaFakeContext($custom,true,$major);
        customDenied(function()use($read,$context){
            $read->execute($context,array('id'=>71,'path'=>'secret.txt'));
        },'custom direct read blocked');
        customDenied(function()use($list,$context){
            $list->execute($context,array('id'=>71,'path'=>'/'));
        },'custom direct list blocked');
        $custom->deletedPath=null;
        $result=$del->execute($context,array('source'=>71,'path'=>'subfolder'));
        customCheck($result['deleted']===true && $custom->deletedPath==='subfolder',
            'custom folder delete stays source-relative');
        customCheck(file_get_contents($root.'/secret.txt')==='DO_NOT_EXPOSE',
            'custom driver did not read or modify external host file');
    }
    foreach(array('modFileMediaSource',
        'MODX\\Revolution\\Sources\\modFileMediaSource') as $builtIn) {
        $source=new CustomMediaFakeSource($builtIn,$root);
        $disabled=new CustomMediaFakeContext($source,false,2);
        customDenied(function()use($read,$disabled){
            $read->execute($disabled,array('id'=>71,'path'=>'secret.txt'));
        },'local root browse needs explicit allow');
        $enabled=new CustomMediaFakeContext($source,true,2);
        $response=$read->execute($enabled,array('id'=>71,'path'=>'secret.txt'));
        $text=$response['encoding']==='base64'
            ? base64_decode($response['content']) : $response['content'];
        customCheck($text==='DO_NOT_EXPOSE','normal local media read preserved');
        $entries=$list->execute($enabled,array('id'=>71));
        customCheck(count($entries['entries'])===1
            && $entries['entries'][0]['name']==='secret.txt',
            'normal local directory list preserved');
        $source->deletedPath=null;
        $del->execute($enabled,array('source'=>71,'path'=>'subfolder'));
        customCheck($source->deletedPath===$root.'/subfolder',
            'built-in MODX2 directory delete uses same existing absolute path');
    }
    customCheck($checks===16,'expected boundary check count');
    echo 'CUSTOM_MEDIA_DRIVER_BOUNDARY_OK checks='.$checks.PHP_EOL;
} finally {
    unlink($root.'/secret.txt');
    rmdir($root);
}
}
