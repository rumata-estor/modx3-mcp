<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Испытание реального miniShop2 msLink через штатные процессоры и MCP на disposable MODX.
 * EN: Disposable pinned miniShop2 link-type processor lifecycle with MCP CAS/readback.
 */
if (PHP_SAPI !== 'cli' || getenv('MODXMCP_DISPOSABLE_CI') !== '1'
    || strpos((string)getenv('MODX_CONFIG_CORE'), '/home/runner/') !== 0
    || !is_file((string)getenv('MODX_CONFIG_CORE'))
    || strpos((string)getenv('RUNNER_TEMP'), '/home/runner/') !== 0) {
    fwrite(STDERR, "Refusing miniShop2 E2E outside disposable CI.\n");
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
if (!is_file($path . 'model/minishop2/minishop2.class.php')
    || !is_file($path . 'processors/mgr/settings/link/create.class.php')) {
    throw new RuntimeException('Pinned miniShop2 source was not staged');
}
// RU: Авторизованный администратор только из одноразовой штатной установки.
// EN: Use installed disposable administrator; never bypass processor ACLs.
$adminClass = $is2 ? 'modUser' : '\MODX\Revolution\modUser';
$admin = $modx->getObject($adminClass, 1);
if (!$admin || !$admin->isSudo()) {
    throw new RuntimeException('Disposable MODX sudo administrator not available');
}
$modx->user = $admin;
$modx->user->addSessionContext('mgr');
if (!$modx->hasPermission('mssetting_save')
    || !$modx->hasPermission('mssetting_list')) {
    throw new RuntimeException('Disposable admin lacks miniShop2 processor permission');
}
if (!$modx->addPackage('minishop2', $path . 'model/')) {
    throw new RuntimeException('Cannot load miniShop2 model');
}
if (!$modx->getManager()->createObjectContainer('msLink')) {
    throw new RuntimeException('Disposable msLink table setup failed');
}
require MODX_CORE_PATH . 'components/modxmcp/model/modxmcp.class.php';
require __DIR__ . '/test.cas-mcp-helper.php';
$api = new modxMCP($modx);
$checks = 0;
function linkRpc($action, array $data = array()) {
    global $api;
    return disposableCasRpc($api, $action, $data);
}
function linkCheck($condition, $label) {
    global $checks;
    if (!$condition) throw new RuntimeException('MINISHOP2_LINK_CAS_FAILED ' . $label);
    $checks++;
}
function linkRevision() {
    $state = linkRpc('get_site_state');
    return (int)$state['site_revision'];
}
function linkGuard() {
    return array('observed_site_revision' => linkRevision(),
        'strict_site_revision' => true, 'read_set' => array());
}
function linkRows($name) {
    $response = linkRpc('ms2_list_link_types', array('query' => $name, 'limit' => 0));
    $rows = isset($response['results']) && is_array($response['results'])
        ? $response['results'] : array();
    return array_values(array_filter($rows, function ($item) use ($name) {
        return is_array($item) && isset($item['name'])
            && (string)$item['name'] === $name;
    }));
}
function linkStale($action, array $request) {
    $before = linkRevision();
    $refused = false;
    try { linkRpc($action, $request); }
    catch (ModxMCPClientException $e) {
        $refused = strpos($e->getMessage(), 'STALE_STATE') !== false;
    }
    linkCheck($refused, $action . ' old CAS replay refused');
    linkCheck(linkRevision() === $before, $action . ' stale replay did not shift revision');
}
function linkMutation($action, array $args) {
    $before = linkRevision();
    $args['_runtime_preconditions'] = linkGuard();
    $result = linkRpc($action, $args);
    linkCheck(linkRevision() === $before + 1, $action . ' advanced CAS exactly once');
    return array($result, $args);
}
$id = 0;
$name = 'mcp_disposable_mslink_' . bin2hex(random_bytes(6));
try {
    list($created, $request) = linkMutation('ms2_create_link_type', array(
        'name' => $name, 'type' => 'one_to_one',
        'description' => 'test-only-before'
    ));
    $rows = linkRows($name);
    linkCheck(count($rows) === 1, 'created one link visible via MCP');
    $id = (int)($rows[0]['id'] ?? 0);
    linkCheck($id > 0, 'new link id readable');
    linkCheck((string)$rows[0]['description'] === 'test-only-before', 'created description readback');
    linkStale('ms2_create_link_type', $request);
    linkCheck(count(linkRows($name)) === 1, 'stale create created no duplicate');
    echo "CAS_MS2_CREATE_LINK_TYPE_INSTALLED_READBACK_STALE_OK\n";

    list($updated, $request) = linkMutation('ms2_update_link_type', array(
        'id' => $id, 'name' => $name, 'description' => 'test-only-after'
    ));
    $rows = linkRows($name);
    linkCheck(count($rows) === 1, 'updated link visible through MCP');
    linkCheck((string)$rows[0]['description'] === 'test-only-after', 'updated description readback');
    linkCheck((string)$rows[0]['type'] === 'one_to_one', 'update preserves type');
    linkStale('ms2_update_link_type', $request);
    $rows = linkRows($name);
    linkCheck(count($rows) === 1
        && (string)$rows[0]['description'] === 'test-only-after',
        'stale update did not overwrite link');
    echo "CAS_MS2_UPDATE_LINK_TYPE_INSTALLED_READBACK_STALE_OK\n";

    list($deleted, $request) = linkMutation('ms2_delete_link_type', array('id' => $id));
    linkCheck(count(linkRows($name)) === 0, 'deleted link absent through MCP');
    linkStale('ms2_delete_link_type', $request);
    linkCheck(count(linkRows($name)) === 0, 'stale delete did not recreate link');
    $id = 0;
    echo "CAS_MS2_DELETE_LINK_TYPE_INSTALLED_READBACK_STALE_OK\n";
} finally {
    if ($id > 0) {
        // RU: Удаляется только собственная фикстура в одноразовой базе.
        // EN: Cleanup limited to test-owned disposable row.
        try { linkRpc('ms2_delete_link_type', array('id' => $id)); }
        catch (Throwable $ignored) {}
    }
}
echo 'CAS_MS2_LINK_TYPE_INSTALLED_E2E_OK platform='
    . ($is2 ? 'modx2' : 'modx3') . ' checks=' . $checks . PHP_EOL;
