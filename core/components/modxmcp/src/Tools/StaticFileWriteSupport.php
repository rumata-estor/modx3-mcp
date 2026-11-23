<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Переносимая атомарная замена static-файла без внешних зависимостей.
 * EN: Portable atomic replacement. Existing-file overwrites require a
 * EN: metadata inspection helper; unavailable inspection fails closed.
 */
namespace ModxMcp\Tools;

/**
 * Temp file + same-directory rename avoids truncate-first corruption and
 * following a symlink at the final path. This does NOT provide dirfd/openat
 * security against a hostile process replacing parent directories mid-call.
 */
final class StaticFileWriteSupport
{

    /**
     * RU: Единый режим для ВСЕХ static element I/O; без автоматического
     * RU: перехода из strict в portable при ошибке или недоступности helper.
     * EN: Shared static I/O policy: never downgrade strict to portable.
     */
    private static function mode($context)
    {
        $value = $context->modx()->getOption('modxmcp.static_io_mode', null, 'portable');
        if ($value === null) {
            $value = 'portable'; // Pre-upgrade sites have no setting.
        }
        if ($value !== 'portable' && $value !== 'strict') {
            throw new \ModxMCPClientException(
                'SECURE_STATIC_IO_UNAVAILABLE: invalid static I/O mode.'
            );
        }
        return $value;
    }

    public static function usesStrict($context)
    {
        return self::mode($context) === 'strict';
    }

    public static function read($context, $absolute)
    {
        ElementContentSupport::assertSafeStaticPath($context, $absolute);
        if (self::mode($context) === 'strict') {
            return self::strictIo($context, $absolute, 'read', null);
        }
        $contents = @file_get_contents($absolute);
        if ($contents === false) {
            throw new \ModxMCPClientException(
                'STATIC_READ_FAILED: static element content could not be read.'
            );
        }
        return $contents;
    }

    private static function pathArguments($context, $absolute)
    {
        $core = realpath((string)$context->modx()->getOption('core_path'));
        if ($core === false || !is_dir($core)) {
            throw new \ModxMCPClientException('SECURE_STATIC_IO_UNAVAILABLE: invalid core root.');
        }
        $core = rtrim($core, DIRECTORY_SEPARATOR);
        $absolute = (string)$absolute;
        $prefix = $core . DIRECTORY_SEPARATOR . 'elements' . DIRECTORY_SEPARATOR;
        // A path through a hosting core_path symlink is allowed only if the
        // canonical parent is inside core/elements. The helper itself does not
        // follow any symlink after opening the canonical core descriptor.
        $parent = realpath(dirname($absolute));
        if ($parent === false) {
            // Preserve new-file paths with nonexisting directories by resolving
            // the configured core alias lexically, never its untrusted children.
            $alias = rtrim((string)$context->modx()->getOption('core_path'), '/\\')
                . DIRECTORY_SEPARATOR;
            if (strpos($absolute, $alias) !== 0) {
                throw new \ModxMCPClientException('SECURE_STATIC_IO_UNAVAILABLE: invalid static path.');
            }
            $relative = substr($absolute, strlen($alias));
        } else {
            $canonical = $parent . DIRECTORY_SEPARATOR . basename($absolute);
            if (strpos($canonical, $prefix) !== 0) {
                throw new \ModxMCPClientException('SECURE_STATIC_IO_UNAVAILABLE: invalid static path.');
            }
            $relative = substr($canonical, strlen($core) + 1);
        }
        $parts = explode('/', str_replace('\\', '/', $relative));
        if (count($parts) < 3 || $parts[0] !== 'elements') {
            throw new \ModxMCPClientException('SECURE_STATIC_IO_UNAVAILABLE: invalid static path.');
        }
        foreach ($parts as $part) {
            if ($part === '' || $part === '.' || $part === '..') {
                throw new \ModxMCPClientException('SECURE_STATIC_IO_UNAVAILABLE: invalid static path.');
            }
        }
        return array($core, implode('/', $parts));
    }

