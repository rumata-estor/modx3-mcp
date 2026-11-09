<?php

class ModxMCPClientException extends Exception {}

require_once __DIR__ . '/../core/components/modxmcp/src/Tools/ProtectedSettingsGuard.php';

function mustRejectProtectedSetting($label, $processor, array $properties, $processorsPath = '')
{
    try {
        \ModxMcp\Tools\ProtectedSettingsGuard::assertAllowed(
            $processor,
            $properties,
            $processorsPath
        );
    } catch (ModxMCPClientException $exception) {
        return;
    }
    throw new RuntimeException('Expected rejection: ' . $label);
}

$blocked = array(
    array('context/setting/create', array('key' => 'modxmcp.enabled')),
    array('/Context\\Setting\\Update/', array('key' => '  ModxMCP.allowed_ips  ')),
    array('system/settings/remove', array('key' => 'modxmcp.api_token')),
    array('system/settings/updatefromgrid', array(
        'data' => json_encode(array('key' => 'modxmcp.require_https', 'value' => 0)),
    )),
    array('MODX\\Revolution\\Processors\\System\\Settings\\UpdateFromGrid', array(
        'data' => json_encode(array('oldkey' => 'MODXMCP.enabled', 'key' => 'site_name')),
    )),
    array('MODX\\Revolution\\Processors\\Context\\Setting\\Remove', array(
        'key' => 'modxmcp.enabled',
    )),
    array('CONTEXT//SETTING///UPDATE', array('key' => 'modxmcp.enabled')),
    array('system/other/../settings/update', array('key' => 'modxmcp.enabled')),
    array('security/user/setting/create', array('key' => 'modxmcp.enabled')),
    array('MODX\\Revolution\\Processors\\Security\\Group\\Setting\\Update', array(
        'key' => 'modxmcp.enabled',
    )),
    array('system/settings/updatefromgrid', array(
        'data' => array(array('key' => 'site_name'), array('key' => 'modxmcp.enabled')),
    )),
    array('context/update', array(
        'key' => 'web',
        'settings' => json_encode(array(array('key' => 'modxmcp.enabled', 'value' => 0))),
    )),
    array('MODX\\Revolution\\Processors\\Context\\Update', array(
        'key' => 'web',
        'settings' => array(array('key' => 'site_name'), array('key' => 'modxmcp.allowed_ips')),
    )),
);
foreach ($blocked as $case) {
    mustRejectProtectedSetting($case[0], $case[0], $case[1]);
}

mustRejectProtectedSetting(
    'processors_path override',
    'update.class.php',
    array('key' => 'modxmcp.enabled', 'value' => 0),
    '/core/model/modx/processors/system/settings/'
);

\ModxMcp\Tools\ProtectedSettingsGuard::assertAllowed(
    'system/settings/update',
    array('key' => 'site_name', 'value' => 'MODX')
);
\ModxMcp\Tools\ProtectedSettingsGuard::assertAllowed(
    'custom/settings/update',
    array('key' => 'modxmcp.enabled', 'value' => 0)
);
\ModxMcp\Tools\ProtectedSettingsGuard::assertAllowed(
    'update',
    array('key' => 'modxmcp.enabled', 'value' => 0),
    '/custom/processors/'
);
\ModxMcp\Tools\ProtectedSettingsGuard::assertAllowed(
    'context/duplicate',
    array('key' => 'web', 'newkey' => 'web-copy')
);
\ModxMcp\Tools\ProtectedSettingsGuard::assertAllowed(
    'MODX\\Revolution\\Processors\\Security\\User\\Duplicate',
    array('id' => 1, 'new_username' => 'copy-user')
);

foreach (array(
    __DIR__ . '/../core/components/modxmcp/model/modxmcp.class.php',
    __DIR__ . '/../core/components/modxmcp/legacy/modx2/modxmcp.class.php',
) as $fallbackFile) {
    $fallback = file_get_contents($fallbackFile);
    if ($fallback === false
        || strpos($fallback, '$this->assertRunProcessorProtectedSettings($processor, $props, $processorsPath);') === false
        || strpos($fallback, 'private function assertRunProcessorProtectedSettings') === false
        || strpos($fallback, 'private function runProcessorPayloadHasProtectedKey') === false
        || strpos($fallback, 'private function normalizeRunProcessorPath') === false
        || strpos($fallback, "context/update") === false
    ) {
        throw new RuntimeException('Missing fallback run_processor protection: ' . $fallbackFile);
    }
}

echo "RUN_PROCESSOR_SETTINGS_GUARD_PHP_OK\n";
