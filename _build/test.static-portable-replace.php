<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Атомарная PHP-замена, права, симлинки, hard links и сбои.
 * EN: Portable PHP atomic replacement, modes, links and failure safety.
 */
namespace ModxMcp\Tools {
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/FilesystemSupport.php';
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/ElementContentSupport.php';
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/StaticFileWriteSupport.php';
}
namespace {
class ModxMCPClientException extends \RuntimeException {}
class PortableModx {
    private $dir;
    public function __construct($dir){$this->dir=$dir;}
    public function getOption($key) {
        if ($key==='core_path')return $this->dir.'/core/';
        if ($key==='base_path')return $this->dir.'/';
        return null;
    }
}
class PortableContext {
    private $modx;
    public function __construct($dir){$this->modx=new PortableModx($dir);}
    public function modx(){return $this->modx;}
}
function pcheck($condition,$what) {
    if(!$condition)throw new \RuntimeException('PORTABLE_STATIC_REPLACE_FAIL '.$what);
}
function denied($callback,$what) {
    $ok=false;
    try {call_user_func($callback);} catch(ModxMCPClientException $e){$ok=true;}
    pcheck($ok,$what);
}
$root=sys_get_temp_dir().'/modxmcp-portable-'.bin2hex(random_bytes(6));
mkdir($root.'/core/elements/chunks',0700,true);
mkdir($root.'/outside',0700,true);
$dir=$root.'/core/elements/chunks';
$target=$dir.'/test.tpl';
$outside=$root.'/outside/secret.tpl';
file_put_contents($outside,'PRIVATE_UNCHANGED');
$ctx=new PortableContext($root);
try {
    pcheck(\ModxMcp\Tools\StaticFileWriteSupport::replace($ctx,$target,'HELLO')===5,'create');
    pcheck(file_get_contents($target)==='HELLO','readback');
    chmod($target,0640);
    $binary="A\0B\n".str_repeat('Z',250000);
    pcheck(\ModxMcp\Tools\StaticFileWriteSupport::replace($ctx,$target,$binary)===strlen($binary),'binary write');
    pcheck(file_get_contents($target)===$binary,'binary readback');
    pcheck((fileperms($target)&0777)===0640,'existing mode retained');
    // RU: Права каталога не должны разрешать обход read-only файла.
    // EN: Being able to rename inside a directory cannot bypass file ACL.
    chmod($target,0444);
    denied(function()use($ctx,$target){
        \ModxMcp\Tools\StaticFileWriteSupport::replace($ctx,$target,'UNAUTHORIZED');
    },'read-only file must reject even when directory writable');
    pcheck(file_get_contents($target)===$binary,'read-only bytes intact');
    chmod($target,0640);
    denied(function()use($ctx,$target){
        \ModxMcp\Tools\StaticFileWriteSupport::replace($ctx,$target,'FORBIDDEN',true);
    },'exclusive must refuse existing target');
    pcheck(file_get_contents($target)===$binary,'exclusive kept old bytes');
    unlink($target);
    symlink($outside,$target);
    denied(function()use($ctx,$target){
        \ModxMcp\Tools\StaticFileWriteSupport::replace($ctx,$target,'ATTACK');
    },'symlink denied');
    pcheck(file_get_contents($outside)==='PRIVATE_UNCHANGED','symlink external intact');
    unlink($target);
    link($outside,$target);
    denied(function()use($ctx,$target){
        \ModxMcp\Tools\StaticFileWriteSupport::replace($ctx,$target,'ATTACK');
    },'hardlink denied');
    pcheck(file_get_contents($outside)==='PRIVATE_UNCHANGED','hardlink external intact');
    unlink($target);
    symlink($root.'/outside',$root.'/core/elements/outlink');
    denied(function()use($ctx,$root){
        \ModxMcp\Tools\StaticFileWriteSupport::replace(
            $ctx,$root.'/core/elements/outlink/secret.tpl','ATTACK');
    },'parent symlink denied');
    pcheck(file_get_contents($outside)==='PRIVATE_UNCHANGED','parent symlink external intact');
    unlink($root.'/core/elements/outlink');
    // RU: Ограниченный хостинг: каталог только для чтения, файл доступен.
    // EN: Restricted host: existing file writable, directory not writable.
    file_put_contents($target,'LEGACY_OLD');
    chmod($target,0666);
    chmod($dir,0555);
    try {
        pcheck(!is_writable($dir) && is_writable($target),
            'restricted hosting fixture');
        $updated=\ModxMcp\Tools\StaticFileWriteSupport::replace(
            $ctx,$target,'LEGACY_NEW'
        );
        pcheck($updated===10 && file_get_contents($target)==='LEGACY_NEW',
            'restricted host legacy compatibility');
    } finally {
        chmod($dir,0700);
    }
    pcheck(count(glob($dir.'/.modxmcp-*.tmp'))===0,'no temp leftovers');
    echo 'STATIC_PORTABLE_REPLACE_OK checks=16'.PHP_EOL;
} finally {
    if(is_link($target)||is_file($target))unlink($target);
    if(is_link($root.'/core/elements/outlink'))unlink($root.'/core/elements/outlink');
    if(is_file($outside))unlink($outside);
    foreach(glob($dir.'/.modxmcp-*.tmp') as $tmp)unlink($tmp);
    rmdir($dir);rmdir($root.'/core/elements');rmdir($root.'/core');
    rmdir($root.'/outside');rmdir($root);
}
}
