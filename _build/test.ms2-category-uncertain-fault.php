<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Категории miniShop2: сторонний процессор может завершиться после записи.
 * EN: Native miniShop2 category faults may follow side effects; report uncertain.
 */
namespace ModxMcp\Tools {
    class ElementMutationSupport {
        public static $fail=false, $refreshes=0;
        public static function refreshCache($context){
            self::$refreshes++;
            if(self::$fail)throw new \RuntimeException('SECRET_CACHE_NATIVE');
        }
    }
    class AuditSupport {
        public static $fail=false, $events=0;
        public static function log($context,$action,$group,$values){
            self::$events++;
            if(self::$fail)throw new \RuntimeException('SECRET_AUDIT_NATIVE');
        }
    }
    class ProcessorSupport {
        public static $fail=false;
        public static function normalize($response){
            if(self::$fail)throw new \RuntimeException('SECRET_RESPONSE_DECODE');
            return array('id'=>123,'success'=>true);
        }
    }
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/MiniShop2Support.php';
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/MiniShop2MutationSupport.php';
}
namespace {
    class ModxMCPClientException extends \RuntimeException {}
    class FakeCategoryResponse {
        private $mode;
        public function __construct($mode){$this->mode=$mode;}
        public function isError(){
            if($this->mode==='status_throw')throw new \RuntimeException('SECRET_STATUS');
            return $this->mode==='error';
        }
    }
    class FakeCategoryPlatform {
        public $mode='success', $calls=0, $sideEffects=0, $lastProps=array();
        public function runProcessor($modx,$name,$props){
            if(!in_array($name,array('resource/create','resource/update'),true))
                throw new \RuntimeException('WRONG_RESOURCE_PROCESSOR');
            $this->calls++;
            $this->lastProps=$props;
            $this->sideEffects++;
            if($this->mode==='throw')throw new \RuntimeException('SECRET_NATIVE_THROW');
            if($this->mode==='no_response')return false;
            return new FakeCategoryResponse($this->mode);
        }
    }
    class FakeCategoryContext {
        public $platform;
        public function __construct(){$this->platform=new FakeCategoryPlatform();}
        public function platform(){return $this->platform;}
        public function modx(){return $this;}
    }
    function catFaultAssert($ok,$reason) {
        if(!$ok)throw new \RuntimeException('MS2_CATEGORY_FAULT_FAILED '.$reason);
    }
    $checks=0;
    foreach(array('create','update') as $action){
        foreach(array('error','throw','no_response','status_throw','cache_throw','audit_throw','decode_throw') as $mode){
            $ctx=new FakeCategoryContext();
            $ctx->platform->mode=substr($mode,-6)==='_throw'
                && in_array($mode,array('cache_throw','audit_throw','decode_throw'),true)
                ? 'success' : $mode;
            \ModxMcp\Tools\ElementMutationSupport::$fail=$mode==='cache_throw';
            \ModxMcp\Tools\AuditSupport::$fail=$mode==='audit_throw';
            \ModxMcp\Tools\ProcessorSupport::$fail=$mode==='decode_throw';
            $error='';
            try{\ModxMcp\Tools\MiniShop2MutationSupport::category($ctx,$action,
                array('id'=>123,'pagetitle'=>'fixture','published'=>0));}
            catch(ModxMCPClientException $e){$error=$e->getMessage();}
            catFaultAssert(strpos($error,'MUTATION_OUTCOME_UNCERTAIN:')===0,
                $action.'/'.$mode.' must not pretend native write rolled back');
            catFaultAssert(strpos($error,'SECRET_')===false,
                $action.'/'.$mode.' raw error leak');
            catFaultAssert($ctx->platform->calls===1 && $ctx->platform->sideEffects===1,
                $action.'/'.$mode.' native processor replayed');
            catFaultAssert(($ctx->platform->lastProps['class_key']??'')==='msCategory'
                &&(int)$ctx->platform->lastProps['published']===0,
                $action.'/'.$mode.' unsafe class or publication');
            ++$checks;
        }
        \ModxMcp\Tools\ElementMutationSupport::$fail=false;
        \ModxMcp\Tools\AuditSupport::$fail=false;
        \ModxMcp\Tools\ProcessorSupport::$fail=false;
        $ctx=new FakeCategoryContext();
        $success=\ModxMcp\Tools\MiniShop2MutationSupport::category($ctx,$action,
            array('id'=>123,'pagetitle'=>'fixture','published'=>0));
        catFaultAssert(($success['id']??null)===123 && $ctx->platform->calls===1,
            $action.' success must still be reported');
        ++$checks;
    }
    echo 'MS2_CATEGORY_POSTWRITE_FAULT_UNCERTAIN_OK checks='.$checks.PHP_EOL;
}
