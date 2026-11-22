<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Реальное тестирование установленной модели VirtualPage через MCP на одноразовых MODX 2/3.
 * EN: Disposable real VirtualPage model lifecycle, MCP readback, CAS stale replay.
 */
if (PHP_SAPI !== 'cli' || getenv('MODXMCP_DISPOSABLE_CI') !== '1'
    || strpos((string)getenv('MODX_CONFIG_CORE'), '/home/runner/') !== 0
    || !is_file((string)getenv('MODX_CONFIG_CORE'))
    || strpos((string)getenv('RUNNER_TEMP'), '/home/runner/') !== 0) {
    fwrite(STDERR, "Refusing VirtualPage E2E outside disposable GitHub CI.\n");
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
$modelPath = MODX_CORE_PATH . 'components/virtualpage/model/';
if (!is_file($modelPath . 'virtualpage/vpevent.class.php')
    || !is_file($modelPath . 'virtualpage/vproute.class.php')) {
    throw new RuntimeException('Pinned upstream VirtualPage model absent');
}
if (!$modx->addPackage('virtualpage', $modelPath)) {
    throw new RuntimeException('Cannot load VirtualPage xPDO package');
}
$manager = $modx->getManager();
foreach (array('vpEvent', 'vpHandler', 'vpRoute') as $class) {
    if (!$manager->createObjectContainer($class)) {
        throw new RuntimeException('Disposable VirtualPage table setup failed: ' . $class);
    }
}
require MODX_CORE_PATH . 'components/modxmcp/model/modxmcp.class.php';
require __DIR__ . '/test.cas-mcp-helper.php';
$api = new modxMCP($modx);
$checks = 0;
function vpRpc($action, array $data = array()) {
    global $api;
    return disposableCasRpc($api, $action, $data);
}
function vpAssert($ok, $message) {
    global $checks;
    if (!$ok) throw new RuntimeException('VIRTUALPAGE_INSTALLED_CAS_FAILED ' . $message);
    ++$checks;
}
function vpRevision() {
    $state = vpRpc('get_site_state');
    return (int)$state['site_revision'];
}
function vpGuard() {
    return array('observed_site_revision' => vpRevision(),
        'strict_site_revision' => true, 'read_set' => array());
}
function vpMut($action, array $payload) {
    $before = vpRevision();
    $payload['_runtime_preconditions'] = vpGuard();
    $result = vpRpc($action, $payload);
    vpAssert(vpRevision() === $before + 1, $action . ' advanced CAS exactly once');
    return array($result, $payload);
}
function vpStale($action, array $old) {
    $before = vpRevision();
    $denied = false;
    try {
        vpRpc($action, $old);
    } catch (ModxMCPClientException $e) {
        $denied = strpos($e->getMessage(), 'STALE_STATE') !== false;
    }
    vpAssert($denied, $action . ' stale replay rejected');
    vpAssert(vpRevision() === $before, $action . ' stale replay consumes no revision');
}
function vpGone($getAction, $id, $label) {
    $gone = false;
    try {
        vpRpc($getAction, array('id' => $id));
    } catch (ModxMCPClientException $e) {
        $gone = strpos($e->getMessage(), 'not found') !== false;
    }
    vpAssert($gone, $label . ' no longer readable via MCP');
}
$marker = bin2hex(random_bytes(7));
$eventName = 'mcp_vp_event_' . $marker;
$handlerName = 'mcp_vp_handler_' . $marker;
$routePath = '/mcp-vp-' . $marker;
$eventId = 0; $handlerId = 0; $routeId = 0;
try {
    list($created, $old) = vpMut('virtualpage_create_event', array(
        'name' => $eventName, 'description' => 'disposable first', 'active' => 0
    ));
    $eventId = (int)($created['id'] ?? 0);
    vpAssert($eventId > 0, 'event created via MCP');
    $read = vpRpc('virtualpage_get_event', array('id' => $eventId));
    vpAssert((string)$read['name'] === $eventName, 'event independent readback');
    vpAssert((string)$read['description'] === 'disposable first', 'event initial description');
    vpStale('virtualpage_create_event', $old);
    echo "CAS_VIRTUALPAGE_CREATE_EVENT_INSTALLED_OK\n";

    list($created, $old) = vpMut('virtualpage_create_handler', array(
        'name' => $handlerName, 'description' => 'disposable first',
        'type' => 3, 'entry' => 0, 'active' => 0, 'cache' => 0
    ));
    $handlerId = (int)($created['id'] ?? 0);
    vpAssert($handlerId > 0, 'handler created via MCP');
    $read = vpRpc('virtualpage_get_handler', array('id' => $handlerId));
    vpAssert((string)$read['name'] === $handlerName, 'handler independent readback');
    vpAssert((int)$read['entry'] === 0, 'handler never points to production resource');
    vpStale('virtualpage_create_handler', $old);
    echo "CAS_VIRTUALPAGE_CREATE_HANDLER_INSTALLED_OK\n";

    list($created, $old) = vpMut('virtualpage_create_route', array(
        'route' => $routePath, 'method' => 'GET',
        'event' => $eventId, 'handler' => $handlerId,
        'description' => 'disposable first', 'active' => 0
    ));
    $routeId = (int)($created['id'] ?? 0);
    vpAssert($routeId > 0, 'route created via MCP');
    $read = vpRpc('virtualpage_get_route', array('id' => $routeId));
    vpAssert((string)$read['route'] === $routePath, 'route path MCP readback');
    vpAssert((int)$read['event'] === $eventId
        && (int)$read['handler'] === $handlerId, 'route references own fixtures');
    vpStale('virtualpage_create_route', $old);
    echo "CAS_VIRTUALPAGE_CREATE_ROUTE_INSTALLED_OK\n";

    list($updated, $old) = vpMut('virtualpage_update_event', array(
        'id' => $eventId, 'description' => 'disposable updated'
    ));
    $read = vpRpc('virtualpage_get_event', array('id' => $eventId));
    vpAssert((string)$read['description'] === 'disposable updated', 'event update readback');
    vpAssert((string)$read['name'] === $eventName, 'event name preserved');
    vpStale('virtualpage_update_event', $old);
    echo "CAS_VIRTUALPAGE_UPDATE_EVENT_INSTALLED_OK\n";

    list($updated, $old) = vpMut('virtualpage_update_handler', array(
        'id' => $handlerId, 'description' => 'disposable updated'
    ));
    $read = vpRpc('virtualpage_get_handler', array('id' => $handlerId));
    vpAssert((string)$read['description'] === 'disposable updated', 'handler update readback');
    vpAssert((int)$read['entry'] === 0, 'handler entry preserved');
    vpStale('virtualpage_update_handler', $old);
    echo "CAS_VIRTUALPAGE_UPDATE_HANDLER_INSTALLED_OK\n";

    list($updated, $old) = vpMut('virtualpage_update_route', array(
        'id' => $routeId, 'description' => 'disposable updated'
    ));
    $read = vpRpc('virtualpage_get_route', array('id' => $routeId));
    vpAssert((string)$read['description'] === 'disposable updated', 'route update readback');
    vpAssert((string)$read['route'] === $routePath, 'route path preserved');
    vpStale('virtualpage_update_route', $old);
    echo "CAS_VIRTUALPAGE_UPDATE_ROUTE_INSTALLED_OK\n";

    list($deleted, $old) = vpMut('virtualpage_delete_route', array('id' => $routeId));
    vpAssert(!empty($deleted['deleted']), 'route removed');
    vpGone('virtualpage_get_route', $routeId, 'route');
    vpStale('virtualpage_delete_route', $old);
    $routeId = 0;
    echo "CAS_VIRTUALPAGE_DELETE_ROUTE_INSTALLED_OK\n";

    list($deleted, $old) = vpMut('virtualpage_delete_handler', array('id' => $handlerId));
    vpAssert(!empty($deleted['deleted']), 'handler removed');
    vpGone('virtualpage_get_handler', $handlerId, 'handler');
    vpStale('virtualpage_delete_handler', $old);
    $handlerId = 0;
    echo "CAS_VIRTUALPAGE_DELETE_HANDLER_INSTALLED_OK\n";

    list($deleted, $old) = vpMut('virtualpage_delete_event', array('id' => $eventId));
    vpAssert(!empty($deleted['deleted']), 'event removed');
    vpGone('virtualpage_get_event', $eventId, 'event');
    vpStale('virtualpage_delete_event', $old);
    $eventId = 0;
    echo "CAS_VIRTUALPAGE_DELETE_EVENT_INSTALLED_OK\n";

    // RU: Отдельный cache-сценарий: создаём только одноразовый тестовый ключ.
    // EN: Isolated cache fixture, no production cache or manual changes.
    $manager = $modx->getCacheManager();
    $options = array('cache_key' => 'default/virtualpage/');
    $cacheKey = 'mcp-disposable-cache-' . $marker;
    $cacheValue = 'fixture';
    $manager->set($cacheKey, $cacheValue, 0, $options);
    vpAssert($manager->get($cacheKey, $options) === 'fixture', 'cache seed visible');
    list($cleared, $old) = vpMut('virtualpage_clear_cache', array());
    vpAssert(!empty($cleared['cleared']), 'clear cache command succeeded');
    vpAssert($manager->get($cacheKey, $options) === null
        || $manager->get($cacheKey, $options) === false,
        'test-owned VirtualPage cache removed');
    vpStale('virtualpage_clear_cache', $old);
    echo "CAS_VIRTUALPAGE_CLEAR_CACHE_INSTALLED_OK\n";
} finally {
    // RU: Уборка касается только созданных данным тестом объектов в disposable БД.
    // EN: Cleanup only fixture IDs in an ephemeral DB, never an installed site.
    foreach (array(
        array('virtualpage_delete_route', $routeId),
        array('virtualpage_delete_handler', $handlerId),
        array('virtualpage_delete_event', $eventId)
    ) as $item) {
        if ($item[1] > 0) {
            try { vpRpc($item[0], array('id' => $item[1])); }
            catch (Throwable $ignored) {}
        }
    }
}
echo 'CAS_VIRTUALPAGE_INSTALLED_LIFECYCLE_OK checks=' . $checks
    . ' platform=' . ($is2 ? 'modx2' : 'modx3') . PHP_EOL;
