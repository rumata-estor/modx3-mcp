<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Локальный Media Source внутри assets: чтение и хеш без root opt-in.
 * EN: Stock assets media source read/hash without site-root opt-in.
 */
namespace ModxMcp\Tools {
    interface ToolInterface {
        public function name(); public function group(); public function isMutation();
        public function supports($context); public function execute($context, array $data);
    }
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/FilesystemSupport.php';
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/MediaSourceSupport.php';
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/MediaSourceFileReadTool.php';
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/MediaSourceFilesTool.php';
}
namespace {
class ModxMCPClientException extends \RuntimeException {}
class AssetsPolicySource {
    private $root;
    private $classKey;
    private $name;
    public function __construct($root, $classKey='modFileMediaSource', $name='Images') {
        $this->root=$root; $this->classKey=$classKey; $this->name=$name;
    }
    public function get($field) {
        if ($field==='id') return 42;
        if ($field==='name') return $this->name;
        if ($field==='class_key') return $this->classKey;
        if ($field==='properties') return array('basePath'=>array('value'=>$this->root));
        return '';
    }
    public function initialize() {}
    public function getObjectContents($path) {
        $raw=@file_get_contents($this->root.'/'.ltrim($path,'/'));
        return $raw===false ? false : array('content'=>$raw);
    }
}
class AssetsPolicyModx {
    public $source;
    private $root;
    private $allowRoot;
    public function __construct($root, $source, $allowRoot=false) {
        $this->root=$root; $this->source=$source; $this->allowRoot=$allowRoot;
    }
    public function getOption($key, $unused=null, $default=null) {
        if ($key==='assets_path') return $this->root.'/assets/';
        if ($key==='core_path') return $this->root.'/core/';
        if ($key==='base_path') return $this->root.'/';
        if ($key==='modxmcp.allow_root_filesystem_read') return $this->allowRoot;
        if ($key==='modxmcp.max_read_bytes') return 262144;
        return $default;
    }
    public function getObject($class, $selector) {return $this->source;}
}
class AssetsPolicyPlatform {
    public function className($entity) {return 'LocalMediaSource';}
}
class AssetsPolicyContext {
    private $modx;
    public function __construct($root, $source, $allowRoot=false) {
        $this->modx=new AssetsPolicyModx($root,$source,$allowRoot);
    }
    public function modx() {return $this->modx;}
    public function platform() {return new AssetsPolicyPlatform();}
}
$checks=0;
function assetsCheck($condition, $label) {
    global $checks;
    if (!$condition) throw new \RuntimeException('ASSETS_MEDIA_POLICY_FAIL '.$label);
    ++$checks;
}
function assetsDeny($callback,$label) {
    $denied=false;
    try {$callback();} catch (\ModxMCPClientException $e) {$denied=true;}
    assetsCheck($denied,$label);
}
$root=sys_get_temp_dir().'/modxmcp-assets-policy-'.bin2hex(random_bytes(6));
mkdir($root.'/assets/images',0700,true);
mkdir($root.'/core',0700,true);
mkdir($root.'/outside',0700,true);
mkdir($root.'/assets_extra',0700,true);
$payload='LOCAL_SOURCE_HASH_CAS_OK';
file_put_contents($root.'/assets/images/sample.txt',$payload);
file_put_contents($root.'/outside/secret.txt','PRIVATE');
file_put_contents($root.'/index.php','SITE_ROOT_FILE');
$read=new \ModxMcp\Tools\MediaSourceFileReadTool();
$list=new \ModxMcp\Tools\MediaSourceFilesTool();
try {
    foreach (array('modFileMediaSource','sources.modFileMediaSource',
        'MODX\\Revolution\\Sources\\modFileMediaSource') as $key) {
        $source=new AssetsPolicySource($root.'/assets/images',$key);
        $context=new AssetsPolicyContext($root,$source,false);
        $approved=\ModxMcp\Tools\MediaSourceSupport::assertLocalReadAllowed($context,$source);
        assetsCheck($approved===realpath($root.'/assets/images'),
            'canonical assets root allowed by default');
        $result=$read->execute($context,array('id'=>42,'path'=>'sample.txt','bytes'=>512));
        assetsCheck($result['sha256']===hash('sha256',$payload),
            'complete native API-verified hash without broad read grant');
        $plain=$result['encoding']==='base64'
            ? base64_decode($result['content']) : $result['content'];
        assetsCheck($plain===$payload,'correct source bytes');
        $partial=$read->execute($context,array('id'=>42,'path'=>'sample.txt','bytes'=>4));
        assetsCheck(!isset($partial['sha256']),'partial read does not advertise CAS hash');
        $items=$list->execute($context,array('id'=>42));
        assetsCheck(count($items['entries'])===1
            && $items['entries'][0]['name']==='sample.txt',
            'assets directory browsing succeeds');
        assetsDeny(function()use($read,$context) {
            $read->execute($context,array('id'=>42,'path'=>'../../outside/secret.txt'));
        },'traversal still denied');
    }
    foreach (array(
        $root,
        $root.'/core',
        $root.'/outside',
        $root.'/assets_extra'
    ) as $unsafe) {
        $source=new AssetsPolicySource($unsafe,'modFileMediaSource','Images');
        $context=new AssetsPolicyContext($root,$source,false);
        assetsDeny(function()use($list,$context) {
            $list->execute($context,array('id'=>42));
        },'site root, core or external root denied by default');
    }
    $source=new AssetsPolicySource($root.'/assets/images',
        'Vendor\\CloudMediaSource','Images');
    $context=new AssetsPolicyContext($root,$source,true);
    assetsDeny(function()use($read,$context) {
        $read->execute($context,array('id'=>42,'path'=>'sample.txt'));
    },'custom remote driver still denied even with root opt-in');
    $source=new AssetsPolicySource($root,'modFileMediaSource','Filesystem');
    $context=new AssetsPolicyContext($root,$source,true);
    $result=$read->execute($context,array('id'=>42,'path'=>'index.php','bytes'=>512));
    assetsCheck($result['sha256']===hash('sha256','SITE_ROOT_FILE'),
        'explicit root opt-in retains previous admin-authorized behavior');
    $link=$root.'/assets/images/escape.txt';
    if (function_exists('symlink') && @symlink($root.'/outside/secret.txt',$link)) {
        $source=new AssetsPolicySource($root.'/assets/images');
        $context=new AssetsPolicyContext($root,$source,false);
        assetsDeny(function()use($read,$context) {
            $read->execute($context,array('id'=>42,'path'=>'escape.txt'));
        },'symlink escaping approved assets denied');
        unlink($link);
    }
    assetsCheck($checks>=24,'regression test coverage');
    echo 'ASSETS_MEDIA_POLICY_OK checks='.$checks.PHP_EOL;
} finally {
    if (is_link($root.'/assets/images/escape.txt')) unlink($root.'/assets/images/escape.txt');
    unlink($root.'/assets/images/sample.txt');
    unlink($root.'/outside/secret.txt');
    unlink($root.'/index.php');
    rmdir($root.'/assets/images'); rmdir($root.'/assets');
    rmdir($root.'/outside'); rmdir($root.'/assets_extra');
    rmdir($root.'/core'); rmdir($root);
}
}
