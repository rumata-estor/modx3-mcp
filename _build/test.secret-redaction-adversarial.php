<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Защита секретов в диагностических данных и её автоматическая проверка.
 * EN: Diagnostic secret redaction and its automated verification.
 */
require __DIR__ . '/../core/components/modxmcp/src/Security/SecretRedactor.php';
$cases = array(
 'password=FAKE_A_123',
 'password: FAKE_B_123',
 '"password":"FAKE_C_123"',
 'X-MCP-Token: FAKE_D_123',
 'Authorization: Bearer FAKE_E_123',
 'Set-Cookie: sid=FAKE_F_123',
 'mysql://tester:FAKE_G_123@localhost/db',
 'api_key=FAKE_H_123',
 'secret_key: FAKE_I_123',
 'credentials=FAKE_J_123',
 'password = "FAKE_K_123"',
 'token=FAKE_L_123&next=1'
);
$failed = 0;
foreach ($cases as $i => $input) {
 $out = \ModxMcp\Security\SecretRedactor::text($input);
 $safe = strpos($out, 'FAKE_') === false;
 if (!$safe) { $failed++; }
 echo ($safe ? 'PASS' : 'FAIL') . ' case=' . ($i + 1) . PHP_EOL;
}
if ($failed) { exit(1); }
echo 'SECRET_REDACTION_PHP_ADVERSARIAL_OK ' . count($cases) . PHP_EOL;
