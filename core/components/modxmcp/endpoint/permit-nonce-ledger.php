<?php
/**
 * One-use permit nonce ledger for the future external authorization gate.
 * Offline primitive only. The caller MUST check a valid MAC, exact scope,
 * configured audience, request digest and expiry BEFORE reserve().
 * A nonce is consumed before attempting the write; failed/unknown writes
 * must NOT release it. This class must not be enabled without an approved
 * outside-webroot private directory and integration tests.
 */
class ModxMcpPermitNonceLedger
{
    private $root;

    public function __construct($directory)
    {
        if (!is_string($directory) || $directory === '' || $directory[0] !== '/') {
            throw new InvalidArgumentException('absolute private nonce directory required');
        }
        $real = realpath($directory);
        if ($real === false || $real !== rtrim($directory, '/')
            || !is_dir($real) || is_link($directory)) {
            throw new RuntimeException('untrusted nonce directory');
        }
        $current = $real;
        do {
            $mode = fileperms($current);
            if ($mode === false || ($mode & 0022) !== 0 || is_link($current)) {
                throw new RuntimeException('nonce directory ancestor is writable');
            }
            $next = dirname($current);
            if ($next === $current) {
                break;
            }
            $current = $next;
        } while (true);
        if ((fileperms($real) & 0077) !== 0) {
            throw new RuntimeException('nonce directory must be private (0700)');
        }
        $this->root = $real;
    }

    public function reserve($nonce)
    {
        if (!is_string($nonce) || !preg_match('/\\A[0-9a-f]{32}\\z/D', $nonce)) {
            throw new InvalidArgumentException('invalid nonce');
        }
        $name = $this->root . '/' . hash('sha256', $nonce) . '.used';
        // Atomic O_EXCL creation: only a single concurrent caller may succeed.
        $fp = @fopen($name, 'x');
        if ($fp === false) {
            throw new RuntimeException('nonce already used or ledger unavailable');
        }
        try {
            @chmod($name, 0600);
            if (fwrite($fp, $nonce . "\n") !== strlen($nonce) + 1 ||
                !fflush($fp)) {
                throw new RuntimeException('nonce ledger write not durable');
            }
            // Fail closed: do not delete this reservation on any later error.
            if (!function_exists('fsync') || !fsync($fp)) {
                // PHP runtimes without fsync may still serve read-only MCP,
                // but cannot safely authorize this protected write path.
                throw new RuntimeException('nonce ledger durability unavailable');
            }
        } finally {
            fclose($fp);
        }
    }
}
