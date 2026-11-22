<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Недоступный безопасный помощник никогда не запускает небезопасный fallback.
 * EN: Unavailable secure helper never falls back to unsafe filesystem writes.
 */
namespace ModxMcp\Tools {
    function proc_open($command,$spec,&$pipes,$cwd=null,$env=null,$opts=array()) {
        return false;
    }
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/StaticFileIoSupport.php';
}
namespace {
class ModxMCPClientException extends \RuntimeException {}
class FailclosedModx {
    public $root;
    public function __construct($root){$this->root=$root;}
    public function getOption($key){
        if($key==='core_path')return $this->root.'/core/';
        return null;
    }
}
class FailclosedContext {
    private $m;
    public function __construct($root){$this->m=new FailclosedModx($root);}
    public function modx(){return $this->m;}
}
$root=sys_get_temp_dir().'/modxmcp-failclosed-'.bin2hex(random_bytes(6));
mkdir($root.'/core/elements/chunks',0700,true);
$file=$root.'/core/elements/chunks/test.tpl';
file_put_contents($file,'UNCHANGED');
try {
    $blocked=false;
    try{\ModxMcp\Tools\StaticFileIoSupport::write(
        new FailclosedContext($root),$file,'DANGEROUS');}
    catch(ModxMCPClientException $e){
        $blocked=strpos($e->getMessage(),'SECURE_STATIC_IO_UNAVAILABLE')===0;
    }
    if(!$blocked || file_get_contents($file)!=='UNCHANGED'){
        throw new \RuntimeException('STATIC_FAILCLOSED_FALLBACK_USED');
    }
    echo 'STATIC_SECURE_FAILCLOSED_OK'.PHP_EOL;
}finally{
    unlink($file);
    rmdir($root.'/core/elements/chunks');
    rmdir($root.'/core/elements');
    rmdir($root.'/core');rmdir($root);
}
}
