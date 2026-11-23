<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Настоящий PHP→Python защищённый дескрипторный ввод-вывод.
 * EN: Real PHP-to-Python descriptor-safe static file I/O integration.
 */
namespace ModxMcp\Tools {
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/FilesystemSupport.php';
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/ElementContentSupport.php';
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/StaticFileWriteSupport.php';
}
namespace {
class ModxMCPClientException extends \RuntimeException {}
class BridgeModx {
    public $root;
    public function __construct($root){$this->root=$root;}
    public function getOption($key){
        if ($key==='core_path')return $this->root.'/core/';
        if ($key==='base_path')return $this->root.'/';
        if ($key==='modxmcp.static_io_mode')return 'strict';
        return null;
    }
}
class BridgeContext {
    public $m;
    public function __construct($root){$this->m=new BridgeModx($root);}
    public function modx(){return $this->m;}
}
function bridgeCheck($cond,$msg){
    if(!$cond)throw new \RuntimeException('STATIC_IO_BRIDGE_FAIL '.$msg);
}
$root=sys_get_temp_dir().'/modxmcp-static-bridge-'.bin2hex(random_bytes(6));
mkdir($root.'/core/elements/chunks',0700,true);
mkdir($root.'/outside',0700,true);
$ctx=new BridgeContext($root);
$target=$root.'/core/elements/chunks/x.tpl';
$outside=$root.'/outside/secret.tpl';
file_put_contents($outside,'DO_NOT_TOUCH');
try {
    $w=\ModxMcp\Tools\StaticFileWriteSupport::replace($ctx,$target,'SAFE',true);
    bridgeCheck($w===4,'exclusive create');
    bridgeCheck(\ModxMcp\Tools\StaticFileWriteSupport::read($ctx,$target)==='SAFE','read');
    $w=\ModxMcp\Tools\StaticFileWriteSupport::replace($ctx,$target,'UPDATED',false);
    bridgeCheck($w===7 && file_get_contents($target)==='UPDATED','update');
    $blocked=false;
    try{\ModxMcp\Tools\StaticFileWriteSupport::replace($ctx,$target,'INVALID',true);}
    catch(ModxMCPClientException $e){$blocked=true;}
    bridgeCheck($blocked && file_get_contents($target)==='UPDATED','exclusive failure unchanged');
    symlink($root.'/outside',$root.'/core/elements/outlink');
    $blocked=false;
    try{\ModxMcp\Tools\StaticFileWriteSupport::replace(
        $ctx,$root.'/core/elements/outlink/secret.tpl','INVALID');}
    catch(ModxMCPClientException $e){$blocked=true;}
    bridgeCheck($blocked && file_get_contents($outside)==='DO_NOT_TOUCH','symlink blocked');
    link($outside,$root.'/core/elements/chunks/hard.tpl');
    $blocked=false;
    try{\ModxMcp\Tools\StaticFileWriteSupport::read(
        $ctx,$root.'/core/elements/chunks/hard.tpl');}
    catch(ModxMCPClientException $e){$blocked=true;}
    bridgeCheck($blocked,'hardlink blocked');
    bridgeCheck(file_get_contents($outside)==='DO_NOT_TOUCH','outside unchanged');
    echo 'STATIC_SECURE_PHP_BRIDGE_OK checks=7'.PHP_EOL;
} finally {
    unlink($root.'/core/elements/chunks/hard.tpl');
    unlink($root.'/core/elements/outlink');
    unlink($target);unlink($outside);
    rmdir($root.'/core/elements/chunks');rmdir($root.'/core/elements');
    rmdir($root.'/core');rmdir($root.'/outside');rmdir($root);
}
}
