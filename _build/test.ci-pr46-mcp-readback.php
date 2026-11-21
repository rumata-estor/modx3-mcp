<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Проверка «ci-pr46-mcp-readback»; тестовый код не запускается на production.
 * EN: «ci-pr46-mcp-readback» regression test; not executed in production.
 */
/**
 * Independent readback using a fresh PHP process and the real MODX MCP
 * dispatcher. No direct database calls; only gets TV and site_revision.
 */
if (PHP_SAPI !== 'cli' || getenv('MODXMCP_DISPOSABLE_CI') !== '1'
    || strpos((string)getenv('MODX_CONFIG_CORE'), '/home/runner/') !== 0
    || !is_file((string)getenv('MODX_CONFIG_CORE')) || $argc !== 2) {
    fwrite(STDERR, "MCP readback allowed only on disposable CI\n");
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
$api = new modxMCP($modx);
$rid = (int)$argv[1];
if ($rid<=0) exit(3);
$raw = array(
    'tvs'=>$api->processRequest('get_resource_tvs', '', array('resource_id'=>$rid)),
    'state'=>$api->processRequest('get_site_state', '', array()),
);
echo json_encode($raw, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