    /**
     * RU: Защищённый backend PR #174: только открытые dirfd и atomic publish.
     * EN: PR #174 descriptor backend. Does not invoke shell or accept input root.
     */
    private static function strictIo($context, $absolute, $action, $payload)
    {
        list($core, $relative) = self::pathArguments($context, $absolute);
        $script = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Security'
            . DIRECTORY_SEPARATOR . 'static_io_guard.py';
        $python = '/usr/bin/python3';
        if (!is_file($script) || !is_file($python) || !function_exists('proc_open')) {
            throw new \ModxMCPClientException(
                'SECURE_STATIC_IO_UNAVAILABLE: strict filesystem helper unavailable.'
            );
        }
        $descriptors = array(
            0 => array('pipe', 'r'),
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w'),
        );
        $process = @proc_open(
            array($python, $script, $core, $relative, $action,
                ($action === 'read' || $action === 'inspect')
                    ? '-' : (string)strlen((string)$payload)),
            $descriptors, $pipes, null, null, array('bypass_shell' => true)
        );
        if (!is_resource($process)) {
            throw new \ModxMCPClientException(
                'SECURE_STATIC_IO_UNAVAILABLE: strict filesystem helper could not start.'
            );
        }
        try {
            if ($payload !== null) {
                $length = strlen($payload);
                $offset = 0;
                while ($offset < $length) {
                    $wrote = @fwrite($pipes[0], substr($payload, $offset, 65536));
                    if ($wrote === false || $wrote < 1) {
                        break;
                    }
                    $offset += $wrote;
                }
            }
            @fclose($pipes[0]);
            $stdout = @stream_get_contents($pipes[1]);
            @fclose($pipes[1]);
            @stream_get_contents($pipes[2]);
            @fclose($pipes[2]);
        } finally {
            $code = @proc_close($process);
        }
        if ($code !== 0) {
            throw new \ModxMCPClientException(
                $action === 'read'
                    ? 'STATIC_READ_FAILED: strict static file read refused.'
                    : ($action === 'inspect'
                        ? 'SECURE_STATIC_IO_UNAVAILABLE: existing static file metadata inspection refused.'
                        : 'MUTATION_OUTCOME_UNCERTAIN: strict static file write refused; inspect before retry.')
            );
        }
        if ($action === 'read' || $action === 'inspect') {
            return (string)$stdout;
        }
        if (!ctype_digit(trim((string)$stdout))
            || (int)trim((string)$stdout) !== strlen((string)$payload)) {
            throw new \ModxMCPClientException(
                'MUTATION_OUTCOME_UNCERTAIN: strict static write result mismatch.'
            );
        }
        return strlen((string)$payload);
    }

    private static function fingerprint($path)
    {
        clearstatcache(true, $path);
        $stat = @lstat($path);
        if ($stat === false) {
            return null;
        }
        return array(
            'dev' => (string)$stat['dev'],
            'ino' => (string)$stat['ino'],
            'uid' => (string)$stat['uid'],
            'gid' => (string)$stat['gid'],
            'mode' => (int)$stat['mode'],
            'nlink' => (int)$stat['nlink'],
        );
    }

    private static function sameIdentity($a, $b)
    {
        return $a === $b;
    }

    /**
     * RU: POSIX rename заменяет inode и может уничтожить ACL/xattrs.
     * RU: Portable не может предполагать отсутствие метаданных без проверки.
     * EN: An inode replacement loses ACL/xattrs. Refuse existing-file writes
     * EN: if a descriptor-anchored inspection is unavailable or finds any.
     * EN: For a hostile local writer, choose strict for the entire operation.
     */
    private static function assertPortableMetadataSafe($context, $absolute)
    {
        try {
            if (trim(self::strictIo($context, $absolute, 'inspect', null)) === 'CLEAN') {
                return;
            }
        } catch (\Throwable $e) {
            // Never silently fall back to an unchecked portable replacement.
        }
        throw new \ModxMCPClientException(
            'SECURE_STATIC_IO_UNAVAILABLE: portable replacement cannot verify'
            . ' existing file ACL/xattrs; use strict static I/O mode.'
        );
    }

