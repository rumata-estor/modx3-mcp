<?php
/* MODX MCP TEST / ТЕСТ MODX MCP
 * RU: Контракт update_system_setting при переименовании настройки.
 * RU: PK-поле 'key' нельзя менять через xPDOObject::save() — UPDATE WHERE
 * RU: строится по уже изменённому значению поля и не находит строк
 * RU: (тихий no-op при формальном success). Переименование обязано идти
 * RU: одним атомарным updateCollection по ИСХОДНОМУ ключу.
 * EN: update_system_setting rename contract. A PK rename cannot go through
 * EN: xPDOObject::save() — the UPDATE WHERE clause uses the already-modified
 * EN: value and matches zero rows while still reporting success. The rename
 * EN: must be one atomic updateCollection against the ORIGINAL key.
 */
$root = dirname(__DIR__) . '/core/components/modxmcp/src';
require_once $root . '/Autoloader.php';
\ModxMcp\Autoloader::register($root);

class ModxMCPClientException extends \RuntimeException {}

class FakeSystemSetting {
    public $fields;
    public $saveCalls = 0;
    public function __construct(array $fields) { $this->fields = $fields; }
    public function get($k) { return isset($this->fields[$k]) ? $this->fields[$k] : null; }
    public function set($k, $v) { $this->fields[$k] = $v; return true; }
    public function save() { $this->saveCalls++; return true; }
}

class FakeRenameCacheManager {
    public function refresh() {}
}

class FakeRenameModx {
    public $settings = array();
    public $updateCalls = array();
    public $forceUpdateResult = null;
    public $hideKeyOnReadback = null;
    public $cacheManager;
    public function __construct(array $settings) {
        $this->settings = $settings;
        $this->cacheManager = new FakeRenameCacheManager();
    }
    public function getOption($key, $options = null, $default = null) {
        if ($key === 'modxmcp.audit_log') { return false; }
        return $default;
    }
    public function getObject($class, $criteria) {
        if ($class !== 'FakeSystemSetting' || !isset($criteria['key'])) { return null; }
        $key = (string) $criteria['key'];
        if ($this->hideKeyOnReadback !== null && $key === $this->hideKeyOnReadback) { return null; }
        return isset($this->settings[$key]) ? $this->settings[$key] : null;
    }
    public function updateCollection($class, array $set, $criteria) {
        $this->updateCalls[] = array($class, $set, $criteria);
        if ($this->forceUpdateResult !== null) { return $this->forceUpdateResult; }
        if ($class !== 'FakeSystemSetting' || !isset($criteria['key'])) { return false; }
        $key = (string) $criteria['key'];
        if (!isset($this->settings[$key])) { return 0; }
        $setting = $this->settings[$key];
        foreach ($set as $field => $value) {
            if ($field === 'key') {
                $newKey = (string) $value;
                if ($newKey !== $key && isset($this->settings[$newKey])) { return false; }
                unset($this->settings[$key]);
                $setting->fields['key'] = $newKey;
                $this->settings[$newKey] = $setting;
            } else {
                $setting->fields[$field] = $value;
            }
        }
        return 1;
    }
    public function getCacheManager() { return new FakeRenameCacheManager(); }
    public function log($level, $message) {}
    public $user = null;
}

class FakeRenamePlatform {
    public function className($name) { return 'FakeSystemSetting'; }
}

class FakeRenameContext {
    private $modx;
    public function __construct(FakeRenameModx $modx) { $this->modx = $modx; }
    public function modx() { return $this->modx; }
    public function platform() { return new FakeRenamePlatform(); }
}

function fail($message) { throw new \RuntimeException($message); }

$tool = new \ModxMcp\Tools\SystemSettingUpdateTool();

// 1. Happy path: rename goes through ONE atomic updateCollection on the
// ORIGINAL key, never through xPDOObject::save(), with fresh readback.
$modx = new FakeRenameModx(array(
    'site_name' => new FakeSystemSetting(array('key' => 'site_name', 'value' => 'Old', 'xtype' => 'textfield', 'namespace' => 'core', 'area' => '')),
));
$context = new FakeRenameContext($modx);
$result = $tool->execute($context, array('key' => 'site_name', 'new_key' => 'site_title', 'value' => 'New'));
if (count($modx->updateCalls) !== 1) { fail('RENAME_EXPECTS_SINGLE_UPDATE_COLLECTION'); }
list($class, $set, $criteria) = $modx->updateCalls[0];
if ($class !== 'FakeSystemSetting') { fail('RENAME_WRONG_CLASS'); }
if ($criteria !== array('key' => 'site_name')) { fail('RENAME_WHERE_MUST_USE_ORIGINAL_KEY'); }
if (($set['key'] ?? null) !== 'site_title' || ($set['value'] ?? null) !== 'New') { fail('RENAME_SET_INCOMPLETE'); }
if ($modx->settings['site_title']->saveCalls !== 0) { fail('RENAME_MUST_NOT_CALL_OBJECT_SAVE'); }
if (!isset($modx->settings['site_title']) || isset($modx->settings['site_name'])) { fail('RENAME_DID_NOT_MOVE_ROW'); }
if ($modx->settings['site_title']->fields['value'] !== 'New') { fail('RENAME_LOST_VALUE_UPDATE'); }
if (($result['key'] ?? null) !== 'site_title' || ($result['value'] ?? null) !== 'New') { fail('RENAME_RESULT_NOT_FRESH_READBACK'); }

