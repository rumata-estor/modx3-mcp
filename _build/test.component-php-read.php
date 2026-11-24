<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Чтение компонентов работает только на PHP, с безопасными отказами.
 * EN: Component-file reads need only PHP and detect symlinks and drift.
 */
class ModxMCPClientException extends \RuntimeException {}
require_once __DIR__.'/../core/components/modxmcp/src/Tools/ToolInterface.php';
require_once __DIR__.'/../core/components/modxmcp/src/Tools/FilesystemSupport.php';
require_once __DIR__.'/../core/components/modxmcp/src/Tools/ComponentSupport.php';
require_once __DIR__.'/../core/components/modxmcp/src/Tools/ComponentFileReadTool.php';
class ComponentPhpModx {
    private $root;
    public function __construct($root) {$this->root=$root;}
    public function getOption($key, $options=null, $default=null) {
        if ($key==='base_path')return $this->root.'/';
        if ($key==='core_path')return $this->root.'/core/';
        if ($key==='assets_path')return $this->root.'/assets/';
        if ($key==='modxmcp.component_code_roots')return 'core/components,assets/components';
        if ($key==='modxmcp.max_read_bytes')return 262144;
        return $default;
    }
}
class ComponentPhpContext {
    private $modx;
    public function __construct($modx){$this->modx=$modx;}
    public function modx(){return $this->modx;}
}
function componentAssert($value,$label){
    if (!$value)throw new \RuntimeException('COMPONENT_PHP_READ_FAILED: '.$label);
}
function componentReject($callback,$label){
    try {$callback();}
    catch(ModxMCPClientException $e) {
        componentAssert(strpos($e->getMessage(),'MANUAL_REVIEW_REQUIRED:')===0
            || strpos($e->getMessage(),'Resolved path is outside')!==false,
            $label.' wrong error: '.$e->getMessage());
        return;
    }
    throw new \RuntimeException('COMPONENT_PHP_READ_FAILED: '.$label.' not rejected');
}
function componentDelete($path){
    if(!is_dir($path))return;
    foreach(new FilesystemIterator($path) as $item){
        if($item->isDir()&&!$item->isLink())componentDelete($item->getPathname());
        else unlink($item->getPathname());
    }
    rmdir($path);
}
$root=sys_get_temp_dir().'/modxmcp-component-php-'.bin2hex(random_bytes(6));
mkdir($root.'/core/components/demo/sub',0700,true);
mkdir($root.'/assets/components',0700,true);
mkdir($root.'/private',0700,true);
file_put_contents($root.'/core/components/demo/sub/readme.txt','HELLO_WORLD');
file_put_contents($root.'/core/components/demo/blob.dat',"\x00\xFF\x7F");
file_put_contents($root.'/core/components/demo/code.php',"<?php echo 'hello';\n");
file_put_contents($root.'/private/secret.txt','CANNOT_READ_PRIVATE');
$ctx=new ComponentPhpContext(new ComponentPhpModx($root));
$reader=new \ModxMcp\Tools\ComponentFileReadTool();
$checks=0;
try{
    $r=$reader->execute($ctx,array('name'=>'demo','path'=>'sub/readme.txt'));
    componentAssert($r['content']==='HELLO_WORLD'&&$r['encoding']==='utf-8'
        &&$r['size']===11&&!$r['truncated'],'normal text');
    ++$checks;
    $r=$reader->execute($ctx,array('name'=>'demo','path'=>'sub/readme.txt',
        'offset'=>6,'bytes'=>3));
    componentAssert($r['content']==='WOR'&&$r['offset']===6
        &&$r['returned_bytes']===3&&$r['truncated'],'bounded range');
    ++$checks;
    $r=$reader->execute($ctx,array('name'=>'demo','path'=>'sub/readme.txt',
        'offset'=>50));
    componentAssert($r['returned_bytes']===0&&$r['offset']===11
        &&$r['content']===''&&!$r['truncated'],'offset beyond end');
    ++$checks;
    $r=$reader->execute($ctx,array('name'=>'demo','path'=>'blob.dat'));
    componentAssert($r['encoding']==='base64'
        &&base64_decode($r['content'],true)==="\x00\xFF\x7F",'binary bytes');
    ++$checks;
    $r=$reader->execute($ctx,array('name'=>'demo','path'=>'code.php'));
    componentAssert($r['encoding']==='utf-8'
        &&strpos($r['content'],'<?php')===0,'PHP source text');
    ++$checks;
    symlink($root.'/private/secret.txt',$root.'/core/components/demo/symlink.txt');
    componentReject(function()use($reader,$ctx){$reader->execute($ctx,array(
        'name'=>'demo','path'=>'symlink.txt'));},'external file symlink');
    ++$checks;
    symlink($root.'/core/components/demo/sub/readme.txt',
        $root.'/core/components/demo/internal-link.txt');
    componentReject(function()use($reader,$ctx){$reader->execute($ctx,array(
        'name'=>'demo','path'=>'internal-link.txt'));},'internal file symlink');
    ++$checks;
    symlink($root.'/private',$root.'/core/components/demo/escaped');
    componentReject(function()use($reader,$ctx){$reader->execute($ctx,array(
        'name'=>'demo','path'=>'escaped/secret.txt'));},'directory symlink');
    ++$checks;
    // The realpath of an admin-configured component root can itself contain
    // symlinks on ordinary shared hosting. Only symlinks within the
    // requested path *below* the resolved trusted root are rejected.
    symlink($root.'/core/components/demo',$root.'/core/components/linked_demo');
    $r=$reader->execute($ctx,array('name'=>'linked_demo','path'=>'sub/readme.txt'));
    componentAssert($r['content']==='HELLO_WORLD','trusted symlinked root');
    ++$checks;
    $prev=getenv('PATH');
    try{
        putenv('PATH=/no-python-on-shared-host');
        $r=$reader->execute($ctx,array('name'=>'demo','path'=>'sub/readme.txt'));
        componentAssert($r['content']==='HELLO_WORLD','no Python or external program');
        ++$checks;
    }finally {
        if($prev===false)putenv('PATH');else putenv('PATH='.$prev);
    }
    componentReject(function()use($reader,$ctx){$reader->execute($ctx,array(
        'name'=>'demo','path'=>'sub/../../../../private/secret.txt'));},
        'traversal');
    ++$checks;
}finally{componentDelete($root);}
echo 'COMPONENT_PHP_READ_NO_RUNTIME_DEPS_OK tests='.$checks.PHP_EOL;
