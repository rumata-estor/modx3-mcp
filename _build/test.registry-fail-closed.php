<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Исполняем реальный диспетчер без доступа к БД; проверяем отказ и CAS.
 * EN: Execute the real dispatcher with no database access; verify denial and CAS.
 */
class RegistryUserFake {
    public function get($key) { return in_array($key, array('active','sudo'), true) ? 1 : null; }
}
class RegistryModxFake {
    public $user; public $disabled = '';
    public function getOption($key, $options = null, $default = null) {
        if ($key === 'modxmcp.service_user_id') return 1;
        if ($key === 'modxmcp.disabled_groups') return $this->disabled;
        return $default;
    }
    public function getObject($class, $where) {
        if ($where !== array('id'=>1) || !in_array($class, array('modUser','MODX\\Revolution\\modUser'), true))
            throw new RuntimeException('Unexpected legacy database access');
        return new RegistryUserFake();
    }
}
class RegistryToolFake {
    public $calls = 0; public $mutation = false; public $supported = true; public $changes = false;
    private $supportCalls = 0;
    public function supports($context) { $this->supportCalls++; return $this->supported && (!$this->changes || $this->supportCalls === 1); }
    public function isMutation() { return $this->mutation; }
    public function execute($context, array $data) { $this->calls++; return $data; }
}
class RegistryRuntimeFake {
    public $tool; public $disappears = false; private $lookups = 0;
    public function registry() { return $this; }
    public function context() { return $this; }
    public function get($action) { $this->lookups++; return $this->disappears && $this->lookups > 1 ? null : $this->tool; }
}
function checkResult($condition, $message) {
    if (!$condition) throw new RuntimeException('REGISTRY_RUNTIME_FAIL: '.$message);
}
$path = $argv[1];
require $path;
$src = dirname(__DIR__).'/core/components/modxmcp/src';
require $src.'/Autoloader.php';
\ModxMcp\Autoloader::register($src);
$class = new ReflectionClass('modxMCP');
$runtimeProperty = $class->getProperty('modularRuntime');
$runtimeProperty->setAccessible(true);
$checks = 0;
foreach (array('no_runtime','unknown','unsupported','disappeared','support_changed','missing_cas','success') as $case) {
    $instance = $class->newInstanceWithoutConstructor();
    $instance->modx = new RegistryModxFake();
    $runtime = new RegistryRuntimeFake();
    $tool = new RegistryToolFake();
    $runtime->tool = $tool;
    if ($case === 'unknown') $runtime->tool = null;
    if ($case === 'unsupported') $tool->supported = false;
    if ($case === 'disappeared') $runtime->disappears = true;
    if ($case === 'support_changed') $tool->changes = true;
    if ($case === 'missing_cas') $tool->mutation = true;
    $runtimeProperty->setValue($instance, $case === 'no_runtime' ? null : $runtime);
    $action = $case === 'missing_cas' ? 'update_element' : 'get_element';
    try {
        $result = $instance->processRequest($action, 'chunk', array('id'=>42));
        checkResult($case === 'success', $case.' accepted');
        checkResult($result === array('id'=>42,'type'=>'chunk') && $tool->calls === 1, 'read did not use module');
    } catch (ModxMCPClientException $e) {
        $prefix = $case === 'missing_cas' ? 'CAS_REQUIRED:' : 'CAS_RUNTIME_REQUIRED:';
        checkResult($case !== 'success' && strpos($e->getMessage(), $prefix) === 0 && $tool->calls === 0, $case.' wrong refusal');
    }
    $checks++;
}
echo 'REGISTRY_RUNTIME_FAIL_CLOSED_OK platform='.modxMCP::VARIANT.' checks='.$checks.PHP_EOL;
