<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Аварийные сценарии make_static: внешние симлинки и штатный успех.
 * EN: make_static blocks external symlink targets for every element type.
 */
namespace ModxMcp\Tools {
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/FilesystemSupport.php';
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/StaticFileWriteSupport.php';
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/ElementContentSupport.php';
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/ElementMutationSupport.php';
}
namespace {
class ModxMCPClientException extends \RuntimeException {}
class MakeStaticFixtureElement {
    public $values=array();
    public $failSave=false;
    public function __construct() {
        $this->values=array('id'=>77,'name'=>'fixture-target','snippet'=>'SAFE',
            'static'=>0,'static_file'=>'','source'=>0);
    }
    public function get($key){return $this->values[$key]??null;}
    public function set($key,$value){$this->values[$key]=$value;}
    public function save(){return !$this->failSave;}
}
class MakeStaticFixtureModx {
    public $base;
    public $element;
    public function __construct($base){
        $this->base=$base;$this->element=new MakeStaticFixtureElement();
    }
    public function getOption($key){
        if($key==='base_path')return $this->base.'/';
        if($key==='core_path')return $this->base.'/core/';
        return null;
    }
    public function getObject($class,$id){
        return $class==='FakeChunk' && (int)$id===77 ? $this->element : null;
    }
}
class MakeStaticFixturePlatform {
    public function className($kind){return $kind==='chunk'?'FakeChunk':null;}
}
class MakeStaticFixtureContext {
    public $modx;
    public function __construct($base){$this->modx=new MakeStaticFixtureModx($base);}
    public function modx(){return $this->modx;}
    public function platform(){return new MakeStaticFixturePlatform();}
}
function mstCheck($condition,$message) {
    if(!$condition)throw new \RuntimeException('MAKE_STATIC_ESCAPE_FAILED: '.$message);
}
$base=sys_get_temp_dir().'/mcp-make-static-boundary-'.bin2hex(random_bytes(5));
mkdir($base.'/core/elements',0700,true);
mkdir($base.'/private',0700,true);
$context=new MakeStaticFixtureContext($base);
try{
    // RU: Подмена каталога chunks симлинком на внешний private.
    // EN: A symlinked chunks directory cannot redirect writes outside.
    symlink($base.'/private',$base.'/core/elements/chunks');
    $denied=false;
    try{\ModxMcp\Tools\ElementMutationSupport::makeStatic(
        $context,'chunk',77
    );}catch(ModxMCPClientException $e){$denied=true;}
    mstCheck($denied,'symlinked chunks directory accepted');
    mstCheck(!file_exists($base.'/private/fixture-target.tpl'),
        'wrote outside static root');
    unlink($base.'/core/elements/chunks');
    $context->modx()->element->values['static']=0;
    $converted=\ModxMcp\Tools\ElementMutationSupport::makeStatic(
        $context,'chunk',77
    );
    mstCheck(($converted['static_file']??'')==='core/elements/chunks/fixture-target.tpl',
        'valid conversion path changed');
    mstCheck(file_get_contents($base.'/core/elements/chunks/fixture-target.tpl')==='SAFE',
        'valid conversion wrote incorrect bytes');
    // RU: Нельзя скрывать неопределённый исход после записи файла.
    // EN: A save failure after a successful filesystem write is uncertain.
    $context->modx()->element->values['name']='save-failed-fixture';
    $context->modx()->element->values['static']=0;
    $context->modx()->element->failSave=true;
    $uncertain=false;
    try{\ModxMcp\Tools\ElementMutationSupport::makeStatic(
        $context,'chunk',77
    );}catch(ModxMCPClientException $e){
        $uncertain=strpos($e->getMessage(),'MUTATION_OUTCOME_UNCERTAIN:')===0;
    }
    mstCheck($uncertain,'failed save did not report uncertain external state');
    mstCheck(file_get_contents($base.'/core/elements/chunks/save-failed-fixture.tpl')==='SAFE',
        'injected save failure did not occur after file write');
    echo 'MAKE_STATIC_SYMLINK_ESCAPE_OK checks=6'.PHP_EOL;
}finally{
    $failureFile=$base.'/core/elements/chunks/save-failed-fixture.tpl';
    if(is_file($failureFile))unlink($failureFile);
    $target=$base.'/core/elements/chunks/fixture-target.tpl';
    if(is_file($target))unlink($target);
    if(is_dir($base.'/core/elements/chunks'))rmdir($base.'/core/elements/chunks');
    rmdir($base.'/core/elements');
    rmdir($base.'/core');
    rmdir($base.'/private');
    rmdir($base);
}
}
