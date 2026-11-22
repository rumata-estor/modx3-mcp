<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Проверка ClientConfig: валидация до записи и неопределённый исход после записи.
 * EN: Verify ClientConfig pre-save validation and uncertain post-save failures.
 */
namespace ModxMcp\Tools {
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/ClientConfigSupport.php';
}
namespace {
class ModxMCPClientException extends \RuntimeException {}
class FakeCCRow {
    public $values = array();
    public $shouldFail = false;
    public $saves = 0;
    public function set($key, $value) { $this->values[$key] = $value; }
    public function get($key) { return isset($this->values[$key]) ? $this->values[$key] : null; }
    public function save() { $this->saves++; return !$this->shouldFail; }
}
class FakeCCCache {
    public $removed = array();
    public function delete($key, $options) {
        $this->removed[] = $options['cache_key'].':'.$key;
        return true;
    }
    public function get($key, $options) { return null; }
}
class FakeCCModx {
    public $created = array();
    public $failChild = false;
    public $cache;
    public $events = array();
    public function __construct() { $this->cache = new FakeCCCache(); }
    public function getCacheManager() { return $this->cache; }
    public function getOption($key, $unused=null, $default=null) {
        return $key==='clientconfig.clear_cache' ? false : $default;
    }
    public function invokeEvent($event) { $this->events[] = $event; }
    public function getObject($class, $criteria) { return null; }
    public function newObject($class) {
        $row = new FakeCCRow();
        $row->shouldFail = $this->failChild;
        $this->created[] = $row;
        return $row;
    }
}
class FakeCCContext {
    public $db;
    public function __construct() { $this->db = new FakeCCModx(); }
    public function modx() { return $this->db; }
}
$checks = 0;
foreach (array('text', 42, array('web' => array('bad')), array('' => 'bad')) as $invalid) {
    try {
        \ModxMcp\Tools\ClientConfigSupport::validateContextValues(array('context_values' => $invalid));
        throw new \RuntimeException('CLIENTCONFIG_INVALID_CONTEXT_ACCEPTED');
    } catch (ModxMCPClientException $e) { $checks++; }
}
\ModxMcp\Tools\ClientConfigSupport::validateContextValues(
    array('context_values'=>array('web'=>'allowed', 'mgr'=>null))
);
$checks++;
$ctx = new FakeCCContext();
$setting = new FakeCCRow();
$setting->set('id', 20);
$ctx->db->failChild = true;
try {
    \ModxMcp\Tools\ClientConfigSupport::applyContextValuesAfterSave(
        $ctx, $setting, array('context_values'=>array('web'=>'valid'))
    );
    throw new \RuntimeException('CLIENTCONFIG_CHILD_FAILURE_UNDETECTED');
} catch (ModxMCPClientException $e) {
    if (strpos($e->getMessage(), 'MUTATION_OUTCOME_UNCERTAIN:') !== 0) throw $e;
    $checks++;
}
if ($ctx->db->cache->removed !== array('system_settings:clientconfig')
    || $ctx->db->events !== array('ClientConfig_ConfigChange')) {
    throw new \RuntimeException('CLIENTCONFIG_PARTIAL_WRITE_CACHE_NOT_INVALIDATED');
}
$checks++;
$create = file_get_contents(__DIR__.'/../core/components/modxmcp/src/Tools/ClientConfigSettingCreateTool.php');
$update = file_get_contents(__DIR__.'/../core/components/modxmcp/src/Tools/ClientConfigSettingUpdateTool.php');
foreach (array($create, $update) as $tool) {
    $validation = strpos($tool, 'ClientConfigSupport::validateContextValues($data)');
    $save = strpos($tool, '$setting->save()');
    $child = strpos($tool, 'ClientConfigSupport::applyContextValuesAfterSave');
    if ($validation === false || $save === false || $child === false
        || !($validation < $save && $save < $child)) {
        throw new \RuntimeException('CLIENTCONFIG_PREWRITE_VALIDATION_ORDER_MISSING');
    }
    $checks++;
}
echo 'CLIENTCONFIG_PARTIAL_WRITE_GUARD_OK checks='.$checks.PHP_EOL;
}
