<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Общее маскирование значений секретных системных и контекстных настроек.
 * EN: Shared secret-value redaction for system and context settings.
 */
namespace ModxMcp\Tools;

final class SettingSecretSupport
{
    public static function isSensitiveKey($key)
    {
        if (!is_string($key)) return false;
        $key = strtolower(trim($key));
        // RU: Публичный ключ проверки подписи не является закрытым секретом.
        // EN: The public signature verification key is intentionally readable.
        if ($key === 'modxmcp.permit_public_key') return false;
        $canonical = preg_replace('/[^a-z0-9]/', '', $key);
        // RU: Учитываем и MODX-style snake_case, и camelCase имена.
        // EN: Recognize both snake_case and camelCase credential key names.
        return (bool)preg_match(
            '/(?:password|passwd|(?:api|access|refresh|auth|session)?token|secret|'
            . 'privatekey|apikey|credentials|credential|authorization|'
            . 'connectionstring|clientkey|accesskey|cachepwd|dbpwd)$/D',
            $canonical
        );
    }

    public static function settingRow(array $row)
    {
        $key = isset($row['key']) && is_string($row['key'])
            ? $row['key'] : (isset($row['name']) && is_string($row['name'])
                ? $row['name'] : '');
        if (!self::isSensitiveKey($key)) return $row;
        foreach (array('value', 'default', 'default_value', 'context_values') as $field) {
            if (array_key_exists($field, $row)) {
                if ($field === 'context_values' && is_array($row[$field])) {
                    foreach ($row[$field] as $context => $value) {
                        $row[$field][$context] = '[REDACTED]';
                    }
                } else {
                    $row[$field] = '[REDACTED]';
                }
            }
        }
        return $row;
    }

    /**
     * RU: Процессоры MODX могут возвращать single row, results или object.
     * EN: MODX processors may return one row, results, or nested object.
     */
    public static function contextResponse($value, $depth = 0)
    {
        if (!is_array($value)) return $value;
        if ($depth > 12) return array('redacted' => 'Maximum settings nesting exceeded.');
        $value = self::settingRow($value);
        // RU: Скрываем только распознанные значения, без изменения обычных полей.
        // EN: Redact recognized secrets without changing normal setting fields.
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = self::contextResponse($item, $depth + 1);
            }
        }
        return $value;
    }
}
