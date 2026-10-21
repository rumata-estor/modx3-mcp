<?php
// Isolated memory & correctness test for readTailLines streaming.

require_once __DIR__ . '/../core/components/modxmcp/src/Tools/ToolInterface.php';
require_once __DIR__ . '/../core/components/modxmcp/src/Tools/FilesystemSupport.php';
require_once __DIR__ . '/../core/components/modxmcp/src/Tools/ErrorLogReadTool.php';
require_once __DIR__ . '/../core/components/modxmcp/src/Tools/AuditLogReadTool.php';

$tmpFile = sys_get_temp_dir() . '/modxmcp-tail-test-' . bin2hex(random_bytes(6)) . '.log';
$fp = fopen($tmpFile, 'wb');
if (!$fp) {
    throw new RuntimeException('Cannot create temp log file');
}

// Generate 50,000 lines of sample logs
for ($i = 1; $i <= 50000; $i++) {
    fwrite($fp, "[2026-10-18 12:00:00] [ERROR] Line number " . $i . " with some dummy data to increase file size\n");
}
fclose($fp);

try {
    // 1. Correctness: read tail 10 lines
    $tail = \ModxMcp\Tools\FilesystemSupport::readTailLines($tmpFile, 10);
    if (count($tail) !== 10) {
        throw new RuntimeException('Expected 10 lines, got ' . count($tail));
    }
    if ($tail[9] !== '[2026-10-18 12:00:00] [ERROR] Line number 50000 with some dummy data to increase file size') {
        throw new RuntimeException('Last line mismatch: ' . $tail[9]);
    }
    if ($tail[0] !== '[2026-10-18 12:00:00] [ERROR] Line number 49991 with some dummy data to increase file size') {
        throw new RuntimeException('First tail line mismatch: ' . $tail[0]);
    }

    // 2. Filter correctness
    $filtered = \ModxMcp\Tools\FilesystemSupport::readTailLines($tmpFile, 5, function ($line) {
        if (strpos($line, '4999') !== false) {
            return 'MATCH: ' . $line;
        }
        return null;
    });
    if (count($filtered) !== 5) {
        throw new RuntimeException('Expected 5 filtered lines, got ' . count($filtered));
    }
    foreach ($filtered as $item) {
        if (strpos($item, 'MATCH:') !== 0) {
            throw new RuntimeException('Filter callback result mismatch');
        }
    }

    // 3. Small file / edge case (file with only 2 lines, limit 5)
    $smallFile = sys_get_temp_dir() . '/modxmcp-tail-small-' . bin2hex(random_bytes(6)) . '.log';
    file_put_contents($smallFile, "Line 1\nLine 2\n");
    $smallTail = \ModxMcp\Tools\FilesystemSupport::readTailLines($smallFile, 5);
    unlink($smallFile);
    if ($smallTail !== array('Line 1', 'Line 2')) {
        throw new RuntimeException('Small file tail mismatch: ' . var_export($smallTail, true));
    }

    // 4. Oversized line without newlines: memory must remain bounded
    $hugeLineFile = sys_get_temp_dir() . '/modxmcp-tail-huge-' . bin2hex(random_bytes(6)) . '.log';
    $fpHuge = fopen($hugeLineFile, 'wb');
    for ($k = 0; $k < 200; $k++) {
        fwrite($fpHuge, str_repeat('X', 8192)); // 1.6 MB with zero newlines
    }
    fclose($fpHuge);
    $hugeTail = \ModxMcp\Tools\FilesystemSupport::readTailLines($hugeLineFile, 2);
    unlink($hugeLineFile);
    if (empty($hugeTail)) {
        throw new RuntimeException('Oversized line reading returned empty');
    }

    echo "LOG_TAIL_PHP_OK\n";
} finally {
    if (file_exists($tmpFile)) {
        unlink($tmpFile);
    }
}
