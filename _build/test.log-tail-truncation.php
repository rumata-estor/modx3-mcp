<?php

require_once __DIR__ . '/../core/components/modxmcp/src/Tools/ToolInterface.php';
require_once __DIR__ . '/../core/components/modxmcp/src/Tools/FilesystemSupport.php';
require_once __DIR__ . '/../core/components/modxmcp/src/Tools/ErrorLogReadTool.php';
require_once __DIR__ . '/../core/components/modxmcp/src/Tools/AuditLogReadTool.php';

$binaryLineFile = null;
$boundedLineFile = null;
$hugeLineFile = null;
$toolRoot = null;
$utf8LineFile = null;
$windowFile = null;

function demandTail($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $marker = '[MODX MCP: line truncated; showing last 8192 bytes] ';

    $hugeLineFile = sys_get_temp_dir() . '/modxmcp-tail-huge-' . bin2hex(random_bytes(6)) . '.log';
    $fpHuge = fopen($hugeLineFile, 'wb');
    for ($i = 0; $i < 200; $i++) {
        fwrite($fpHuge, str_repeat('X', 8192));
    }
    fclose($fpHuge);

    $hugeMetadata = array();
    $hugeTail = \ModxMcp\Tools\FilesystemSupport::readTailLines(
        $hugeLineFile,
        2,
        null,
        $hugeMetadata
    );
    demandTail(count($hugeTail) === 1 && is_string($hugeTail[0]), 'Oversized line must return one string');
    demandTail(strpos($hugeTail[0], $marker) === 0, 'Oversized line marker is missing');
    demandTail(strlen($hugeTail[0]) === strlen($marker) + 8192, 'Oversized line fragment length is invalid');
    demandTail($hugeMetadata['truncated_lines'] === 1, 'Oversized line count is invalid');
    demandTail($hugeMetadata['result_complete'] === false, 'Oversized line was reported complete');

    $filteredMetadata = array();
    $filtered = \ModxMcp\Tools\FilesystemSupport::readTailLines(
        $hugeLineFile,
        2,
        function ($line) { return null; },
        $filteredMetadata
    );
    demandTail($filtered === array(), 'Filtered oversized line returned a value');
    demandTail($filteredMetadata['truncated_lines'] === 1, 'Filtered truncation was hidden');
    demandTail($filteredMetadata['result_complete'] === false, 'Filtered truncation was reported complete');

    $missingMetadata = array();
    $missing = \ModxMcp\Tools\FilesystemSupport::readTailLines(
        sys_get_temp_dir() . '/modxmcp-tail-missing-' . bin2hex(random_bytes(6)),
        1,
        null,
        $missingMetadata
    );
    demandTail($missing === array(), 'Missing file returned data');
    demandTail($missingMetadata === array(
        'result_complete' => true,
        'scanned_bytes' => 0,
        'truncated_lines' => 0,
    ), 'Missing-file metadata is invalid');

    $directoryMetadata = array();
    $directoryResult = \ModxMcp\Tools\FilesystemSupport::readTailLines(
        sys_get_temp_dir(),
        1,
        null,
        $directoryMetadata
    );
    demandTail($directoryResult === array(), 'Directory path returned log data');
    demandTail($directoryMetadata['result_complete'] === false, 'I/O failure was reported complete');

    $utf8LineFile = sys_get_temp_dir() . '/modxmcp-tail-utf8-' . bin2hex(random_bytes(6)) . '.log';
    file_put_contents($utf8LineFile, str_repeat('€', 200000));
    $utf8Metadata = array();
    $utf8Tail = \ModxMcp\Tools\FilesystemSupport::readTailLines(
        $utf8LineFile, 1, null, $utf8Metadata
    );
    demandTail(count($utf8Tail) === 1, 'UTF-8 oversized line returned wrong count');
    demandTail(preg_match('//u', $utf8Tail[0]) === 1, 'UTF-8 oversized line became invalid');
    demandTail(json_encode($utf8Tail, JSON_UNESCAPED_UNICODE) !== false, 'UTF-8 tail cannot be encoded as JSON');

    $binaryLineFile = sys_get_temp_dir() . '/modxmcp-tail-binary-' . bin2hex(random_bytes(6)) . '.log';
    file_put_contents($binaryLineFile, str_repeat("\xFF", 524289));
    $binaryMetadata = array();
    $binaryTail = \ModxMcp\Tools\FilesystemSupport::readTailLines(
        $binaryLineFile, 1, null, $binaryMetadata
    );
    demandTail(count($binaryTail) === 1, 'Binary oversized line returned wrong count');
    demandTail(strpos($binaryTail[0], '[MODX MCP: line truncated; base64 of last 8192 bytes] ') === 0, 'Binary tail was not encoded');
    demandTail(json_encode($binaryTail, JSON_UNESCAPED_UNICODE) !== false, 'Binary tail cannot be encoded as JSON');

    $boundedLineFile = sys_get_temp_dir() . '/modxmcp-tail-bounded-' . bin2hex(random_bytes(6)) . '.log';
    file_put_contents($boundedLineFile, "BEFORE\n" . str_repeat('Z', 524289) . "\nAFTER\n");
    $boundedMetadata = array();
    $boundedTail = \ModxMcp\Tools\FilesystemSupport::readTailLines(
        $boundedLineFile,
        3,
        null,
        $boundedMetadata
    );
    demandTail(count($boundedTail) === 3, 'Bounded oversized fixture returned wrong line count');
    demandTail($boundedTail[0] === 'BEFORE' && $boundedTail[2] === 'AFTER', 'Neighbor lines changed');
    demandTail(strpos($boundedTail[1], $marker) === 0, 'Bounded oversized line marker is missing');
    demandTail($boundedMetadata['truncated_lines'] === 1, 'Bounded oversized line was not counted once');
    demandTail($boundedMetadata['result_complete'] === false, 'Bounded oversized line was reported complete');

    $toolRoot = sys_get_temp_dir() . '/modxmcp-tail-tools-' . bin2hex(random_bytes(6));
    $auditDir = $toolRoot . '/components/modxmcp/logs';
    $errorDir = $toolRoot . '/cache/logs';
    demandTail(mkdir($auditDir, 0700, true), 'Cannot create audit fixture directory');
    demandTail(mkdir($errorDir, 0700, true), 'Cannot create error-log fixture directory');
    $longJson = '{"action":"target","payload":"' . str_repeat('Q', 524289) . '"}';
    file_put_contents(
        $auditDir . '/audit.log',
        json_encode(array('action' => 'target', 'id' => 1)) . "\n"
        . $longJson . "\n"
        . json_encode(array('action' => 'target', 'id' => 2)) . "\n"
    );
    file_put_contents($errorDir . '/error.log', "BEFORE\n" . str_repeat('E', 524289) . "\nAFTER\n");
    $context = new class($toolRoot) {
        private $modx;
        public function __construct($root) {
            $this->modx = new class($root) {
                private $root;
                public function __construct($root) { $this->root = $root; }
                public function getOption($key) { return $key === 'core_path' ? $this->root . '/' : null; }
            };
        }
        public function modx() { return $this->modx; }
    };

    $audit = (new \ModxMcp\Tools\AuditLogReadTool())->execute(
        $context,
        array('action' => 'target', 'limit' => 3)
    );
    demandTail($audit['total'] === 2, 'Audit tool returned wrong entry count');
    demandTail($audit['entries'][0]['id'] === 1 && $audit['entries'][1]['id'] === 2, 'Audit entries changed');
    demandTail($audit['truncated_lines'] === 1 && $audit['result_complete'] === false, 'Audit truncation metadata is invalid');
    foreach ($audit['entries'] as $entry) {
        demandTail(is_array($entry), 'Audit tool returned a non-array entry');
    }

    $error = (new \ModxMcp\Tools\ErrorLogReadTool())->execute($context, array('limit' => 3));
    demandTail($error['total'] === 3, 'Error-log tool returned wrong line count');
    demandTail(strpos($error['lines'][1], $marker) === 0, 'Error-log truncation marker is missing');
    demandTail($error['truncated_lines'] === 1 && $error['result_complete'] === false, 'Error-log metadata is invalid');

    $windowFile = sys_get_temp_dir() . '/modxmcp-tail-window-' . bin2hex(random_bytes(6)) . '.log';
    $fpWindow = fopen($windowFile, 'wb');
    fwrite($fpWindow, "EARLY_MATCH\n");
    for ($i = 0; $i < 2050; $i++) {
        fwrite($fpWindow, str_repeat('Y', 8191) . "\n");
    }
    fclose($fpWindow);
    $windowMetadata = array();
    $matches = \ModxMcp\Tools\FilesystemSupport::readTailLines(
        $windowFile,
        1,
        function ($line) { return $line === 'EARLY_MATCH' ? $line : null; },
        $windowMetadata
    );
    demandTail($matches === array(), 'Match beyond scan window was unexpectedly returned');
    demandTail($windowMetadata['result_complete'] === false, 'Scan-window exhaustion was hidden');
    demandTail($windowMetadata['scanned_bytes'] === 16777216, 'Scan-window byte count is invalid');

    echo "LOG_TAIL_TRUNCATION_PHP_OK\n";
} finally {
    foreach (array(
        $binaryLineFile,
        $boundedLineFile,
        $hugeLineFile,
        $utf8LineFile,
        $windowFile,
    ) as $file) {
        if ($file !== null && file_exists($file)) {
            unlink($file);
        }
    }
    if ($toolRoot !== null && is_dir($toolRoot)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($toolRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($toolRoot);
    }
}
