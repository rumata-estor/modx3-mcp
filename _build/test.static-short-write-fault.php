<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Инъекция частичной записи без воздействия на настоящие сайты.
 * EN: Simulate short file writes and require fail-closed uncertain outcomes.
 */
namespace ModxMcp\Tools {
    class StaticFileIoSupport {
        public static function write($context,$file,$content,$createOnly=false) {
            // RU: Авария до атомарной публикации: целевой файл не меняется.
            // EN: Partial bytes are written only to a disposable temp inode.
            $temp=dirname($file).'/.modxmcp-injected-short.tmp';
            \file_put_contents($temp,substr((string)$content,0,2));
            \unlink($temp);
            throw new \ModxMCPClientException(
                'MUTATION_OUTCOME_UNCERTAIN: injected short descriptor write.'
            );
        }
    }
    class ElementSupport {
        public static function processorBase($type){return 'element/';}
    }
    class ProcessorSupport {
        public static function error($response){return 'unexpected';}
    }
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/FilesystemSupport.php';
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/ElementContentSupport.php';
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/ElementMutationSupport.php';
}
namespace {
class ModxMCPClientException extends \RuntimeException {}
class ShortWriteElement {
    public $saveCalls=0;
    public function get($key) {
        return array('name'=>'shortwrite','snippet'=>'12345678','static'=>0)[$key]??null;
    }
    public function toArray(){return array('id'=>1,'snippet'=>'old');}
    public function save(){$this->saveCalls++;return true;}
}
class ShortWriteModx {
    public $root;public $element;
    public function __construct($root){$this->root=$root;$this->element=new ShortWriteElement();}
    public function getOption($key){
        if($key==='core_path')return $this->root.'/core/';
        if($key==='base_path')return $this->root.'/';
        return null;
    }
    public function getObject($class,$id) {
        return $class==='FakeChunk' && $id===1?$this->element:null;
    }
}
class ShortWritePlatform {
    public $processorCalls=0;
    public function className($type){return $type==='chunk'?'FakeChunk':null;}
    public function runProcessor($modx,$route,$args){
        $this->processorCalls++;throw new \RuntimeException('processor should never run');
    }
}
class ShortWriteContext {
    public $m;public $p;
    public function __construct($root){
        $this->m=new ShortWriteModx($root);$this->p=new ShortWritePlatform();
    }
    public function modx(){return $this->m;}
    public function platform(){return $this->p;}
}
$root=sys_get_temp_dir().'/modxmcp-short-write-'.bin2hex(random_bytes(6));
mkdir($root.'/core/elements/chunks',0700,true);
$ctx=new ShortWriteContext($root);
$checks=0;
try {
    // RU: Недописанный временный файл не публикуется и не сохраняет метаданные.
    // EN: A partial temp file must not be published or save metadata.
    $uncertain=false;
    try {
        \ModxMcp\Tools\ElementMutationSupport::makeStatic($ctx,'chunk',1);
    }catch(ModxMCPClientException $e){
        $uncertain=strpos($e->getMessage(),'MUTATION_OUTCOME_UNCERTAIN:')===0;
    }
    $target=$root.'/core/elements/chunks/shortwrite.tpl';
    if(!$uncertain || is_file($target) || $ctx->m->element->saveCalls!==0) {
        throw new \RuntimeException('MAKE_STATIC_SHORT_WRITE_NOT_DETECTED');
    }
    $checks++;
    \file_put_contents($target,'ORIGINAL');
    // RU: edit_element_lines/replace_across не вызывают процессор после
    // RU: неполной файловой записи.
    // EN: Static content edits must not dispatch a MODX processor after short writes.
    $uncertain=false;
    try {
        \ModxMcp\Tools\ElementContentSupport::write(
            $ctx,$ctx->m->element,'chunk',array('field'=>'snippet'),
            true,$target,'NEWCONTENT'
        );
    }catch(ModxMCPClientException $e){
        $uncertain=strpos($e->getMessage(),'MUTATION_OUTCOME_UNCERTAIN:')===0;
    }
    if(!$uncertain || \file_get_contents($target)!=='ORIGINAL'
        || $ctx->p->processorCalls!==0) {
        throw new \RuntimeException('STATIC_EDIT_SHORT_WRITE_NOT_DETECTED');
    }
    $checks++;
    echo 'STATIC_SHORT_WRITE_FAULT_OK checks='.$checks.PHP_EOL;
} finally {
    $target=$root.'/core/elements/chunks/shortwrite.tpl';
    if(is_file($target))unlink($target);
    rmdir($root.'/core/elements/chunks');
    rmdir($root.'/core/elements');
    rmdir($root.'/core');
    rmdir($root);
}
}
