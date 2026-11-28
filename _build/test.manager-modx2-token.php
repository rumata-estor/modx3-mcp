<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Регрессия ротации токена MODX 2 на подготовленном пакете, без БД.
 * EN: MODX 2 Manager token rotation regression on staged package, no DB.
 */
class ManagerUserFake {
    public $authenticated = true;
    public function isAuthenticated($context) { return $context === 'mgr' && $this->authenticated; }
}
class ManagerTokenSettingFake {
    public $saved = 'original'; public $pending; public $reject = false;
    public function set($key, $value) { if ($key === 'value') $this->pending = $value; }
    public function save() { if ($this->reject) return false; $this->saved = $this->pending; return true; }
}
class modX {
    public $user; public $setting; public $permission = true; public $runtimeAvailable = true;
    public $reloads = 0; public $core;
    public function __construct($core) {
        $this->core = $core; $this->user = new ManagerUserFake();
        $this->setting = new ManagerTokenSettingFake();
    }
    public function getVersionData() { return array('version' => '2.8.8-pl'); }
    public function getOption($key, $options = null, $default = null) {
        if ($key === 'core_path') return dirname(dirname($this->core)) . '/';
        if ($key === 'modxmcp.core_path') return $this->runtimeAvailable ? $this->core . '/' : $this->core . '/missing/';
        if ($key === 'modxmcp.audit_log') return false;
        return $default;
    }
    public function hasPermission($permission) { return $permission === 'settings' && $this->permission; }
    public function getObject($class, $criteria) {
        if ($class !== 'modSystemSetting' || $criteria !== array('key'=>'modxmcp.api_token')) {
            throw new RuntimeException('Unexpected access or service account selection');
        }
        return $this->setting;
    }
    public function reloadConfig() { $this->reloads++; }
    public function getCacheManager() { return null; }
}
class modProcessor {
    public $modx;
    public function __construct($modx) { $this->modx = $modx; }
    public function failure($message) { return array('success'=>false, 'message'=>$message); }
    public function success($message, $object) { return array('success'=>true, 'object'=>$object); }
}
function requireCheck($condition, $message) {
    if (!$condition) throw new RuntimeException('MANAGER_TOKEN_FAIL: '.$message);
}
$core = rtrim($argv[1], '/');
require $core.'/processors/mgr/regeneratetoken3.class.php';
$checks = 0;
foreach (array('unauthenticated','permission','missing_runtime','save_failure','success') as $scenario) {
    $modx = new modX($core);
    if ($scenario === 'unauthenticated') $modx->user->authenticated = false;
    if ($scenario === 'permission') $modx->permission = false;
    if ($scenario === 'missing_runtime') $modx->runtimeAvailable = false;
    if ($scenario === 'save_failure') $modx->setting->reject = true;
    $user = $modx->user;
    $processor = new ModxmcpRegenerateToken3Processor($modx);
    $result = $processor->process();
    requireCheck($modx->user === $user, 'Manager identity changed');
    if ($scenario === 'success') {
        requireCheck($result['success'] === true, 'success rejected');
        requireCheck(preg_match('/^[a-f0-9]{64}$/D', $result['object']['token']) === 1, 'invalid token');
        requireCheck($modx->setting->saved === $result['object']['token'] && $modx->reloads === 1, 'token not persisted');
    } else {
        requireCheck($result['success'] === false, $scenario.' accepted');
        requireCheck($modx->setting->saved === 'original' && $modx->reloads === 0, $scenario.' modified state');
        if ($scenario === 'missing_runtime') requireCheck(strpos($result['message'], 'CAS_RUNTIME_REQUIRED') !== false, 'missing runtime fell back');
        if ($scenario === 'save_failure') requireCheck(strpos($result['message'], 'could not save') !== false, 'save error hidden');
    }
    $checks++;
}
echo 'MODX2_MANAGER_TOKEN_ROTATION_OK checks='.$checks.PHP_EOL;