    /**
     * RU: Реальный файл заменяется только после полной записи временного.
     * EN: Replace destination only after a complete same-directory write.
     */
    public static function replace($context, $absolute, $content, $exclusive = false)
    {
        $absolute = (string)$absolute;
        $content = (string)$content;
        ElementContentSupport::assertSafeStaticPath($context, $absolute);
        if (self::mode($context) === 'strict') {
            return self::strictIo($context, $absolute,
                $exclusive ? 'create' : 'write', $content);
        }

        $directory = dirname($absolute);
        if (!is_dir($directory) && !@mkdir($directory, 0755, true)) {
            throw new \ModxMCPClientException('Cannot create static file directory.');
        }
        ElementContentSupport::assertSafeStaticPath($context, $absolute);
        $parentReal = realpath($directory);
        $parent = self::fingerprint($directory);
        if ($parentReal === false || $parent === null) {
            throw new \ModxMCPClientException('Static file directory is unavailable.');
        }

        $target = self::fingerprint($absolute);
        // RU: rename не должен обходить запрет записи в существующий файл.
        // EN: Directory write permission must not bypass read-only file ACLs.
        if ($target !== null && !is_writable($absolute)) {
            throw new \ModxMCPClientException('Existing static file is not writable.');
        }
        // RU: Внутренние симлинки до 1.2.8 были допустимы; не ломаем их.
        // EN: Preserve previously permitted in-root symlink behavior.
        if (!$exclusive && is_link($absolute)) {
            throw new \ModxMCPClientException('SECURE_STATIC_IO_UNAVAILABLE: unsafe compatibility write refused.');
        }
        if ($target !== null && ($target['mode'] & 0170000) !== 0100000) {
            throw new \ModxMCPClientException('Static target must be a regular file.');
        }
        if ($exclusive && ($target !== null || is_link($absolute))) {
            throw new \ModxMCPClientException(
                'make_static: TV exclusive creation failed; target already exists.'
            );
        }
        // RU: Windows rename поверх существующего файла отличается от Unix.
        // RU: Там оставляем прежний способ, не вводя новые требования.
        // EN: Windows rename-replace is not equivalent to POSIX rename;
        // EN: keep legacy behavior rather than break existing installations.
        if (DIRECTORY_SEPARATOR === '\\' && !$exclusive) {
            throw new \ModxMCPClientException('SECURE_STATIC_IO_UNAVAILABLE: unsafe compatibility write refused.');
        }
        // RU: Некоторые хостинги разрешают запись в файл, но запрещают
        // RU: создание соседних файлов. До 1.2.8 этот случай работал.
        // EN: Preserve pre-1.2.8 behavior when directories deny creation.
        // EN: Never enable this compatibility path for new files.
        if (!$exclusive && $target !== null && !is_writable($directory)
            && is_writable($absolute)) {
            throw new \ModxMCPClientException('SECURE_STATIC_IO_UNAVAILABLE: unsafe compatibility write refused.');
        }
        if ($target !== null) {
            self::assertPortableMetadataSafe($context, $absolute);
        }
        $temp = $directory . DIRECTORY_SEPARATOR . '.modxmcp-' . bin2hex(random_bytes(12)) . '.tmp';
        $handle = @fopen($temp, 'xb');
        if ($handle === false) {
            throw new \ModxMCPClientException('Could not create static temporary file.');
        }
        $tempCreated = true;
        try {
            $length = strlen($content);
            $offset = 0;
            while ($offset < $length) {
                $wrote = @fwrite($handle, substr($content, $offset, 65536));
                if ($wrote === false || $wrote < 1) {
                    throw new \ModxMCPClientException(
                        'MUTATION_OUTCOME_UNCERTAIN: static temporary write failed; inspect before retry.'
                    );
                }
                $offset += $wrote;
            }
            $flushed = @fflush($handle);
            $closed = @fclose($handle);
            $handle = null;
            if (!$flushed || !$closed) {
                throw new \ModxMCPClientException(
                    'MUTATION_OUTCOME_UNCERTAIN: static temporary flush failed; inspect before retry.'
                );
            }
            // Preserve normal MODX file readability. Existing files retain
            // their mode; new files obey the host umask like ordinary fopen.
            $permissions = $target === null
                ? (0666 & ~umask())
                : ($target['mode'] & 0777);
            if (!@chmod($temp, $permissions)) {
                throw new \ModxMCPClientException('Static temporary permissions could not be set.');
            }
            $tmpInfo = self::fingerprint($temp);
            if ($tmpInfo === null || ($tmpInfo['mode'] & 0170000) !== 0100000
                || $tmpInfo['nlink'] !== 1) {
                throw new \ModxMCPClientException('Static temporary file was replaced.');
            }
            // RU: Не менять владельца чужого файла на хостинге при rename.
            // EN: Shared-hosting files may be owned by a different account;
            // EN: preserve ownership and legacy behavior instead of replacing inode.
            if ($target !== null && (
                $target['uid'] !== $tmpInfo['uid']
                || $target['gid'] !== $tmpInfo['gid']
            ) && is_writable($absolute)) {
                ElementContentSupport::assertSafeStaticPath($context, $absolute);
                throw new \ModxMCPClientException('SECURE_STATIC_IO_UNAVAILABLE: unsafe compatibility write refused.');
            }

            // Detect parent/target substitution between our initial checks and
            // rename. PHP cannot eliminate the final tiny check/use interval.
            ElementContentSupport::assertSafeStaticPath($context, $absolute);
            if (realpath($directory) !== $parentReal
                || !self::sameIdentity(self::fingerprint($directory), $parent)
                || !self::sameIdentity(self::fingerprint($absolute), $target)
                || ($exclusive && is_link($absolute))) {
                throw new \ModxMCPClientException(
                    'MUTATION_OUTCOME_UNCERTAIN: static file changed concurrently; inspect before retry.'
                );
            }
            // Recheck immediately before publication: metadata may change
            // after the first inspection. Portable retains its documented
            // final TOCTOU interval; strict is required for hostile writers.
            if ($target !== null) {
                self::assertPortableMetadataSafe($context, $absolute);
            }
            if ($exclusive) {
                if (!@link($temp, $absolute)) {
                    throw new \ModxMCPClientException(
                        'make_static: TV exclusive creation failed; target already exists or link unavailable.'
                    );
                }
            } elseif (!@rename($temp, $absolute)) {
                throw new \ModxMCPClientException(
                    'MUTATION_OUTCOME_UNCERTAIN: static file replacement failed; inspect before retry.'
                );
            }
            if (!$exclusive) { $tempCreated = false; }
            return $length;
        } finally {
            if (is_resource($handle)) {
                @fclose($handle);
            }
            if ($tempCreated) {
                @unlink($temp);
            }
        }
    }
}
