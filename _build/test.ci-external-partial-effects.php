<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Одноразовая интеграция: реальные файловые эффекты до аварии внешнего процессора.
 * EN: Disposable integration: real filesystem side effects before external processor failure.
 */
/**
 * This exercises installed MODX MCP tool classes with genuine filesystem
 * readback but intentionally instrumented processor/package dispatch.
 * It does NOT claim a real third-party transport installer was executed.
 */
if (PHP_SAPI !== 'cli' || getenv('MODXMCP_DISPOSABLE_CI') !== '1'
    || strpos((string)getenv('MODX_CONFIG_CORE'), '/home/runner/') !== 0
    || strpos((string)getenv('RUNNER_TEMP'), '/home/runner/') !== 0
    || !is_file((string)getenv('MODX_CONFIG_CORE'))) {
    fwrite(STDERR, "Refusing external-effects test outside disposable CI.\n");
    exit(3);
}
require_once getenv('MODX_CONFIG_CORE');
$is2 = !is_file(MODX_CORE_PATH . 'src/Revolution/modX.php');
if ($is2) {
    require_once MODX_CORE_PATH . 'model/modx/modx.class.php';
    $modx = new modX();
} else {
    require_once MODX_CORE_PATH . 'vendor/autoload.php';
    $modx = \MODX\Revolution\modX::getInstance();
}
$modx->initialize('mgr');
require_once MODX_CORE_PATH . 'components/modxmcp/model/modxmcp.class.php';
new modxMCP($modx);

class DisposableEffectResponse {
    private $data;
    public function __construct(array $data) { $this->data=$data; }
    public function isError() {return false;}
    public function getResponse() {return $this->data;}
    public function getObject() {return $this->data;}
}
class DisposableEffectPlatform {
    public $events=array();
    public $mode='throw';
    public $root;
    public $packageName;
    public $signature;
    public function __construct($root,$packageName,$signature) {
        $this->root=$root; $this->packageName=$packageName; $this->signature=$signature;
    }
    public function className($key) {
        return $key==='transport_package' ?
            (class_exists('MODX\\Revolution\\Transport\\modTransportPackage')
                ? 'MODX\\Revolution\\Transport\\modTransportPackage' : 'modTransportPackage') : $key;
    }
    public function processorTarget($name){return $name;}
    public function runProcessor($modx,$name,$props) {
        $this->events[]=$name;
        if ($name==='workspace/packages/rest/getlist') return new DisposableEffectResponse(array(
            'results'=>array(array('name'=>$this->packageName,
                'signature'=>$this->signature,'location'=>'https://example.invalid/fixture'))
        ));
        if ($name==='workspace/packages/rest/download') {
            if ($this->mode==='install') return new DisposableEffectResponse(array('signature'=>$this->signature));
            file_put_contents($this->root.'/download.part','partial-archive-bytes');
            throw new \RuntimeException('token=FAKE_SECRET_DOWNLOAD');
        }
        if ($name==='workspace/packages/install') {
            file_put_contents($this->root.'/installed-file','installed-partial-file');
            throw new \RuntimeException('token=FAKE_SECRET_INSTALL');
        }
        if ($name==='workspace/packages/uninstall') {
            file_put_contents($this->root.'/removed-file','removed-partial-file');
            throw new \RuntimeException('token=FAKE_SECRET_UNINSTALL');
        }
        if ($name==='resource/getlist') {
            file_put_contents($this->root.'/processor-effect','processor-partial-file');
            if ($this->mode==='silent') return false;
            if ($this->mode==='reported') return new class {
                public function isError(){return true;}
                public function getMessage(){return 'password=FAKE_SECRET_PROCESSOR';}
            };
            throw new \RuntimeException('password=FAKE_SECRET_PROCESSOR');
        }
        throw new \RuntimeException('Unexpected dispatch: '.$name);
    }
}
class DisposableEffectContext {
    private $modx; private $platform;
    public function __construct($modx,$platform) { $this->modx=$modx; $this->platform=$platform; }
    public function modx(){return $this->modx;}
    public function platform(){return $this->platform;}
}
function effectCheck($condition,$label) {
    if (!$condition) throw new \RuntimeException('PARTIAL_EFFECT_TEST_FAIL: '.$label);
}
function effectUncertain($call,$label) {
    try { call_user_func($call); }
    catch (\ModxMCPClientException $e) {
        effectCheck(strpos($e->getMessage(),'MUTATION_OUTCOME_UNCERTAIN:')===0,$label.' uncertain');
        effectCheck(strpos($e->getMessage(),'FAKE_SECRET_')===false,$label.' secret');
        return;
    }
    throw new \RuntimeException('PARTIAL_EFFECT_FALSE_SUCCESS: '.$label);
}
$root=getenv('RUNNER_TEMP').'/mcp-external-fault-'.bin2hex(random_bytes(5));
mkdir($root,0700,true);
$pkg='mcpdisposable'.bin2hex(random_bytes(5));
$signature=$pkg.'-1.0-pl';
$platform=new DisposableEffectPlatform($root,$pkg,$signature);
$context=new DisposableEffectContext($modx,$platform);
$modx->setOption('modxmcp.allow_run_processor',true);
$modx->setOption('modxmcp.processor_access_mode','allowlist');
$modx->setOption('modxmcp.allowed_processors','resource/getlist');
$modx->setOption('modxmcp.privileged_processors','');
$checks=0;
try {
    foreach(array('throw','silent','reported') as $mode) {
        $platform->mode=$mode;
        @unlink($root.'/processor-effect');
        effectUncertain(function()use($context) {
            (new \ModxMcp\Tools\RunProcessorTool())->execute($context,array(
                'processor'=>'resource/getlist','properties'=>array('limit'=>1)
            ));
        },'processor-'.$mode);
        effectCheck(file_get_contents($root.'/processor-effect')==='processor-partial-file',
            'processor effect survived '.$mode);
        $checks++;
    }
    effectUncertain(function()use($context,$pkg) {
        (new \ModxMcp\Tools\PackageInstallTool())->execute($context,array(
            'package'=>$pkg,'provider'=>1));
    },'package-download');
    effectCheck(file_get_contents($root.'/download.part')==='partial-archive-bytes',
        'package partial download survived');
    effectCheck(!file_exists($root.'/installed-file'),'install must not dispatch on download fault');
    $checks++;
    $platform->mode='install';
    effectUncertain(function()use($context,$pkg) {
        (new \ModxMcp\Tools\PackageInstallTool())->execute($context,array(
            'package'=>$pkg,'provider'=>1));
    },'package-install');
    effectCheck(file_get_contents($root.'/installed-file')==='installed-partial-file',
        'install partial effect survived');
    $checks++;
    effectUncertain(function()use($context,$signature) {
        (new \ModxMcp\Tools\PackageUninstallTool())->execute($context,array(
            'signature'=>$signature));
    },'package-uninstall');
    effectCheck(file_get_contents($root.'/removed-file')==='removed-partial-file',
        'uninstall partial effect survived');
    $checks++;
    $paths=implode(',',$platform->events);
    effectCheck(substr_count($paths,'workspace/packages/install')===1,
        'install dispatch count after failed download + explicit second attempt');
    echo 'EXTERNAL_PARTIAL_EFFECTS_REAL_MODX_' . ($is2?'2':'3') .
       '_OK checks='.$checks.PHP_EOL;
} finally {
    $modx->setOption('modxmcp.allow_run_processor',false);
    $modx->setOption('modxmcp.processor_access_mode','disabled');
    foreach (glob($root.'/*') as $file) { if (is_file($file)) unlink($file); }
    rmdir($root);
}
