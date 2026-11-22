<?php

class ModxMCPClientException extends Exception {}

require_once __DIR__ . '/../core/components/modxmcp/src/Tools/ToolInterface.php';
require_once __DIR__ . '/../core/components/modxmcp/src/Tools/SystemSettingListTool.php';

class TokenMaskSettingStub {
    private $data;
    public function __construct(array $data) { $this->data = $data; }
    public function get($k) { return isset($this->data[$k]) ? $this->data[$k] : null; }
}

$cases = array(
    array('abcdef1234567890abcdef', 'abcdef…cdef', 'long token preview'),
    array('abc123', '********', 'short token fully masked'),
    array('abcdefghijklmnopq', 'abcdef…nopq', 'boundary token preview'),
);
foreach ($cases as $case) {
    $norm = \ModxMcp\Tools\SystemSettingListTool::normalize(new TokenMaskSettingStub(array(
        'id' => 1, 'key' => 'modxmcp.api_token', 'value' => $case[0], 'xtype' => 'textfield', 'namespace' => 'modxmcp', 'area' => 'security',
    )));
    if ($norm['value'] !== $case[1]) {
        throw new RuntimeException($case[2] . ': ' . $norm['value']);
    }
}

$plain = \ModxMcp\Tools\SystemSettingListTool::normalize(new TokenMaskSettingStub(array(
    'id' => 2, 'key' => 'site_start', 'value' => '1', 'xtype' => 'textfield', 'namespace' => 'core', 'area' => 'site',
)));
if ($plain['value'] !== '1') {
    throw new RuntimeException('Non-token setting must stay untouched: ' . $plain['value']);
}

foreach (array(
    __DIR__ . '/../core/components/modxmcp/model/modxmcp.class.php',
    __DIR__ . '/../core/components/modxmcp/legacy/modx2/modxmcp.class.php',
) as $monolithFile) {
    $monolith = file_get_contents($monolithFile);
    if ($monolith === false
        || strpos($monolith, "if (\$key === 'modxmcp.api_token' && is_string(\$value) && \$value !== '')") === false
        || strpos($monolith, 'strlen($value) > 16') === false
    ) {
        throw new RuntimeException('Monolithic normalizeSystemSetting does not mask the API token: ' . $monolithFile);
    }
}

echo "TOKEN_MASKING_PHP_OK\n";
