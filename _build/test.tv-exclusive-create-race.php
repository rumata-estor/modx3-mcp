<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Проверка гонки на конечный TV-файл при make_static.
 * EN: Inject a competing TV target creation at exclusive-open time.
 */
namespace ModxMcp\Tools {
    function link($temporary,$target) {
        if (!empty($GLOBALS['inject_tv_collision'])) {
            // RU: Конкурент занимает путь перед атомарной публикацией link().
            // EN: Competitor occupies the destination just before atomic link.
            \file_put_contents($target,'OTHER_OWNER');
        }
        return \link($temporary,$target);
    }
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/FilesystemSupport.php';
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/StaticFileWriteSupport.php';
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/ElementContentSupport.php';
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/ElementMutationSupport.php';
}
namespace {
class ModxMCPClientException extends \RuntimeException {}
class ExclusiveTV {
    public $saves=0;
    public function get($name) {
        $fields=array('name'=>'exclusive-fixture','default_text'=>'OWN_TEXT',
            'static'=>0,'source'=>0);
        return $fields[$name]??null;
    }
    public function set($key,$value){}
    public function save(){$this->saves++;return true;}
}
class ExclusiveModx {
    public $root;public $tv;
    public function __construct($root){$this->root=$root;$this->tv=new ExclusiveTV();}
    public function getOption($name) {
        if($name==='core_path')return $this->root.'/core/';
        if($name==='base_path')return $this->root.'/';
        return null;
    }
    public function getObject($class,$id){
        return $class==='FakeTV' && (int)$id===9 ? $this->tv : null;
    }
}
class ExclusivePlatform {
    public function className($type){return $type==='tv'?'FakeTV':null;}
}
class ExclusiveContext {
    public $m;public $p;
    public function __construct($root){
        $this->m=new ExclusiveModx($root);$this->p=new ExclusivePlatform();
    }
    public function modx(){return $this->m;}
    public function platform(){return $this->p;}
}
$root=sys_get_temp_dir().'/modxmcp-tv-exclusive-'.bin2hex(random_bytes(6));
mkdir($root.'/core/elements/tvs',0700,true);
$ctx=new ExclusiveContext($root);
$target=$root.'/core/elements/tvs/exclusive-fixture.tpl';
try {
    $GLOBALS['inject_tv_collision']=true;
    $refused=false;
    try {\ModxMcp\Tools\ElementMutationSupport::makeStatic($ctx,'tv',9);}
    catch(ModxMCPClientException $e) {
        $refused=strpos($e->getMessage(),'exclusive creation failed')!==false;
    }
    if (!$refused || \file_get_contents($target)!=='OTHER_OWNER'
        || $ctx->m->tv->saves!==0) {
        throw new \RuntimeException('TV_EXCLUSIVE_RACE_OVERWROTE_COMPETITOR');
    }
    // RU: Если цель свободна, эксклюзивное создание должно работать.
    // EN: The same route must succeed when no competing writer exists.
    unlink($target);
    $GLOBALS['inject_tv_collision']=false;
    $result=\ModxMcp\Tools\ElementMutationSupport::makeStatic($ctx,'tv',9);
    if(($result['static_file']??'')!=='core/elements/tvs/exclusive-fixture.tpl'
        || \file_get_contents($target)!=='OWN_TEXT'
        || $ctx->m->tv->saves!==1) {
        throw new \RuntimeException('TV_EXCLUSIVE_NORMAL_CREATION_FAILED');
    }
    echo 'TV_EXCLUSIVE_CREATE_RACE_OK checks=2'.PHP_EOL;
} finally {
    if(is_file($target))unlink($target);
    rmdir($root.'/core/elements/tvs');
    rmdir($root.'/core/elements');
    rmdir($root.'/core');
    rmdir($root);
}
}
