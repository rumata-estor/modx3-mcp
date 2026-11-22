<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Защита секретов в диагностических данных и её автоматическая проверка.
 * EN: Diagnostic secret redaction and its automated verification.
 */
namespace ModxMcp\Security;

/** Sanitizes diagnostic data; never call on working values before persistence. */
class SecretRedactor
{
    public static function isSecretKey($key)
    {
        return (bool) preg_match('/(?:password|passwd|pwd|secret|token|api[_-]?key|access[_-]?key|authorization|cookie|session[_-]?id|private[_-]?key|credential|db[_-]?pass)/i', (string)$key);
    }

    public static function value($value, $key = '')
    {
        if (self::isSecretKey($key)) { return '[REDACTED]'; }
        if (is_array($value)) {
            $result = array();
            foreach ($value as $childKey => $childValue) {
                $result[$childKey] = self::value($childValue, $childKey);
            }
            return $result;
        }
        return is_string($value) ? self::text($value) : $value;
    }

    public static function text($text)
    {
        $text = (string)$text;
        // Authentication schemes and credentials embedded in URLs, including DB DSNs.
        $text = preg_replace('/\b(Bearer\s+)[A-Za-z0-9._~+\/-]+/i', '$1[REDACTED]', $text);
        $text = preg_replace('/\b(Basic\s+)[A-Za-z0-9+\/=]+/i', '$1[REDACTED]', $text);
        $text = preg_replace('~\b([a-z][a-z0-9+.-]*://)[^\s/@:]+:[^\s/@]+@~i', '$1[REDACTED]@', $text);
        // JSON string fields, HTTP headers and shell-style KEY=value pairs.
        $text = preg_replace('/(["\x27]?)(password|passwd|pwd|secret(?:[_-]?key)?|token|api[_-]?key|access[_-]?key|authorization|(?:set[_-]?)?cookie|client[_-]?secret|private[_-]?key|credentials?|db[_-]?pass)\1(\s*[:=]\s*["\x27]?)[^\s,;&"\x27}]+/i', '$1$2$1$3[REDACTED]', $text);
        return $text;
    }
}
