<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Дескрипторный ввод-вывод для статических элементов, без TOCTOU fallback.
 * EN: Descriptor-anchored static element I/O without a TOCTOU fallback.
 */
namespace ModxMcp\Tools;

final class StaticFileIoSupport
{
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

    public static function read($context, $absolute)
    {
        return self::invoke($context, $absolute, 'read', null);
    }

    public static function write($context, $absolute, $content, $createOnly = false)
    {
        return self::invoke($context, $absolute, $createOnly ? 'create' : 'write', (string)$content);
    }

    private static function invoke($context, $absolute, $action, $payload)
    {
        list($core, $relative) = self::pathArguments($context, $absolute);
        $script = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Security'
            . DIRECTORY_SEPARATOR . 'static_io_guard.py';
        // Restrict the executable: no PATH lookup, shell interpolation,
        // user-selected python, or legacy unsafe write fallback.
        $python = '/usr/bin/python3';
        if (!is_file($script) || !is_file($python) || !function_exists('proc_open')) {
            throw new \ModxMCPClientException(
                'SECURE_STATIC_IO_UNAVAILABLE: guarded filesystem helper is unavailable.'
            );
        }
        $descriptors = array(
            0 => array('pipe', 'r'),
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w'),
        );
        // PHP 7.4 supports the argv array form, avoiding any shell.
        $process = @proc_open(
            array($python, $script, $core, $relative, $action,
                $action === 'read' ? '-' : (string)strlen((string)$payload)),
            $descriptors, $pipes, null, null,
            array('bypass_shell' => true)
        );
        if (!is_resource($process)) {
            throw new \ModxMCPClientException('SECURE_STATIC_IO_UNAVAILABLE: helper could not start.');
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
            // The child never outputs user-supplied diagnostics.
            @stream_get_contents($pipes[2]);
            @fclose($pipes[2]);
        } finally {
            $code = @proc_close($process);
        }
        if ($code !== 0) {
            throw new \ModxMCPClientException(
                'MUTATION_OUTCOME_UNCERTAIN: guarded static file I/O failed; '
                . 'inspect file state before retry.'
            );
        }
        if ($action === 'read') {
            return (string)$stdout;
        }
        if (!ctype_digit(trim((string)$stdout)) || (int)trim((string)$stdout) !== strlen((string)$payload)) {
            throw new \ModxMCPClientException(
                'MUTATION_OUTCOME_UNCERTAIN: guarded static write result mismatch.'
            );
        }
        return strlen((string)$payload);
    }
}
