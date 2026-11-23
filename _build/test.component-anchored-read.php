<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Чтение компонента не выходит за корень при симлинках и отсутствии Python.
 * EN: Component reads are descriptor-anchored and fail closed without Python.
 */
class ModxMCPClientException extends \RuntimeException {}
require_once __DIR__.'/../core/components/modxmcp/src/Tools/ToolInterface.php';
require_once __DIR__.'/../core/components/modxmcp/src/Tools/FilesystemSupport.php';
require_once __DIR__.'/../core/components/modxmcp/src/Tools/ComponentSupport.php';
require_once __DIR__.'/../core/components/modxmcp/src/Tools/ComponentFileReadTool.php';
class AnchoredModx {
    public $root;
    public function __construct($root) {$this->root=$root;}
    public function getOption($key,$options=null,$default=null) {
        if ($key==='base_path')return $this->root.'/';
        if ($key==='core_path')return $this->root.'/core/';
        if ($key==='assets_path')return $this->root.'/assets/';
        if ($key==='modxmcp.component_code_roots')return 'core/components,assets/components';
        return $default;
    }
}
class AnchoredContext {
    private $m;
    public function __construct($m){$this->m=$m;}
    public function modx(){return $this->m;}
}
function must($truth,$label){
    if (!$truth)throw new \RuntimeException('COMPONENT_ANCHORED_READ_FAILED '.$label);
}
function deny($callback,$label){
    try {$callback();}
    catch(ModxMCPClientException $e) {
        must(strpos($e->getMessage(),'MANUAL_REVIEW_REQUIRED:')===0
            || strpos($e->getMessage(),'Resolved path is outside')!==false,
            $label.' wrong error: '.$e->getMessage());
        return;
    }
    throw new \RuntimeException('COMPONENT_ANCHORED_READ_FAILED '.$label.' not refused');
}
function deleteTree($dir){
    if(!is_dir($dir))return;
    foreach(new FilesystemIterator($dir) as $f){
        if($f->isDir()&&!$f->isLink())deleteTree($f->getPathname());
        else unlink($f->getPathname());
    }
    rmdir($dir);
}
$root=sys_get_temp_dir().'/modxmcp-component-anchor-'.bin2hex(random_bytes(5));
mkdir($root.'/core/components/demo/sub',0700,true);
mkdir($root.'/assets/components',0700,true);
mkdir($root.'/private',0700,true);
file_put_contents($root.'/core/components/demo/sub/readme.txt','HELLO_WORLD');
file_put_contents($root.'/core/components/demo/blob.dat',"\x00\xFF\x7F");
file_put_contents($root.'/private/secret.txt','CANNOT_READ_PRIVATE');
$ctx=new AnchoredContext(new AnchoredModx($root));
$reader=new \ModxMcp\Tools\ComponentFileReadTool();
$tests=0;
try {
    $r=$reader->execute($ctx,array('name'=>'demo','path'=>'sub/readme.txt'));
    must($r['content']==='HELLO_WORLD'&&$r['encoding']==='utf-8'
        &&$r['size']===11&&!$r['truncated'],'full public text read');
    $tests++;
    $r=$reader->execute($ctx,array('name'=>'demo','path'=>'sub/readme.txt',
        'offset'=>6,'bytes'=>3));
    must($r['content']==='WOR'&&$r['offset']===6&&$r['returned_bytes']===3
        &&$r['truncated'],'bounded range');
    $tests++;
    $r=$reader->execute($ctx,array('name'=>'demo','path'=>'blob.dat'));
    must($r['encoding']==='base64'
        &&base64_decode($r['content'],true)==="\x00\xFF\x7F",'binary read');
    $tests++;
    symlink($root.'/private/secret.txt',$root.'/core/components/demo/symlink.txt');
    deny(function()use($reader,$ctx){$reader->execute($ctx,
        array('name'=>'demo','path'=>'symlink.txt'));},'symlink to outside');
    $tests++;
    symlink($root.'/core/components/demo/sub/readme.txt',
        $root.'/core/components/demo/internal-link.txt');
    deny(function()use($reader,$ctx){$reader->execute($ctx,
        array('name'=>'demo','path'=>'internal-link.txt'));},'symlink within root');
    $tests++;
    symlink($root.'/private',$root.'/core/components/demo/escaped');
    deny(function()use($reader,$ctx){$reader->execute($ctx,
        array('name'=>'demo','path'=>'escaped/secret.txt'));},'symlink directory');
    $tests++;
    $previous=getenv('PATH');
    try {
        putenv('PATH=/definitely-not-a-python-executable');
        deny(function()use($reader,$ctx){$reader->execute($ctx,
            array('name'=>'demo','path'=>'sub/readme.txt'));},'python unavailable');
        $tests++;
    } finally {
        if ($previous===false)putenv('PATH');
        else putenv('PATH='.$previous);
    }
} finally {
    deleteTree($root);
}
echo 'COMPONENT_ANCHORED_READ_FAIL_CLOSED_OK tests='.$tests.PHP_EOL;
