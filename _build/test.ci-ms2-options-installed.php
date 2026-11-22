<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Создание и изменение одной тестовой настройки miniShop2 через штатный MCP processor.
 * EN: Disposable miniShop2 option create/update, independent MCP readback and stale-CAS denial.
 */
if (PHP_SAPI !== 'cli' || getenv('MODXMCP_DISPOSABLE_CI') !== '1'
    || strpos((string)getenv('MODX_CONFIG_CORE'), '/home/runner/') !== 0
    || !is_file((string)getenv('MODX_CONFIG_CORE'))
    || strpos((string)getenv('RUNNER_TEMP'), '/home/runner/') !== 0) {
    fwrite(STDERR, "Refusing miniShop2 option E2E outside disposable CI.\n");
    exit(3);
}
require getenv('MODX_CONFIG_CORE');
$is2 = !is_file(MODX_CORE_PATH . 'src/Revolution/modX.php');
if ($is2) {
    require MODX_CORE_PATH . 'model/modx/modx.class.php';
    $modx = new modX();
} else {
    require MODX_CORE_PATH . 'vendor/autoload.php';
    $modx = \MODX\Revolution\modX::getInstance();
}
$modx->initialize('mgr');
$modx->setOption('modxmcp.disabled_groups', '');
$path = MODX_CORE_PATH . 'components/minishop2/';
if (!is_file($path . 'model/minishop2/msoption.class.php')
    || !is_file($path . 'processors/mgr/settings/option/create.class.php')) {
    throw new RuntimeException('Pinned miniShop2 option runtime absent');
}
$admin = $modx->getObject(
    $is2 ? 'modUser' : '\MODX\Revolution\modUser', 1
);
if (!$admin || (int)$admin->get('sudo') !== 1) {
    throw new RuntimeException('Disposable sudo manager missing');
}
$modx->user = $admin;
$modx->user->addSessionContext('mgr');
if (!$modx->hasPermission('mssetting_save')) {
    throw new RuntimeException('Disposable manager does not have native miniShop2 permission');
}
if (!$modx->addPackage('minishop2', $path . 'model/')) {
    throw new RuntimeException('Cannot load native miniShop2 model');
}
$manager = $modx->getManager();
foreach (array('msOption', 'msCategoryOption', 'msProductOption') as $class) {
    if (!$manager->createObjectContainer($class)) {
        throw new RuntimeException('Cannot create isolated option model: ' . $class);
    }
}
require MODX_CORE_PATH . 'components/modxmcp/model/modxmcp.class.php';
require __DIR__ . '/test.cas-mcp-helper.php';
$api = new modxMCP($modx);
$checks = 0;
function optRpc($action, array $data = array()) {
    global $api;
    return disposableCasRpc($api, $action, $data);
}
function optCheck($ok, $label) {
    global $checks;
    if (!$ok) throw new RuntimeException('MS2_OPTION_CAS_FAILED ' . $label);
    ++$checks;
}
function optRevision() {
    $s = optRpc('get_site_state');
    return (int)$s['site_revision'];
}
function optGuard() {
    return array('observed_site_revision' => optRevision(),
        'strict_site_revision' => true, 'read_set' => array());
}
function optMut($action, array $data) {
    $before = optRevision();
    $data['_runtime_preconditions'] = optGuard();
    $result = optRpc($action, $data);
    optCheck(optRevision() === $before + 1, $action . ' advances revision once');
    return array($result, $data);
}
function optStale($action, array $request) {
    $revision = optRevision();
    $denied = false;
    try { optRpc($action, $request); }
    catch (ModxMCPClientException $e) {
        $denied = strpos($e->getMessage(), 'STALE_STATE') !== false;
    }
    optCheck($denied, $action . ' stale replay refused');
    optCheck(optRevision() === $revision, $action . ' stale replay did not mutate revision');
}
function optList($key) {
    $response = optRpc('ms2_list_options', array('query' => $key, 'limit' => 100));
    $rows = isset($response['results']) && is_array($response['results'])
        ? $response['results'] : array();
    return array_values(array_filter($rows, function ($row) use ($key) {
        return is_array($row) && isset($row['key'])
            && (string)$row['key'] === $key;
    }));
}
$key = 'mcpdisposableoption' . bin2hex(random_bytes(6));
list($created, $request) = optMut('ms2_create_option', array(
    'key' => $key,
    'caption' => 'temporary-before',
    'description' => 'single disposable option',
    'type' => 'textfield',
    'category' => 0
));
$rows = optList($key);
optCheck(count($rows) === 1, 'created one real option via MCP');
$id = (int)($rows[0]['id'] ?? 0);
optCheck($id > 0, 'option id available from MCP readback');
optCheck((string)$rows[0]['caption'] === 'temporary-before',
    'option first caption persisted');
$read = optRpc('ms2_get_option', array('id' => $id));
optCheck((string)$read['key'] === $key, 'get option readback key');
optStale('ms2_create_option', $request);
optCheck(count(optList($key)) === 1, 'stale create did not duplicate');
echo "CAS_MS2_CREATE_OPTION_INSTALLED_READBACK_STALE_OK\n";

list($updated, $request) = optMut('ms2_update_option', array(
    'id' => $id, 'key' => $key, 'caption' => 'temporary-after',
    'description' => 'changed in isolated addon'
));
$read = optRpc('ms2_get_option', array('id' => $id));
optCheck((string)$read['caption'] === 'temporary-after', 'update persisted via MCP');
optCheck((string)$read['key'] === $key, 'update left key unchanged');
optStale('ms2_update_option', $request);
$read = optRpc('ms2_get_option', array('id' => $id));
optCheck((string)$read['caption'] === 'temporary-after',
    'stale update did not overwrite option');
echo "CAS_MS2_UPDATE_OPTION_INSTALLED_READBACK_STALE_OK\n";
// RU: Тестовая база и единственный сгенерированный option удаляются целиком GitHub CI.
// EN: No direct database cleanup: the entire disposable MODX/MariaDB fixture is destroyed.
echo 'CAS_MS2_OPTIONS_INSTALLED_E2E_OK checks=' . $checks
    . ' platform=' . ($is2 ? 'modx2' : 'modx3') . PHP_EOL;
