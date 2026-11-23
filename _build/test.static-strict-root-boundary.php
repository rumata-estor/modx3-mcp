<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: strict не создаёт core/elements через PHP и безопасно создаёт дочерние каталоги.
 * EN: Strict mode refuses a missing static root, creates children using dirfd only.
 */
namespace ModxMcp\Tools {
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/FilesystemSupport.php';
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/StaticFileWriteSupport.php';
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/ElementContentSupport.php';
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/ElementMutationSupport.php';
}
namespace {
class ModxMCPClientException extends \RuntimeException {}
class StrictRootElement {
    public $data=array('id'=>12,'name'=>'strict-child','snippet'=>'SAFE_PAYLOAD',
        'static'=>0,'static_file'=>'','source'=>0);
    public $saves=0;
    public function get($key){return isset($this->data[$key])?$this->data[$key]:null;}
    public function set($key,$value){$this->data[$key]=$value;}
    public function save(){$this->saves++;return true;}
}
class StrictRootModx {
    public $root;public $el;public $mode='strict';
    public function __construct($root){$this->root=$root;$this->el=new StrictRootElement();}
    public function getOption($key,$options=null,$default=null) {
        if($key==='core_path')return $this->root.'/core/';
        if($key==='base_path')return $this->root.'/';
        if($key==='modxmcp.static_io_mode')return $this->mode;
        return $default;
    }
    public function getObject($class,$id) {
        return $class==='FakeChunk' && (int)$id===12?$this->el:null;
    }
}
class StrictRootPlatform {
    public function className($type){return $type==='chunk'?'FakeChunk':null;}
}
class StrictRootContext {
    public $m;public $p;
    public function __construct($root){$this->m=new StrictRootModx($root);$this->p=new StrictRootPlatform();}
    public function modx(){return $this->m;}
    public function platform(){return $this->p;}
}
function strictRootCheck($condition,$message){
    if(!$condition)throw new \RuntimeException('STATIC_STRICT_ROOT_TEST_FAILED: '.$message);
}
$root=sys_get_temp_dir().'/modxmcp-strict-root-'.bin2hex(random_bytes(6));
mkdir($root.'/core',0700,true);
$ctx=new StrictRootContext($root);
try {
    $denied=false;
    try {\ModxMcp\Tools\ElementMutationSupport::makeStatic($ctx,'chunk',12);}
    catch(ModxMCPClientException $e){$denied=true;}
    strictRootCheck($denied,'missing root must refuse');
    strictRootCheck(!is_dir($root.'/core/elements'),'strict must not create root via PHP');
    strictRootCheck($ctx->m->el->saves===0,'metadata unchanged on refusal');

    mkdir($root.'/core/elements',0700,false);
    $response=\ModxMcp\Tools\ElementMutationSupport::makeStatic($ctx,'chunk',12);
    $file=$root.'/core/elements/chunks/strict-child.tpl';
    strictRootCheck(($response['static_file']??'')==='core/elements/chunks/strict-child.tpl',
        'returned static path');
    strictRootCheck(file_get_contents($file)==='SAFE_PAYLOAD','dirfd-created directory and file');
    strictRootCheck($ctx->m->el->saves===1,'metadata saved exactly once');

    $read=\ModxMcp\Tools\ElementContentSupport::read(
        $ctx,$ctx->m->el,array('field'=>'snippet')
    );
    strictRootCheck($read[0]==='SAFE_PAYLOAD','strict read through shared backend');

    $ctx->m->mode='unsupported';
    $denied=false;
    try {\ModxMcp\Tools\StaticFileWriteSupport::replace($ctx,$file,'BAD');}
    catch(ModxMCPClientException $e){$denied=true;}
    strictRootCheck($denied && file_get_contents($file)==='SAFE_PAYLOAD',
        'unknown mode must not downgrade or mutate');
    echo 'STATIC_STRICT_ROOT_BOUNDARY_OK checks=8'.PHP_EOL;
} finally {
    $file=$root.'/core/elements/chunks/strict-child.tpl';
    if(is_file($file))unlink($file);
    if(is_dir($root.'/core/elements/chunks'))rmdir($root.'/core/elements/chunks');
    if(is_dir($root.'/core/elements'))rmdir($root.'/core/elements');
    rmdir($root.'/core');rmdir($root);
}
}
