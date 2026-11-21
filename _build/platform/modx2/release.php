<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Служебный сценарий сборки/проверки MODX MCP: release.
 * EN: MODX MCP build/verification helper: release.
 */
return array(
    'platform' => 'modx2',
    'package_name' => 'MODXMCP',
    'namespace' => 'modxmcp',
    'requires' => array('modx' => '>=2.8.0,<3.0.0'),
    'bootstrap' => __DIR__ . '/bootstrap.php',
);
