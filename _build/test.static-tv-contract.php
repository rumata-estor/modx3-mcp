<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Контракт статических TV: проверка путей и списка разрешённых полей.
 * EN: Static TV allowlist and path validation contract (PR #55 review).
 */
namespace ModxMcp\Tools {
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/FilesystemSupport.php';
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/ElementMutationSupport.php';
}
namespace {
class ModxMCPClientException extends \RuntimeException {}
class TvStaticFakeModx {
    public $root;
    public function __construct($root){$this->root=$root;}
    public function getOption($key,$options=null,$default=null){
        if($key==='core_path')return $this->root.'/core/';
        if($key==='base_path')return $this->root.'/';
        if($key==='assets_path')return $this->root.'/assets/';
        return $default;
    }
}
class TvStaticFakeContext {
    private $modx;
    public function __construct($root){$this->modx=new TvStaticFakeModx($root);}
    public function modx(){return $this->modx;}
}
$root=sys_get_temp_dir().'/mcp-tv-static-'.bin2hex(random_bytes(5));
mkdir($root.'/core/elements/tvs',0700,true);
mkdir($root.'/outside',0700,true);
symlink($root.'/outside',$root.'/core/elements/tvs/escape');
$ctx=new TvStaticFakeContext($root);
$checks=0;
function tvStaticCheck($ok,$msg) {
    global $checks;
    if(!$ok)throw new \RuntimeException('STATIC_TV_CONTRACT_FAILED: '.$msg);
    $checks++;
}
try {
    $data=\ModxMcp\Tools\ElementMutationSupport::prepare(
        $ctx,'tv',array('id'=>4,'name'=>'fixture','static'=>1,
        'static_file'=>'core/elements/tvs/fixture.tpl','source'=>1),
        'update_element'
    );
    tvStaticCheck($data['static_file']==='core/elements/tvs/fixture.tpl',
        'valid TV static path accepted');
    $filtered=\ModxMcp\Tools\ElementMutationSupport::filterData(
        'tv',array_merge($data,array('sudo'=>1,'hidden_access'=>1))
    );
    tvStaticCheck(isset($filtered['static']) && isset($filtered['static_file'])
        && isset($filtered['source']),'TV static metadata preserved');
    tvStaticCheck(!isset($filtered['sudo'])&&!isset($filtered['hidden_access']),
        'unsafe fields remain excluded');
    foreach(array(
        '../outside/secret',
        '/etc/passwd',
        'core/elements/tvs/escape/secret',
    ) as $path){
        $denied=false;
        try {\ModxMcp\Tools\ElementMutationSupport::prepare(
            $ctx,'tv',array('id'=>4,'static_file'=>$path),'update_element'
        );}catch(ModxMCPClientException $e){$denied=true;}
        tvStaticCheck($denied,'unsafe TV path rejected: '.$path);
    }
    echo 'STATIC_TV_FIELDS_AND_PATHS_OK checks='.$checks.PHP_EOL;
} finally {
    unlink($root.'/core/elements/tvs/escape');
    rmdir($root.'/core/elements/tvs');
    rmdir($root.'/core/elements');
    rmdir($root.'/core');
    rmdir($root.'/outside');
    rmdir($root);
}
}
