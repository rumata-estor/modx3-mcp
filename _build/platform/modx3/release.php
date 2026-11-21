<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Служебный сценарий сборки/проверки MODX MCP: release.
 * EN: MODX MCP build/verification helper: release.
 */
return array(
    'platform' => 'modx3',
    'package_name' => 'MODX3MCP',
    'namespace' => 'modxmcp',
    'requires' => array('modx' => '>=3.0.0,<4.0.0'),
    'bootstrap' => __DIR__ . '/bootstrap.php',
);
