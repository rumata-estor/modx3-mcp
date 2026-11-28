<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Изолированные MODX 2/3: прямой guard старого маршрута и запрет обхода CAS.
 * EN: Disposable MODX 2/3: exercise legacy guard directly and deny CAS fallback.
 */
if (PHP_SAPI !== 'cli' || getenv('MODXMCP_DISPOSABLE_CI') !== '1'
    || strpos((string)getenv('MODX_CONFIG_CORE'), '/home/runner/') !== 0
    || !is_file((string)getenv('MODX_CONFIG_CORE'))) {
    fwrite(STDERR, "Refusing legacy guard test outside disposable GitHub CI.\n");
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
$modx->setOption('modxmcp.allow_run_processor', true); // in-memory only
$reflection = new ReflectionClass($api);
if ($reflection->hasMethod('runProcessorPassthrough')) throw new RuntimeException('Legacy processor handler remains');
$checks = 0;
$cases = array(
    array('system/settings/update', array('key'=>'modxmcp.api_token','value'=>'BAD')),
    array('system/setting/update', array('key'=>' MODXMCP.enabled ','value'=>'0')),
    array('context/setting/create', array('key'=>'modxmcp.require_https','value'=>'0')),
    array('context/update', array('key'=>'web','settings'=>json_encode(array(array('key'=>'modxmcp.enabled','value'=>0))))),
    array('system/settings/updatefromgrid', array('data'=>json_encode(array('oldkey'=>'modxmcp.enabled','key'=>'site_name')))),
    array('security/user/setting/create', array('key'=>'modxmcp.enabled')),
);
foreach ($cases as $case) {
    $denied = false;
    try {
        \ModxMcp\Security\ProcessorAccessPolicy::authorize(array('processor'=>$case[0], 'properties'=>$case[1]), 'privileged', '', $case[0]);
    } catch (RuntimeException $e) {
        $denied = strpos($e->getMessage(), 'AUTH_DENIED:') === 0;
    }
    if (!$denied) { throw new RuntimeException('LEGACY_GUARD_FAIL: '.$case[0]); }
    $checks++;
}
$denied = false;
try {
    \ModxMcp\Security\ProcessorAccessPolicy::authorize(array(
        'processor'=>'update.class.php',
        'processors_path'=>'/core/model/modx/processors/system/settings/',
        'properties'=>array('key'=>'modxmcp.enabled','value'=>0),
    ), 'privileged', '', 'system/settings/update');
} catch (RuntimeException $e) {
    $denied = strpos($e->getMessage(), 'AUTH_DENIED:') === 0;
}
if (!$denied) { throw new RuntimeException('LEGACY_GUARD_FAIL: processors_path'); }
$checks++;
// RU: Штатный диспетчер не должен переходить к старому обработчику при отказе modular runtime.
// EN: Public dispatch must refuse to fall back to legacy when modular runtime is unavailable.
$runtime = $reflection->getProperty('modularRuntime');
$runtime->setAccessible(true);
$runtime->setValue($api, null);
$denied = false;
try {
    $api->processRequest('run_processor', '', array('processor'=>'system/settings/update'));
} catch (ModxMCPClientException $e) {
    $denied = strpos($e->getMessage(), 'CAS_RUNTIME_REQUIRED:') === 0;
}
if (!$denied) { throw new RuntimeException('LEGACY_GUARD_FAIL: modular CAS fallback'); }
$checks++;
echo 'LEGACY_GUARD_DISPOSABLE_MODX_OK platform=' . ($is2?'modx2':'modx3')
    . ' checks='.$checks.PHP_EOL;
