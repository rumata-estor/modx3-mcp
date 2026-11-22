<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Защита секретов в диагностических данных и её автоматическая проверка.
 * EN: Diagnostic secret redaction and its automated verification.
 */
require_once __DIR__ . '/../core/components/modxmcp/src/Security/SecretRedactor.php';
use ModxMcp\Security\SecretRedactor;
$input = array('password' => 'fake-value-a', 'nested' => array('api_token' => 'fake-value-b', 'message' => 'Connection rejected password=fake-value-c'), 'status' => 'failed');
$out = SecretRedactor::value($input);
if ($out['password'] !== '[REDACTED]' || $out['nested']['api_token'] !== '[REDACTED]') { throw new RuntimeException('key redaction failed'); }
if (strpos($out['nested']['message'], 'fake-value-c') !== false) { throw new RuntimeException('text redaction failed'); }
if ($out['status'] !== 'failed') { throw new RuntimeException('ordinary diagnostic altered'); }
foreach (array('Bearer fake-token-abc', 'Basic ZmFrZQ==', 'https://bob:fakepass@example.test/') as $value) {
    if (strpos(SecretRedactor::text($value), 'fake') !== false) { throw new RuntimeException('secret exposed'); }
}
echo "SECRET_REDACTION_PHP_OK\n";
