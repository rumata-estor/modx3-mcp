<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Переносимая атомарная замена static-файла без внешних зависимостей.
 * EN: Portable atomic replacement for static files without external dependencies.
 */
namespace ModxMcp\Tools;

/**
 * Temp file + same-directory rename avoids truncate-first corruption and
 * following a symlink at the final path. This does NOT provide dirfd/openat
 * security against a hostile process replacing parent directories mid-call.
 */
final class StaticFileWriteSupport
{
    private static $compatibilityWarningLogged = false;

    /**
     * RU: Не менять прежнее поведение, если можно изменить только файл,
     * RU: но нельзя создать временный файл рядом с ним.
     * EN: Preserve legacy semantics when directories deny file creation.
     * EN: This preserves the preexisting TOCTOU risk and logs a warning.
     */
    private static function legacyCompatibleWrite($absolute, $content)
    {
        if (!self::$compatibilityWarningLogged) {
            @error_log('MODX MCP: static atomic replacement unavailable; '
                . 'using legacy in-place write; TOCTOU parent race remains.');
            self::$compatibilityWarningLogged = true;
        }
        $written = @file_put_contents($absolute, $content);
        if ($written === false || $written !== strlen($content)) {
            throw new \ModxMCPClientException(
                'MUTATION_OUTCOME_UNCERTAIN: compatibility static write incomplete; '
                . 'inspect file before retry.'
            );
        }
        return $written;
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
     * RU: Реальный файл заменяется только после полной записи временного.
     * EN: Replace destination only after a complete same-directory write.
     */
    public static function replace($context, $absolute, $content, $exclusive = false)
    {
        $absolute = (string)$absolute;
        $content = (string)$content;
        ElementContentSupport::assertSafeStaticPath($context, $absolute);

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
            return self::legacyCompatibleWrite($absolute, $content);
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
            return self::legacyCompatibleWrite($absolute, $content);
        }
        // RU: Некоторые хостинги разрешают запись в файл, но запрещают
        // RU: создание соседних файлов. До 1.2.8 этот случай работал.
        // EN: Preserve pre-1.2.8 behavior when directories deny creation.
        // EN: Never enable this compatibility path for new files.
        if (!$exclusive && $target !== null && !is_writable($directory)
            && is_writable($absolute)) {
            return self::legacyCompatibleWrite($absolute, $content);
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
                return self::legacyCompatibleWrite($absolute, $content);
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
            if (!@rename($temp, $absolute)) {
                throw new \ModxMCPClientException(
                    'MUTATION_OUTCOME_UNCERTAIN: static file replacement failed; inspect before retry.'
                );
            }
            $tempCreated = false;
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
