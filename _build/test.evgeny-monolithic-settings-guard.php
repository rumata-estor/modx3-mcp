<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Регрессионная проверка защитных правил в обоих монолитных обработчиках (PR #54, @AgelxNash).
 * EN: Regression test for protected settings in both legacy dispatchers (PR #54, @AgelxNash).
 */
$base = __DIR__ . '/../core/components/modxmcp/';
foreach (array('model/modxmcp.class.php', 'legacy/modx2/modxmcp.class.php') as $file) {
    if (!is_file($base.$file)) continue;
    $body = file_get_contents($base.$file);
    if (strpos($body, 'use \\ModxMcp\\Core\\Connector;') === false || strpos($body, 'function runProcessorPassthrough') !== false) {
        throw new RuntimeException('Legacy dispatcher still present: '.$file);
    }
}
require_once $base.'src/Security/ProcessorAccessPolicy.php';
foreach (array('system/settings/update','system/setting/update','context/setting/create','context/update','security/user/setting/create') as $processor) {
    try {
        \ModxMcp\Security\ProcessorAccessPolicy::authorize(array('processor'=>$processor,'properties'=>array('key'=>'modxmcp.enabled')), 'privileged', '', $processor);
    } catch (RuntimeException $e) {
        if (strpos($e->getMessage(), 'AUTH_DENIED:') === 0) continue;
        throw $e;
    }
    throw new RuntimeException('Protected processor allowed: '.$processor);
}
echo "EVGENY_MONOLITHIC_SETTINGS_GUARD_OK\n";
