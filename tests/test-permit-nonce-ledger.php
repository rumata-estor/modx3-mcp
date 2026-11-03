<?php
/* Offline replay tests, without any real MODX site or HTTP traffic. */
require_once __DIR__ . '/../core/components/modxmcp/endpoint/permit-nonce-ledger.php';
$base = getenv('HOME');
if (!is_string($base) || $base === '' || !is_dir($base)) {
    throw new RuntimeException('test requires a private HOME');
}
$directory = $base . '/.modxmcp-nonce-test-' . bin2hex(random_bytes(8));
if (!mkdir($directory, 0700)) {
    throw new RuntimeException('cannot create test directory');
}
try {
    $ledger = new ModxMcpPermitNonceLedger($directory);
    $nonce = '0123456789abcdef0123456789abcdef';
    $ledger->reserve($nonce);
    try {
        $ledger->reserve($nonce);
        throw new RuntimeException('replayed nonce accepted');
    } catch (RuntimeException $e) {
        if ($e->getMessage() === 'replayed nonce accepted') {
            throw $e;
        }
    }
    $ledger->reserve(str_repeat('f', 32));
    try {
        $ledger->reserve('../' . $nonce);
        throw new RuntimeException('invalid nonce accepted');
    } catch (InvalidArgumentException $e) { /* expected */ }
    chmod($directory, 0777);
    try {
        new ModxMcpPermitNonceLedger($directory);
        throw new RuntimeException('unsafe nonce directory accepted');
    } catch (RuntimeException $e) {
        if ($e->getMessage() === 'unsafe nonce directory accepted') {
            throw $e;
        }
    }
    chmod($directory, 0700);
} finally {
    foreach (glob($directory . '/*.used') as $entry) {
        unlink($entry);
    }
    rmdir($directory);
}
echo "MODX_NONCE_LEDGER_OK\n";
