<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Вспомогательная логика «Filesystem» для инструментов MODX MCP.
 * EN: Shared «Filesystem» implementation used by MODX MCP tools.
 */
namespace ModxMcp\Tools;

class FilesystemSupport
{
    public static function normalizeRelativePath($path)
    {
        $path = str_replace('\\', '/', trim((string) $path));
        $path = trim($path, '/');
        if ($path === '') {
            return '';
        }

        $parts = array();
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                throw new \ModxMCPClientException('Path traversal is not allowed.');
            }
            $parts[] = $part;
        }
        return implode('/', $parts);
    }

    public static function joinPath($rootPath, $relativePath)
    {
        $absolutePath = $rootPath;
        if ($relativePath !== '') {
            $absolutePath .= DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        }

        $rootReal = realpath($rootPath);
        if ($rootReal === false) {
            $rootReal = self::normalizePath($rootPath);
        }

        $targetDir = file_exists($absolutePath)
            ? realpath($absolutePath)
            : realpath(dirname($absolutePath));
        if ($targetDir === false) {
            $targetDir = self::normalizePath(dirname($absolutePath));
        }

        if (!self::pathStartsWith($targetDir, $rootReal)) {
            throw new \ModxMCPClientException('Resolved path is outside of allowed root.');
        }

        return self::normalizePath($absolutePath);
    }

    /**
     * RU: Для чтения исходников компонента используем последовательный
     * RU: openat(O_NOFOLLOW) от корня файловой системы; не открываем файл
     * RU: по имени после отдельной проверки realpath.
     * EN: Read component source through pinned dirfds and O_NOFOLLOW.
     * EN: Missing Python/POSIX support fails closed; no PHP path fallback.
     */
    public static function readComponentFileAnchored($modx, $componentRoot, $relativePath, array $data)
    {
        $maxAllowed = (int)$modx->getOption('modxmcp.max_read_bytes', null, 262144);
        if ($maxAllowed <= 0) { $maxAllowed = 262144; }
        // Bound JSON/stdout allocations even when the admin raises the limit.
        $maxAllowed = min($maxAllowed, 4194304);
        $offset = isset($data['offset']) ? max(0, (int)$data['offset']) : 0;
        $wanted = isset($data['bytes']) ? (int)$data['bytes']
            : (isset($data['length']) ? (int)$data['length'] : $maxAllowed);
        if ($wanted <= 0 || $wanted > $maxAllowed) {
            $wanted = $maxAllowed;
        }

        $helper = dirname(__DIR__) . '/Security/component_read_guard.py';
        if (!function_exists('proc_open') || !is_file($helper)) {
            throw new \ModxMCPClientException(
                'MANUAL_REVIEW_REQUIRED: secure component-file reader unavailable;'
                . ' install Python 3 and enable proc_open.'
            );
        }
        $command = array(
            'python3', $helper, (string)$componentRoot, (string)$relativePath,
            (string)$offset, (string)$wanted
        );
        $spec = array(
            0 => array('pipe', 'r'),
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w'),
        );
        $pipes = array();
        try {
            $process = @proc_open($command, $spec, $pipes, null, null,
                array('bypass_shell' => true));
        } catch (\Throwable $error) {
            $process = false;
        }
        if (!is_resource($process)) {
            throw new \ModxMCPClientException(
                'MANUAL_REVIEW_REQUIRED: secure component-file reader cannot start.'
            );
        }
        fclose($pipes[0]);
        // Python caps raw reads at 4 MiB; JSON/base64 can be larger.
        $response = stream_get_contents($pipes[1], 8388608);
        fclose($pipes[1]);
        // Read/discard stderr; do not reveal paths or internal failures.
        stream_get_contents($pipes[2], 8192);
        fclose($pipes[2]);
        $exit = proc_close($process);
        $decoded = is_string($response) ? json_decode($response, true) : null;
        if ($exit !== 0 || !is_array($decoded)
            || !isset($decoded['size'], $decoded['offset'],
                $decoded['returned_bytes'], $decoded['encoding'], $decoded['content'])
            || !array_key_exists('truncated', $decoded)
            || !is_int($decoded['size']) || !is_int($decoded['offset'])
            || !is_int($decoded['returned_bytes'])
            || !is_bool($decoded['truncated'])
            || !in_array($decoded['encoding'], array('utf-8','base64'), true)
            || !is_string($decoded['content'])
        ) {
            throw new \ModxMCPClientException(
                'MANUAL_REVIEW_REQUIRED: secure component-file read could not be verified.'
            );
        }
        return $decoded;
    }

    public static function readFileBounded($modx, $absolutePath, array $data)
    {
        $total = (int) filesize($absolutePath);
        $maxAllowed = (int) $modx->getOption('modxmcp.max_read_bytes', null, 262144);
        if ($maxAllowed <= 0) { $maxAllowed = 262144; }

        $offset = isset($data['offset']) ? max(0, (int) $data['offset']) : 0;
        if ($offset > $total) { $offset = $total; }

        $requested = isset($data['bytes'])
            ? (int) $data['bytes']
            : (isset($data['length']) ? (int) $data['length'] : $maxAllowed);
        if ($requested <= 0 || $requested > $maxAllowed) {
            $requested = $maxAllowed;
        }

        $raw = ($total > 0 && $offset < $total)
            ? (string) file_get_contents($absolutePath, false, null, $offset, $requested)
            : '';
        $returned = strlen($raw);

        $mime = function_exists('mime_content_type')
            ? mime_content_type($absolutePath)
            : 'application/octet-stream';
        $isText = is_string($mime) && (
            strpos($mime, 'text/') === 0
            || strpos($mime, 'json') !== false
            || strpos($mime, 'xml') !== false
            || strpos($mime, 'javascript') !== false
            || strpos($mime, 'svg') !== false
            || strpos($mime, 'x-httpd-php') !== false
        );

        return array(
            'mime' => $mime,
            'size' => $total,
            'offset' => $offset,
            'returned_bytes' => $returned,
            'truncated' => ($offset + $returned) < $total,
            'encoding' => $isText ? 'utf-8' : 'base64',
            'content' => $isText ? $raw : base64_encode($raw),
        );
    }

    // RU: Автор алгоритма bounded log tail — @AgelxNash, PR #53.
    // EN: Bounded log-tail algorithm contributed by @AgelxNash, PR #53.
    public static function readTailLines($filePath, $limit = 100, $filter = null, &$metadata = null)
    {
        $limit = max(1, min((int)$limit, 1000));
        $metadata = array(
            'result_complete' => true,
            'scanned_bytes' => 0,
            'truncated_lines' => 0,
        );
        if (!file_exists($filePath)) {
            return array();
        }
        if (!is_file($filePath) || !is_readable($filePath)) {
            $metadata['result_complete'] = false;
            return array();
        }
        $fp = @fopen($filePath, 'rb');
        if (!$fp) {
            $metadata['result_complete'] = false;
            return array();
        }

        $lines = array();
        $buffer = '';
        $chunkSize = 8192;
        $maxBufferSize = 524288;
        $maxBytesToScan = 16777216;
        $totalBytesRead = 0;
        $discardingTruncatedLine = false;
        $readFailed = false;
        // RU: Дополнительный предел на весь ответ (не только на строку):
        // RU: 1000 допустимых строк по 512 КиБ иначе дали бы ~500 МиБ RAM.
        // EN: Bound the aggregate response as well as individual lines:
        // EN: 1000 valid 512 KiB lines could otherwise consume ~500 MiB.
        $maxReturnedBytes = 4194304;
        $returnedBytes = 0;
        $outputLimitReached = false;
        $push = function ($item) use (
            &$lines, &$returnedBytes, &$outputLimitReached, $maxReturnedBytes
        ) {
            // RU: JSON может увеличить размер управляющих символов в 6 раз.
            // EN: Count serialized bytes; control characters may expand 6x.
            $encoded = json_encode($item);
            $bytes = is_string($encoded) ? strlen($encoded) : $maxReturnedBytes + 1;
            if ($bytes > $maxReturnedBytes - $returnedBytes) {
                $outputLimitReached = true;
                return;
            }
            $lines[] = $item;
            $returnedBytes += $bytes;
        };
        $appendLine = function ($line) use (
            $push, $filter, &$metadata, $maxBufferSize, $chunkSize
        ) {
            $line = rtrim($line, "\r");
            if (strlen($line) > $maxBufferSize) {
                $metadata['truncated_lines']++;
                if ($filter === null) {
                    $push(self::tailFragment($line, $chunkSize));
                }
                return;
            }
            if ($filter !== null) {
                $item = call_user_func($filter, $line);
                if ($item !== null) {
                    $push($item);
                }
            } elseif (preg_match('//u', $line) !== 1) {
                // RU: Короткие бинарные строки тоже обязаны быть JSON-safe.
                // EN: Even short binary log lines must be JSON-safe.
                $push('[MODX MCP: binary line base64] ' . base64_encode($line));
            } else {
                $push($line);
            }
        };

        if (fseek($fp, 0, SEEK_END) !== 0) {
            fclose($fp);
            $metadata['result_complete'] = false;
            return array();
        }
        $pos = ftell($fp);
        if ($pos === false) {
            fclose($fp);
            $metadata['result_complete'] = false;
            return array();
        }
        $firstChunk = true;

        while ($pos > 0 && count($lines) < $limit
            && $totalBytesRead < $maxBytesToScan && !$outputLimitReached) {
            $readSize = min($chunkSize, $pos, $maxBytesToScan - $totalBytesRead);
            $pos -= $readSize;
            $totalBytesRead += $readSize;
            fseek($fp, $pos, SEEK_SET);
            $chunk = fread($fp, $readSize);
            if ($chunk === false) {
                $readFailed = true;
                break;
            }

            if ($discardingTruncatedLine) {
                $newlinePos = strrpos($chunk, "\n");
                if ($newlinePos === false) {
                    continue;
                }
                $chunk = substr($chunk, 0, $newlinePos);
                $discardingTruncatedLine = false;
            }

            $buffer = $chunk . $buffer;

            if ($firstChunk) {
                $buffer = rtrim($buffer, "\r\n");
                $firstChunk = false;
            }

            if (strlen($buffer) > $maxBufferSize && strpos($buffer, "\n") === false) {
                $metadata['truncated_lines']++;
                if ($filter === null) {
                    $push(self::tailFragment($buffer, $chunkSize));
                }
                $buffer = '';
                $discardingTruncatedLine = true;
                continue;
            }

            $parts = explode("\n", $buffer);
            if ($pos > 0) {
                $buffer = array_shift($parts);
            } else {
                $buffer = '';
            }

            for ($i = count($parts) - 1; $i >= 0; $i--) {
                $appendLine($parts[$i]);
                if (count($lines) >= $limit || $outputLimitReached) {
                    break;
                }
            }
        }
        fclose($fp);

        $metadata['result_complete'] = !$readFailed && !$outputLimitReached
            && $metadata['truncated_lines'] === 0
            && (count($lines) >= $limit || $pos === 0);
        $metadata['scanned_bytes'] = $totalBytesRead;

        return array_reverse($lines);
    }

    private static function tailFragment($line, $maxBytes)
    {
        $fragment = substr((string)$line, -(int)$maxBytes);
        if (preg_match('//u', $fragment) === 1) {
            return '[MODX MCP: line truncated; showing last 8192 bytes] ' . $fragment;
        }
        for ($offset = 1; $offset <= 3 && $offset < strlen($fragment); $offset++) {
            $candidate = substr($fragment, $offset);
            if (preg_match('//u', $candidate) === 1) {
                return '[MODX MCP: line truncated; showing UTF-8-safe suffix] ' . $candidate;
            }
        }
        return '[MODX MCP: line truncated; base64 of last 8192 bytes] '
            . base64_encode($fragment);
    }

    public static function normalizePath($path)
    {
        return rtrim(
            str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, (string) $path),
            DIRECTORY_SEPARATOR
        );
    }

    public static function isAbsolutePath($path)
    {
        return preg_match('#^(?:[A-Za-z]:[\\\\/]|/)#', (string) $path) === 1;
    }

    private static function pathStartsWith($path, $rootPath)
    {
        $path = self::normalizePath($path);
        $rootPath = self::normalizePath($rootPath);
        if ($path === $rootPath) {
            return true;
        }
        return strpos($path, $rootPath . DIRECTORY_SEPARATOR) === 0;
    }
}
