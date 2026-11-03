<?php
/* Shared Python/PHP signed permit fixture; no MODX runtime or API credentials. */
require_once __DIR__ . '/../core/components/modxmcp/endpoint/permit-wire.php';
$claims = array(
    'version' => '1', 'audience' => 'modx-site-one', 'site_id' => 'site-one',
    'contour' => 'test', 'task_id' => 'TASK-20261103-100003-abcd',
    'plan_digest' => str_repeat('a', 64), 'operation_id' => 'op-1',
    'action' => 'update_element', 'target_digest' => str_repeat('b', 64),
    'parameters_digest' => str_repeat('c', 64),
    'request_digest' => str_repeat('d', 64),
    'nonce' => '0123456789abcdef0123456789abcdef',
    'expires_at' => '1794000000',
);
$key = str_repeat('K', 32);
$expected = '434ba4361defc2ee8a7289a552b3ca3a90b2c6a304800546a558ce7cf801c7fb';
if (ModxMcpPermitWire::sign($claims, $key) !== $expected ||
    !ModxMcpPermitWire::verify($claims, $expected, $key)) {
    throw new RuntimeException('python/php permit fixture mismatch');
}
foreach (array(
    'site_id' => 'other-site', 'contour' => 'production',
    'action' => 'delete_element', 'target_digest' => str_repeat('f', 64),
    'parameters_digest' => str_repeat('e', 64),
    'request_digest' => str_repeat('f', 64),
    'nonce' => str_repeat('f', 32)
) as $field => $value) {
    $forged = $claims;
    $forged[$field] = $value;
    if (ModxMcpPermitWire::verify($forged, $expected, $key)) {
        throw new RuntimeException('signed scope was changed: ' . $field);
    }
}
$bad = $claims;
$bad['extra_field'] = '1';
try {
    ModxMcpPermitWire::encode($bad);
    throw new RuntimeException('unrecognized field accepted');
} catch (InvalidArgumentException $e) { /* expected */ }
$bad = $claims;
$bad['action'] = "update_element\nadmin=true";
try {
    ModxMcpPermitWire::encode($bad);
    throw new RuntimeException('newline injection accepted');
} catch (InvalidArgumentException $e) { /* expected */ }
if (ModxMcpPermitWire::verify($claims, str_repeat('0', 64), $key)) {
    throw new RuntimeException('invalid MAC accepted');
}
echo "MODX_PERMIT_WIRE_V1_OK\n";
