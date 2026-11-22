<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Реальный жизненный цикл MIGX config на одноразовых MODX 2/3 через MCP.
 * EN: Real pinned MIGX config create/update/delete and stale CAS on disposable MODX 2/3.
 */
if (PHP_SAPI !== 'cli' || getenv('MODXMCP_DISPOSABLE_CI') !== '1'
    || strpos((string)getenv('MODX_CONFIG_CORE'), '/home/runner/') !== 0
    || !is_file((string)getenv('MODX_CONFIG_CORE'))
    || strpos((string)getenv('RUNNER_TEMP'), '/home/runner/') !== 0) {
    fwrite(STDERR, "Refusing MIGX E2E outside disposable CI.\n");
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
$path = MODX_CORE_PATH . 'components/migx/model/';
if (!is_file($path . 'migx/migxconfig.class.php')) {
    throw new RuntimeException('Pinned MIGX model source is absent');
}
if (!$modx->addPackage('migx', $path)) {
    throw new RuntimeException('MIGX xPDO model cannot be loaded');
}
// RU: Временная БД GitHub Actions, без SQL и модификации установленных сайтов.
// EN: Disposable CI DB only, create addon tables via native xPDO manager.
$manager = $modx->getManager();
foreach (array('migxConfig', 'migxFormtab', 'migxFormtabField',
    'migxConfigElement', 'migxElement') as $class) {
    if (!$manager->createObjectContainer($class)) {
        throw new RuntimeException('Cannot create disposable MIGX table: ' . $class);
    }
}
require MODX_CORE_PATH . 'components/modxmcp/model/modxmcp.class.php';
require __DIR__ . '/test.cas-mcp-helper.php';
$api = new modxMCP($modx);
$checks = 0;
function migxRpc($action, array $data = array()) {
    global $api;
    return disposableCasRpc($api, $action, $data);
}
function migxAssert($condition, $label) {
    global $checks;
    if (!$condition) throw new RuntimeException('MIGX_INSTALLED_CAS_FAILED ' . $label);
    ++$checks;
}
function migxGuard() {
    $state = migxRpc('get_site_state');
    return array(
        'observed_site_revision' => (int)$state['site_revision'],
        'strict_site_revision' => true,
        'read_set' => array()
    );
}
function migxRevision() {
    $state = migxRpc('get_site_state');
    return (int)$state['site_revision'];
}
function migxStaleReplay($action, array $request) {
    $before = migxRevision();
    $denied = false;
    try {
        migxRpc($action, $request);
    } catch (ModxMCPClientException $e) {
        $denied = strpos($e->getMessage(), 'STALE_STATE') !== false;
    }
    migxAssert($denied, $action . ' refused old CAS');
    migxAssert(migxRevision() === $before, $action . ' replay did not advance revision');
}
$marker = bin2hex(random_bytes(6));
$name = 'mcp_migx_disposable_' . $marker;
$id = 0;
try {
    $before = migxRevision();
    $create = array(
        'name' => $name,
        'category' => 'mcp_fixture_before',
        'published' => 0,
        'formtabs' => '[]',
        '_runtime_preconditions' => migxGuard()
    );
    $created = migxRpc('migx_create_config', $create);
    $id = (int)($created['id'] ?? 0);
    migxAssert($id > 0, 'MIGX config created by MCP');
    migxAssert(migxRevision() === $before + 1, 'MIGX create advanced CAS once');
    $read = migxRpc('migx_get_config', array('id' => $id));
    migxAssert((string)$read['name'] === $name, 'independent MCP name readback');
    migxAssert((string)$read['category'] === 'mcp_fixture_before',
        'independent MCP initial category');
    migxStaleReplay('migx_create_config', $create);
    $list = migxRpc('migx_list_configs', array('query' => $name));
    migxAssert((int)$list['total'] === 1, 'create replay created no duplicate');
    echo "CAS_MIGX_CREATE_INSTALLED_READBACK_STALE_REPLAY_OK\n";

    $before = migxRevision();
    $update = array(
        'id' => $id,
        'category' => 'mcp_fixture_after',
        '_runtime_preconditions' => migxGuard()
    );
    $updated = migxRpc('migx_update_config', $update);
    migxAssert((int)$updated['id'] === $id, 'MIGX update returns same config');
    migxAssert(migxRevision() === $before + 1, 'MIGX update advanced CAS once');
    $read = migxRpc('migx_get_config', array('id' => $id));
    migxAssert((string)$read['category'] === 'mcp_fixture_after',
        'independent MCP updated category');
    migxAssert((string)$read['name'] === $name, 'update did not replace name');
    migxStaleReplay('migx_update_config', $update);
    $read = migxRpc('migx_get_config', array('id' => $id));
    migxAssert((string)$read['category'] === 'mcp_fixture_after',
        'stale update did not overwrite saved category');
    echo "CAS_MIGX_UPDATE_INSTALLED_READBACK_STALE_REPLAY_OK\n";

    $before = migxRevision();
    $delete = array('id' => $id, '_runtime_preconditions' => migxGuard());
    $removed = migxRpc('migx_delete_config', $delete);
    migxAssert(!empty($removed['deleted']), 'MIGX delete completed');
    migxAssert(migxRevision() === $before + 1, 'MIGX delete advanced CAS once');
    $notFound = false;
    try {
        migxRpc('migx_get_config', array('id' => $id));
    } catch (ModxMCPClientException $e) {
        $notFound = strpos($e->getMessage(), 'not found') !== false;
    }
    migxAssert($notFound, 'deleted config cannot be fetched through MCP');
    migxStaleReplay('migx_delete_config', $delete);
    $list = migxRpc('migx_list_configs', array('query' => $name));
    migxAssert((int)$list['total'] === 0, 'deleted config absent from independent list');
    $id = 0;
    echo "CAS_MIGX_DELETE_INSTALLED_READBACK_STALE_REPLAY_OK\n";
} finally {
    if ($id > 0) {
        // RU: Cleanup строго собственного объекта в одноразовой БД.
        // EN: Cleanup only the test-owned object in disposable DB.
        try {
            migxRpc('migx_delete_config', array('id' => $id));
        } catch (Throwable $ignored) {}
    }
}
echo 'CAS_MIGX_INSTALLED_LIFECYCLE_OK checks=' . $checks
    . ' platform=' . ($is2 ? 'modx2' : 'modx3') . PHP_EOL;
