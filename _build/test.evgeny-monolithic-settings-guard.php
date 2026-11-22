<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Регрессионная проверка защитных правил в обоих монолитных обработчиках (PR #54, @AgelxNash).
 * EN: Regression test for protected settings in both legacy dispatchers (PR #54, @AgelxNash).
 */
$files = [
    __DIR__ . '/../core/components/modxmcp/model/modxmcp.class.php',
    __DIR__ . '/../core/components/modxmcp/legacy/modx2/modxmcp.class.php',
];
foreach ($files as $file) {
    $body = file_get_contents($file);
    foreach ([
        '$this->assertRunProcessorProtectedSettings($processor, $props, $processorsPath);',
        'private function assertRunProcessorProtectedSettings',
        'private function runProcessorPayloadHasProtectedKey',
        'private function normalizeRunProcessorPath',
        "'delete_context_setting'",
        "context_settings",
    ] as $needle) {
        if (strpos($body, $needle) === false) {
            throw new RuntimeException('Missing legacy settings guard: ' . $needle . ' in ' . $file);
        }
    }
}
echo "EVGENY_MONOLITHIC_SETTINGS_GUARD_OK\n";
