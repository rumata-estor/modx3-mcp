<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Аварийный отказ процессора после записи статического файла.
 * EN: Fault injection: static file changes before MODX processor fails.
 */
namespace ModxMcp\Tools {
    class ElementMutationSupport {
        public static function filterData($type,array $data){return $data;}
    }
    class ElementSupport {
        public static function processorBase($type){return 'element/';}
    }
    class ProcessorSupport {
        public static function error($response){return 'injected processor error';}
    }
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/FilesystemSupport.php';
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/ElementContentSupport.php';
}
namespace {
class ModxMCPClientException extends \RuntimeException {}
class FaultModx {
    public $root;
    public function __construct($root){$this->root=$root;}
    public function getOption($key) {
        if($key==='core_path')return $this->root.'/core/';
        return null;
    }
}
class FaultResponse {public function isError(){return true;}}
class FaultPlatform {
    public $calls=0;
    public function runProcessor($modx,$route,$properties) {
        $this->calls++;return new FaultResponse();
    }
}
class FaultContext {
    public $modx; public $platform;
    public function __construct($root) {
        $this->modx=new FaultModx($root);
        $this->platform=new FaultPlatform();
    }
    public function modx(){return $this->modx;}
    public function platform(){return $this->platform;}
}
class FaultElement {
    public function toArray(){return array('id'=>47,'snippet'=>'OLD');}
}
$root=sys_get_temp_dir().'/modxmcp-content-fault-'.bin2hex(random_bytes(6));
mkdir($root.'/core/elements/chunks',0700,true);
$absolute=$root.'/core/elements/chunks/fixture.tpl';
file_put_contents($absolute,'OLD');
$ctx=new FaultContext($root);
$checks=0;
try {
    // RU: Ошибка процессора после записи файла — исход НЕ определён.
    // EN: Failed processor after changing file must mark uncertain outcome.
    $uncertain=false;
    try {
        \ModxMcp\Tools\ElementContentSupport::write(
            $ctx,new FaultElement(),'chunk',array('field'=>'snippet'),
            true,$absolute,'NEW'
        );
    } catch(ModxMCPClientException $e) {
        $uncertain=strpos($e->getMessage(),'MUTATION_OUTCOME_UNCERTAIN:')===0;
    }
    if(!$uncertain || file_get_contents($absolute)!=='NEW'
        || $ctx->platform->calls!==1) {
        throw new \RuntimeException('STATIC_EDIT_FAILURE_NOT_UNCERTAIN');
    }
    $checks++;
    // RU: Без файлового побочного эффекта остаётся обычная ошибка.
    // EN: Nonstatic processor failures must not report a file write.
    $ordinary=false;
    try {
        \ModxMcp\Tools\ElementContentSupport::write(
            $ctx,new FaultElement(),'chunk',array('field'=>'snippet'),
            false,null,'DB_ONLY'
        );
    } catch(ModxMCPClientException $e) {
        $ordinary=strpos($e->getMessage(),'element save failed:')===0;
    }
    if(!$ordinary || $ctx->platform->calls!==2) {
        throw new \RuntimeException('NONSTATIC_ERROR_CONTRACT_CHANGED');
    }
    $checks++;
    echo 'STATIC_CONTENT_POSTWRITE_FAULT_OK checks='.$checks.PHP_EOL;
} finally {
    unlink($absolute);
    rmdir($root.'/core/elements/chunks');
    rmdir($root.'/core/elements');
    rmdir($root.'/core');
    rmdir($root);
}
}
