<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Жёсткое прерывание подписанного запроса — только disposable CI.
 * EN: Killable real signed MCP worker, disposable CI only.
 */
if (PHP_SAPI !== 'cli'
    || getenv('MODXMCP_DISPOSABLE_CI') !== '1'
    || strpos((string)getenv('MODX_CONFIG_CORE'), '/home/runner/') !== 0
    || !is_file((string)getenv('MODX_CONFIG_CORE'))
    || $argc !== 2
    || strpos((string)$argv[1], '/home/runner/') !== 0
    || !is_file((string)$argv[1])) {
    fwrite(STDERR, "Refusing kill worker outside disposable CI.\n");
    exit(3);
}
$input = json_decode((string)file_get_contents($argv[1]), true);
if (!is_array($input) || ($input['action'] ?? null) !== 'create_element'
    || !isset($input['raw_body'], $input['permit'], $input['data'])
    || !is_array($input['permit']) || !is_array($input['data'])) {
    exit(4);
}
require getenv('MODX_CONFIG_CORE');
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
// Trigger on journal SUCCEEDED blocks after the resource is committed;
// parent uses proc_terminate(..., 9) on this worker, never on MODX services.
try {
    $api->processRequest(
        'create_element', '', $input['data'],
        array('raw_body' => $input['raw_body'], 'permit' => $input['permit'])
    );
    fwrite(STDERR, "Unexpected successful response; parent did not kill worker.\n");
    exit(5);
} catch (Throwable $error) {
    // Never print raw native processor errors or signed details.
    fwrite(STDERR, "Worker exited before expected SIGKILL.\n");
    exit(6);
}
