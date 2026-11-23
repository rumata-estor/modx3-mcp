<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Ошибка чтения static_file не должна превращаться в пустое содержимое.
 * EN: An unreadable static file must never appear to contain an empty string.
 */
namespace ModxMcp\Tools {
    function file_get_contents($filename) {
        if (!empty($GLOBALS['modxmcp_inject_static_read_failure'])) {
            return false;
        }
        return \file_get_contents($filename);
    }
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/FilesystemSupport.php';
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/ElementContentSupport.php';
}
namespace {
class ModxMCPClientException extends \RuntimeException {}
class StaticReadModx {
    private $root;
    public function __construct($root) {$this->root=$root;}
    public function getOption($key) {
        if($key==='core_path')return $this->root.'/core/';
        if($key==='base_path')return $this->root.'/';
        if($key==='assets_path')return $this->root.'/assets/';
        return null;
    }
}
class StaticReadContext {
    private $modx;
    public function __construct($root){$this->modx=new StaticReadModx($root);}
    public function modx(){return $this->modx;}
}
class StaticReadElement {
    public $relative;
    public function __construct($relative){$this->relative=$relative;}
    public function get($key) {
        if($key==='static')return 1;
        if($key==='static_file')return $this->relative;
        if($key==='snippet')return 'DB_FALLBACK_DO_NOT_MISREPORT';
        return '';
    }
}
$root=sys_get_temp_dir().'/modxmcp-static-read-fault-'.bin2hex(random_bytes(6));
mkdir($root.'/core/elements/chunks',0700,true);
$file=$root.'/core/elements/chunks/fixture.tpl';
\file_put_contents($file,'SAFE_STATIC_ORIGINAL');
$ctx=new StaticReadContext($root);
$element=new StaticReadElement('core/elements/chunks/fixture.tpl');
$checks=0;
try {
    $ok=\ModxMcp\Tools\ElementContentSupport::read(
        $ctx,$element,array('field'=>'snippet'));
    if($ok[0]!=='SAFE_STATIC_ORIGINAL'||$ok[1]!==true)
        throw new \RuntimeException('STATIC_READ_VALID_RESULT_CHANGED');
    $checks++;
    $GLOBALS['modxmcp_inject_static_read_failure']=true;
    $error=null;
    try {
        \ModxMcp\Tools\ElementContentSupport::read(
            $ctx,$element,array('field'=>'snippet'));
    }catch(\Throwable $e){$error=$e;}
    if(!($error instanceof ModxMCPClientException)
        || strpos($error->getMessage(),'STATIC_READ_FAILED:')!==0
        || strpos($error->getMessage(),$root)!==false
        || $error->getPrevious()!==null) {
        throw new \RuntimeException('STATIC_READ_FALSE_MUST_DENY_NO_PATH_LEAK');
    }
    $checks++;
    $GLOBALS['modxmcp_inject_static_read_failure']=false;
    \file_put_contents($file,'');
    $empty=\ModxMcp\Tools\ElementContentSupport::read(
        $ctx,$element,array('field'=>'snippet'));
    if($empty[0]!==''||$empty[1]!==true)
        throw new \RuntimeException('STATIC_EMPTY_FILE_MUST_REMAIN_VALID');
    $checks++;
    echo 'STATIC_READ_FAILURE_FAIL_CLOSED_OK checks='.$checks.PHP_EOL;
} finally {
    $GLOBALS['modxmcp_inject_static_read_failure']=false;
    @unlink($file);
    @rmdir($root.'/core/elements/chunks');
    @rmdir($root.'/core/elements');
    @rmdir($root.'/core');
    @rmdir($root);
}
}
