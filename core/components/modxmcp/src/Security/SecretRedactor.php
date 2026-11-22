<?php
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
        $text = preg_replace('/\b(Bearer\s+)[A-Za-z0-9._~+\/-]+/i', '$1[REDACTED]', $text);
        $text = preg_replace('/\b(Basic\s+)[A-Za-z0-9+\/=]+/i', '$1[REDACTED]', $text);
        $text = preg_replace('/\b(password|passwd|pwd|secret|token|api[_-]?key|access[_-]?key|authorization|cookie|client[_-]?secret|private[_-]?key)\b(\s*[:=]\s*["\x27]?)[^\s,;&"\x27}]+/i', '$1$2[REDACTED]', $text);
        $text = preg_replace('~(https?://)[^\s/@:]+:[^\s/@]+@~i', '$1[REDACTED]@', $text);
        return $text;
    }
}
