<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Неопределённый итог обновления заказа miniShop2 после частичного эффекта.
 * EN: miniShop2 order update must fail closed after partial native effects.
 */
namespace ModxMcp\Tools {
    interface ToolInterface {
        public function name();
        public function group();
        public function isMutation();
        public function supports($context);
        public function execute($context, array $data);
    }
    class AuditSupport {
        public static $calls = 0;
        public static $throw = false;
        public static $lastPayload = array();
        public static function log($context, $action, $type, $payload) {
            self::$calls++;
            self::$lastPayload = $payload;
            if (self::$throw) {
                throw new \RuntimeException('SECRET_AUDIT_EXCEPTION');
            }
        }
    }
    require_once __DIR__ . '/../core/components/modxmcp/src/Tools/ProcessorSupport.php';
    require_once __DIR__ . '/../core/components/modxmcp/src/Tools/MiniShop2Support.php';
    require_once __DIR__ . '/../core/components/modxmcp/src/Tools/MiniShop2MutationSupport.php';
    require_once __DIR__ . '/../core/components/modxmcp/src/Tools/Ms2OrderUpdateTool.php';
}
namespace {
    class ModxMCPClientException extends \RuntimeException {}
    class FakeMiniShop2 {
        public $initializations = 0;
        public function initialize($key) {
            $this->initializations++;
            return true;
        }
    }
    class FakeMs2Response {
        private $failure;
        private $statusThrow;
        private $responseThrow;
        public function __construct($failure, $statusThrow, $responseThrow) {
            $this->failure = $failure;
            $this->statusThrow = $statusThrow;
            $this->responseThrow = $responseThrow;
        }
        public function isError() {
            if ($this->statusThrow) {
                throw new \RuntimeException('SECRET_RESPONSE_EXCEPTION');
            }
            return $this->failure;
        }
        public function getMessage() { return 'SECRET_NATIVE_RESPONSE'; }
        public function hasFieldErrors() { return false; }
        public function getResponse() {
            if ($this->responseThrow) {
                throw new \RuntimeException('SECRET_RESPONSE_DECODING');
            }
            return json_encode(array('success'=>true,'object'=>array('id'=>42,'status'=>2)));
        }
        public function getObject() { return array('id'=>42,'status'=>2); }
    }
    class FakeMs2Modx {
        public $context;
        public $service;
        public $calls = 0;
        public $sideEffects = 0;
        public $mode = 'error';
        public $lastProps = array();
        public $addonAvailable = true;
        public function __construct() {
            $this->context = new \stdClass();
            $this->context->key = 'mgr';
            $this->service = new FakeMiniShop2();
        }
        public function getOption($key, $options = null, $fallback = null) {
            return $key === 'core_path' ? '/tmp/disposable/core/' : $fallback;
        }
        public function getService($key, $className, $path) {
            return $this->addonAvailable ? $this->service : null;
        }
        public function runProcessor($action, array $payload, array $options) {
            if ($action !== 'mgr/orders/update'
                || empty($options['processors_path'])) {
                throw new \RuntimeException('WRONG_PROCESSOR_OR_PATH');
            }
            $this->calls++;
            $this->lastProps = $payload;
            // The native processor may have changed state before failure.
            $this->sideEffects++;
            if ($this->mode === 'throw') {
                throw new \RuntimeException('SECRET_RUNTIME_EXCEPTION');
            }
            if ($this->mode === 'no_response') { return false; }
            return new FakeMs2Response(
                $this->mode === 'error',
                $this->mode === 'status_throw',
                $this->mode === 'decode_throw'
            );
        }
    }
    class FakeMs2Context {
        public $modx;
        public function __construct() { $this->modx = new FakeMs2Modx(); }
        public function modx() { return $this->modx; }
    }
    function ms2OrderAssert($condition, $msg) {
        if (!$condition) {
            throw new \RuntimeException('MS2_ORDER_UNCERTAIN_TEST_FAILED ' . $msg);
        }
    }
    $checks=0;
    $tool = new \ModxMcp\Tools\Ms2OrderUpdateTool();
    foreach (array('error','throw','no_response','status_throw','decode_throw','audit_throw') as $mode) {
        $context = new FakeMs2Context();
        $context->modx->mode = $mode === 'audit_throw' ? 'success' : $mode;
        \ModxMcp\Tools\AuditSupport::$throw = ($mode === 'audit_throw');
        $caught = '';
        try {
            $tool->execute($context, array(
                'id'=>42,'status'=>2,'delivery'=>3,'payment'=>4,
            ));
        } catch (ModxMCPClientException $e) {
            $caught = $e->getMessage();
        }
        ms2OrderAssert(strpos($caught, 'MUTATION_OUTCOME_UNCERTAIN:') === 0,
            $mode . ': untrusted native error not marked unknown');
        ms2OrderAssert(strpos($caught, 'SECRET_') === false,
            $mode . ': secret exception leaked');
        ms2OrderAssert($context->modx->calls === 1
            && $context->modx->sideEffects === 1,
            $mode . ': mutation was retried or not exercised');
        ms2OrderAssert($context->modx->lastProps['id'] === 42,
            $mode . ': ID modified unexpectedly');
        ++$checks;
    }
    \ModxMcp\Tools\AuditSupport::$throw = false;
    $missing = new FakeMs2Context();
    $missing->modx->addonAvailable = false;
    $blocked = '';
    try { $tool->execute($missing, array('id'=>42)); }
    catch (ModxMCPClientException $e) { $blocked=$e->getMessage(); }
    ms2OrderAssert(strpos($blocked, 'Could not load miniShop2 service') !== false
        && strpos($blocked, 'MUTATION_OUTCOME_UNCERTAIN') === false,
        'missing addon misclassified as post-write uncertainty');
    ms2OrderAssert($missing->modx->sideEffects === 0, 'missing addon mutated order');
    ++$checks;

    $missingId = new FakeMs2Context();
    $blocked = '';
    try { $tool->execute($missingId, array('status'=>2)); }
    catch (ModxMCPClientException $e) { $blocked=$e->getMessage(); }
    ms2OrderAssert(strpos($blocked, 'id is required') !== false
        && $missingId->modx->sideEffects === 0,
        'missing ID not refused before native processor');
    ++$checks;

    $success = new FakeMs2Context();
    $success->modx->mode = 'success';
    $result = $tool->execute($success, array(
        'id'=>'42','status'=>2,'delivery'=>3,'payment'=>4,'newpassword'=>'NEVER_AUDIT'
    ));
    ms2OrderAssert((int)$result['id']===42
        && $success->modx->sideEffects === 1
        && $success->modx->calls === 1,
        'success result or count wrong');
    ms2OrderAssert(\ModxMcp\Tools\AuditSupport::$lastPayload
        === array('id'=>42,'status'=>2,'delivery'=>3,'payment'=>4),
        'audit must contain only non-secret order fields');
    ++$checks;
    echo 'MS2_ORDER_PARTIAL_OUTCOME_FAIL_CLOSED_OK checks=' . $checks . PHP_EOL;
}
