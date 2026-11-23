<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Безопасность чтения и записи static_file из сохранённых элементов.
 * EN: Reject persisted static_file paths outside core/elements on read/write.
 */
namespace ModxMcp\Tools {
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/FilesystemSupport.php';
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/StaticFileWriteSupport.php';
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/ElementContentSupport.php';
}
namespace {
class ModxMCPClientException extends \RuntimeException {}
class StaticBoundaryModx {
    public $core;
    public function __construct($path){$this->core=$path;}
    public function getOption($key){
        if($key==='core_path')return $this->core.'/core/';
        if($key==='base_path')return $this->core.'/';
        return null;
    }
}
class StaticBoundaryContext {
    public $m;
    public function __construct($path){$this->m=new StaticBoundaryModx($path);}
    public function modx(){return $this->m;}
}
class StaticBoundaryElement {
    private $path;
    public function __construct($path){$this->path=$path;}
    public function get($key){
        if($key==='static')return true;
        if($key==='static_file')return $this->path;
        return '';
    }
}
$root=sys_get_temp_dir().'/modxmcp-static-boundary-'.bin2hex(random_bytes(6));
mkdir($root.'/core/elements/tvs',0700,true);
mkdir($root.'/private',0700,true);
file_put_contents($root.'/private/token.txt','PRIVATE_DO_NOT_READ');
file_put_contents($root.'/core/elements/tvs/valid.tpl','SAFE');
symlink($root.'/private',$root.'/core/elements/tvs/outlink');
$context=new StaticBoundaryContext($root);
$checks=0;
function staticBoundaryAssert($condition,$message){
    global $checks;
    if(!$condition)throw new \RuntimeException('STATIC_BOUNDARY_FAILED: '.$message);
    $checks++;
}
try {
    $good=new StaticBoundaryElement('core/elements/tvs/valid.tpl');
    $read=\ModxMcp\Tools\ElementContentSupport::read(
        $context,$good,array('field'=>'default_text')
    );
    staticBoundaryAssert($read[0]==='SAFE','valid static TV read');
    $unsafe=array(
        'core/elements/tvs/outlink/token.txt',
        'core/elements/tvs/../../../private/token.txt',
        '../private/token.txt',
    );
    foreach($unsafe as $path){
        $denied=false;
        try {\ModxMcp\Tools\ElementContentSupport::read(
            $context,new StaticBoundaryElement($path),
            array('field'=>'default_text')
        );} catch(ModxMCPClientException $e){$denied=true;}
        staticBoundaryAssert($denied,'unsafe static read '.$path);
    }
    foreach(array(
        $root.'/core/elements/tvs/outlink/new.tpl',
        $root.'/private/new.tpl',
        $root.'/core/elements/tvs/../../../private/new.tpl',
    ) as $path){
        $denied=false;
        try {\ModxMcp\Tools\ElementContentSupport::assertSafeStaticPath(
            $context,$path
        );}catch(ModxMCPClientException $e){$denied=true;}
        staticBoundaryAssert($denied,'unsafe static write '.$path);
    }
    \ModxMcp\Tools\ElementContentSupport::assertSafeStaticPath(
        $context,$root.'/core/elements/tvs/future.tpl'
    );
    staticBoundaryAssert(true,'legitimate new static file accepted');
    // RU: Hard link на внешний файл обходит realpath: запрещаем чтение и запись.
    // EN: A hard link to an outside file bypasses realpath; deny read and write.
    $alias=$root.'/core/elements/tvs/hardlinked.tpl';
    if (!link($root.'/private/token.txt',$alias)) {
        throw new \RuntimeException('Cannot create hard-link security fixture');
    }
    staticBoundaryAssert(realpath($alias)===$alias,
        'hard link must look like a local file to realpath');
    $hardReadDenied=false;
    try {\ModxMcp\Tools\ElementContentSupport::read(
        $context,new StaticBoundaryElement('core/elements/tvs/hardlinked.tpl'),
        array('field'=>'default_text')
    );} catch(ModxMCPClientException $e) {$hardReadDenied=true;}
    staticBoundaryAssert($hardReadDenied,'external-inode hard link read denied');
    $hardWriteDenied=false;
    try {\ModxMcp\Tools\ElementContentSupport::write(
        $context,new StaticBoundaryElement('core/elements/tvs/hardlinked.tpl'),
        'tv',array('field'=>'default_text'),true,$alias,'CORRUPTED'
    );} catch(ModxMCPClientException $e) {$hardWriteDenied=true;}
    staticBoundaryAssert($hardWriteDenied,'external-inode hard link write denied');
    staticBoundaryAssert(file_get_contents($root.'/private/token.txt')==='PRIVATE_DO_NOT_READ',
        'outside secret file unchanged');
    unlink($alias);
    // RU: Даже сам core/elements не может быть симлинком наружу.
    // EN: Reject replacement of the core/elements root with an outside link.
    rename($root.'/core/elements',$root.'/core/elements-safe');
    symlink($root.'/private',$root.'/core/elements');
    $rootSymlinkDenied=false;
    try {\ModxMcp\Tools\ElementContentSupport::assertSafeStaticPath(
        $context,$root.'/core/elements/token.txt'
    );}catch(ModxMCPClientException $e){$rootSymlinkDenied=true;}
    unlink($root.'/core/elements');
    rename($root.'/core/elements-safe',$root.'/core/elements');
    staticBoundaryAssert($rootSymlinkDenied,'external static root symlink rejected');
    echo 'STATIC_ELEMENT_CONTENT_BOUNDARY_OK checks='.$checks.PHP_EOL;
} finally {
    unlink($root.'/core/elements/tvs/outlink');
    unlink($root.'/core/elements/tvs/valid.tpl');
    unlink($root.'/private/token.txt');
    rmdir($root.'/core/elements/tvs');
    rmdir($root.'/core/elements');
    rmdir($root.'/core');
    rmdir($root.'/private');
    rmdir($root);
}
}
