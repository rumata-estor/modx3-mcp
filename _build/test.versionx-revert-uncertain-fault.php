<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Проверка VersionX: после вызова штатного revert возможен частичный эффект.
 * EN: VersionX revert must report uncertain outcome after native processor faults.
 */
namespace ModxMcp\Tools {
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/ToolInterface.php';
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/VersionXSupport.php';
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/VersionXVersionRevertTool.php';
    class AuditSupport {
        public static $throw = false;
        public static $calls = 0;
        public static function log($context,$action,$target,$meta) {
            self::$calls++;
            if (self::$throw) throw new \RuntimeException('SECRET_AUDIT_VERSIONX');
        }
    }
}
namespace {
    class ModxMCPClientException extends \RuntimeException {}
    class FakeVxObject {
        private $props;
        public function __construct(array $props) { $this->props=$props; }
        public function get($key) { return $this->props[$key]??null; }
    }
    class FakeVxResponse {
        private $mode;
        public function __construct($mode) { $this->mode=$mode; }
        public function isError() {
            if ($this->mode==='status_throw')
                throw new \RuntimeException('SECRET_STATUS_VERSIONX');
            return $this->mode==='error';
        }
        public function getMessage() { return 'SECRET_VERSIONX_RESPONSE'; }
    }
    class FakeVxPlatform {
        public function className($which) {
            if($which!=='resource')throw new \RuntimeException('wrong content class');
            return 'modResource';
        }
    }
    class FakeVxModx {
        public $mode='success', $processorCalls=0, $sideEffects=0, $readCalls=0;
        public $versionExists=true, $addonExists=true, $changed=false;
        public function getOption($key,$other=null,$fallback=null) {
            return $key==='core_path'?'/tmp/pinned/versionx-ci/':$fallback;
        }
        public function getService($name,$class,$path) {
            return $this->addonExists?new \stdClass():null;
        }
        public function getObject($class,$criteria) {
            if($class==='vxResource') {
                return $this->versionExists?new FakeVxObject(array(
                    'version_id'=>7,'content_id'=>42,'saved'=>'2026-11-22',
                    'user'=>1,'mode'=>'live','marked'=>0,'title'=>'Before',
                    'content'=>'before text',
                )):false;
            }
            if($class==='modResource') {
                $this->readCalls++;
                if($this->mode==='postread_throw'&&$this->processorCalls>0) {
                    throw new \RuntimeException('SECRET_POSTREAD_VERSIONX');
                }
                return new FakeVxObject(array(
                    'id'=>42,'class_key'=>'modDocument','pagetitle'=>'fixture',
                ));
            }
            throw new \RuntimeException('unexpected lookup');
        }
        public function runProcessor($action,array $payload,array $options) {
            if($action!=='resources/revert'
                || $payload!==array('content_id'=>42,'version_id'=>7)
                || empty($options['processors_path'])) {
                throw new \RuntimeException('unexpected VersionX processor args');
            }
            $this->processorCalls++;
            $this->sideEffects++;
            if($this->mode==='throw')throw new \RuntimeException('SECRET_REVERT_EXCEPTION');
            if($this->mode==='no_response')return false;
            return new FakeVxResponse($this->mode);
        }
    }
    class FakeVxContext {
        public $modx,$platform;
        public function __construct() {
            $this->modx=new FakeVxModx();
            $this->platform=new FakeVxPlatform();
        }
        public function modx(){return $this->modx;}
        public function platform(){return $this->platform;}
    }
    function vxAssert($ok,$msg) {
        if(!$ok)throw new \RuntimeException('VERSIONX_UNCERTAIN_E2E_FAIL '.$msg);
    }
    $checks=0;
    $tool=new \ModxMcp\Tools\VersionXVersionRevertTool();
    foreach(array('error','throw','no_response','status_throw','postread_throw','audit_throw') as $mode) {
        $context=new FakeVxContext();
        $context->modx->mode=$mode==='audit_throw'?'success':$mode;
        \ModxMcp\Tools\AuditSupport::$throw=($mode==='audit_throw');
        $error='';
        try {
            $tool->execute($context,array(
                'type'=>'resource','content_id'=>42,
                'version_id'=>7,'confirm'=>true
            ));
        } catch(ModxMCPClientException $e) { $error=$e->getMessage(); }
        vxAssert(strpos($error,'MUTATION_OUTCOME_UNCERTAIN:')===0,
            $mode.' uncertain outcome missing');
        vxAssert(strpos($error,'SECRET_')===false,
            $mode.' raw external error leaked');
        vxAssert($context->modx->processorCalls===1 && $context->modx->sideEffects===1,
            $mode.' native revert retried or never executed');
        $checks++;
    }
    \ModxMcp\Tools\AuditSupport::$throw=false;
    foreach(array('missing_version','missing_addon','no_confirmation') as $mode){
        $context=new FakeVxContext();
        if($mode==='missing_version')$context->modx->versionExists=false;
        if($mode==='missing_addon')$context->modx->addonExists=false;
        $failed=false;
        try {
            $tool->execute($context,array('type'=>'resource','content_id'=>42,
                'version_id'=>7,'confirm'=>$mode!=='no_confirmation'));
        } catch(ModxMCPClientException $e) { $failed=true; }
        vxAssert($failed && $context->modx->processorCalls===0,
            $mode.' did not fail before processor execution');
        ++$checks;
    }
    $context=new FakeVxContext();
    $result=$tool->execute($context,array(
        'type'=>'resource','content_id'=>42,'version_id'=>7,'confirm'=>true
    ));
    vxAssert($result['reverted']===true && $result['content_id']===42
        && $result['before_exists']===true && $result['after_exists']===true
        && $context->modx->processorCalls===1,
        'successful revert result not preserved');
    ++$checks;
    echo 'VERSIONX_REVERT_NATIVE_FAULT_UNCERTAIN_OK checks='.$checks.PHP_EOL;
}
