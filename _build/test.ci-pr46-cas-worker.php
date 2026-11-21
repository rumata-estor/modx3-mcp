<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Проверка «ci-pr46-cas-worker»; тестовый код не запускается на production.
 * EN: «ci-pr46-cas-worker» regression test; not executed in production.
 */
/**
 * Isolated CAS race worker for temporary MODX 2/3 CI database fixtures.
 * No credentials/production sites; both writers use the same observed CAS.
 */
if (PHP_SAPI !== 'cli' || getenv('MODXMCP_DISPOSABLE_CI') !== '1'
    || strpos((string)getenv('MODX_CONFIG_CORE'), '/home/runner/') !== 0
    || $argc !== 8) {
    fwrite(STDERR, "Refusing to run CAS worker outside disposable CI.\n");
    exit(3);
}
$config = getenv('MODX_CONFIG_CORE');
if (!is_file($config)) exit(3);
require_once $config;
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
$api = new modxMCP($modx);
$rid = (int)$argv[1];
$name = (string)$argv[2];
$before = (string)$argv[3];
$after = (string)$argv[4];
$revision = (int)$argv[5];
$ready = (string)$argv[6];
$gate = (string)$argv[7];
if ($rid <= 0 || $name === '' || $revision < 0
    || strpos($ready, '/home/runner/') !== 0
    || strpos($gate, '/home/runner/') !== 0) {
    exit(3);
}
file_put_contents($ready, "ready");
$deadline = microtime(true) + 15;
while (!is_file($gate) && microtime(true) < $deadline) {
    usleep(10000);
}
if (!is_file($gate)) {
    fwrite(STDERR, "CAS_WORKER_GATE_TIMEOUT\n");
    exit(4);
}
try {
    $result = $api->processRequest('update_resource_tvs', '', array(
        'resource_id' => $rid,
        'tvs' => array($name => $after),
        '_runtime_preconditions' => array(
            'observed_site_revision' => $revision,
            'strict_site_revision' => true,
            'read_set' => array(),
            'expected_tvs' => array($name => $before),
        )
    ));
    if (!isset($result['_site_revision'])
        || (int)$result['_site_revision'] !== $revision + 1) {
        throw new RuntimeException('Invalid revision in winning writer.');
    }
    echo "CAS_WORKER_OK\n";
} catch (ModxMCPClientException $e) {
    if (strpos($e->getMessage(), 'STALE_STATE') === false) {
        throw $e;
    }
    echo "CAS_WORKER_STALE\n";
}