// 2. Target key already exists → explicit refusal, no write at all.
$modx = new FakeRenameModx(array(
    'site_name' => new FakeSystemSetting(array('key' => 'site_name', 'value' => 'Old')),
    'site_title' => new FakeSystemSetting(array('key' => 'site_title', 'value' => 'Taken')),
));
try {
    $tool->execute(new FakeRenameContext($modx), array('key' => 'site_name', 'new_key' => 'site_title'));
    fail('RENAME_CONFLICT_NOT_DETECTED');
} catch (\ModxMCPClientException $e) {
    if (strpos($e->getMessage(), 'already exists') === false) { throw $e; }
}
if ($modx->updateCalls !== array()) { fail('RENAME_CONFLICT_MUST_NOT_WRITE'); }

// 3. Empty new_key is rejected before any write.
$modx = new FakeRenameModx(array(
    'site_name' => new FakeSystemSetting(array('key' => 'site_name', 'value' => 'Old')),
));
try {
    $tool->execute(new FakeRenameContext($modx), array('key' => 'site_name', 'new_key' => ''));
    fail('RENAME_EMPTY_KEY_NOT_REJECTED');
} catch (\ModxMCPClientException $e) {
    if (strpos($e->getMessage(), 'non-empty') === false) { throw $e; }
}
if ($modx->updateCalls !== array()) { fail('RENAME_EMPTY_KEY_MUST_NOT_WRITE'); }

// 4. modxmcp.* is protected for renames the same way as for direct writes.
$modx = new FakeRenameModx(array(
    'site_name' => new FakeSystemSetting(array('key' => 'site_name', 'value' => 'Old')),
));
try {
    $tool->execute(new FakeRenameContext($modx), array('key' => 'site_name', 'new_key' => 'modxmcp.token'));
    fail('RENAME_GUARD_NOT_APPLIED');
} catch (\ModxMCPClientException $e) {
    if (strpos($e->getMessage(), 'modxmcp.') === false) { throw $e; }
}
if ($modx->updateCalls !== array()) { fail('RENAME_GUARD_MUST_NOT_WRITE'); }

// 5. Backwards compatibility: without new_key the plain update path is intact.
$setting = new FakeSystemSetting(array('key' => 'site_name', 'value' => 'Old', 'xtype' => 'textfield', 'namespace' => 'core', 'area' => ''));
$modx = new FakeRenameModx(array('site_name' => $setting));
$context = new FakeRenameContext($modx);
$result = $tool->execute($context, array('key' => 'site_name', 'value' => 'New'));
if ($setting->saveCalls !== 1) { fail('PLAIN_UPDATE_MUST_USE_OBJECT_SAVE'); }
if ($modx->updateCalls !== array()) { fail('PLAIN_UPDATE_MUST_NOT_USE_UPDATE_COLLECTION'); }
if (($result['key'] ?? null) !== 'site_name' || ($result['value'] ?? null) !== 'New') { fail('PLAIN_UPDATE_RESULT_BROKEN'); }

// 6. Zero affected rows (row vanished concurrently) must fail closed.
$setting = new FakeSystemSetting(array('key' => 'site_name', 'value' => 'Old'));
$modx = new FakeRenameModx(array('site_name' => $setting));
$modx->forceUpdateResult = 0;
try {
    $tool->execute(new FakeRenameContext($modx), array('key' => 'site_name', 'new_key' => 'site_title'));
    fail('RENAME_ZERO_ROWS_MUST_FAIL');
} catch (\ModxMCPClientException $e) {
    if (strpos($e->getMessage(), 'Failed to update') === false) { throw $e; }
}

// 7. Rename applied but readback lost → uncertain outcome, not false success.
$setting = new FakeSystemSetting(array('key' => 'site_name', 'value' => 'Old'));
$modx = new FakeRenameModx(array('site_name' => $setting));
$modx->hideKeyOnReadback = 'site_title';
try {
    $tool->execute(new FakeRenameContext($modx), array('key' => 'site_name', 'new_key' => 'site_title'));
    fail('RENAME_READBACK_FAILURE_NOT_DETECTED');
} catch (\ModxMCPClientException $e) {
    if (strpos($e->getMessage(), 'MUTATION_OUTCOME_UNCERTAIN') !== 0) { throw $e; }
}

echo 'SYSTEM_SETTING_RENAME_CONTRACT_OK checks=7' . PHP_EOL;
