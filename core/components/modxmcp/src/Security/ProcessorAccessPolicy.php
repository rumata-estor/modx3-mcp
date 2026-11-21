<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Проверяет точный путь процессора и режим доступа, не выполняет процессор.
 * EN: Validates exact processor paths and access modes; does not execute processors.
 */
namespace ModxMcp\Security;

/** Exact processor policy: no wildcard or user-controlled processor loaders. */
final class ProcessorAccessPolicy
{
    private static function parseList($csv)
    {
        if (!is_string($csv) || strlen($csv) > 8192) {
            throw new \RuntimeException('AUTH_DENIED: invalid processor policy list.');
        }
        if ($csv === '') { return array(); }
        $paths = array();
        foreach (explode(',', $csv) as $item) {
            if ($item !== trim($item)
                || !preg_match('#\A[a-z][a-z0-9_-]*(?:/[a-z][a-z0-9_-]*){1,7}\z#D', $item)
                || isset($paths[$item])) {
                throw new \RuntimeException('AUTH_DENIED: malformed processor allowlist.');
            }
            $paths[$item] = true;
        }
        return $paths;
    }

    public static function authorize(array $data, $mode, $allowedCsv, $privilegedCsv)
    {
        if (!is_string($mode)
            || !in_array($mode, array('disabled', 'allowlist', 'privileged'), true)) {
            throw new \RuntimeException('AUTH_DENIED: invalid processor access mode.');
        }
        if ($mode === 'disabled') {
            throw new \RuntimeException('AUTH_DENIED: processor execution disabled.');
        }
        if (isset($data['processors_path'])) {
            throw new \RuntimeException('AUTH_DENIED: custom processor paths forbidden.');
        }
        $processor = isset($data['processor']) ? $data['processor'] : null;
        if (!is_string($processor)
            || !preg_match('#\A[a-z][a-z0-9_-]*(?:/[a-z][a-z0-9_-]*){1,7}\z#D', $processor)) {
            throw new \RuntimeException('AUTH_DENIED: invalid processor name.');
        }
        // RU: Системные, контекстные и пользовательские настройки способны
        // RU: изменить доверенные modxmcp.* ключи, включая вложенные settings
        // RU: у context/update. Запрещаем их даже в privileged режиме.
        // EN: System/context/user/group settings can modify modxmcp.* trust
        // EN: anchors, including nested settings in context/update.
        // EN: Deny these generic routes even under privileged authorization.
        if (strpos($processor, 'system/settings/') === 0
            || strpos($processor, 'system/setting/') === 0
            || strpos($processor, 'context/setting/') === 0
            || $processor === 'context/update'
            || strpos($processor, 'security/user/setting/') === 0
            || strpos($processor, 'security/group/setting/') === 0) {
            throw new \RuntimeException('AUTH_DENIED: settings processors are admin-only.');
        }
        $allowed = self::parseList($allowedCsv);
        $privileged = self::parseList($privilegedCsv);
        // RU: Управление пользователями, пакетами и namespace не разрешается
        // RU: обычным белым списком — только privileged + внешняя подпись.
        // EN: User/security, package and namespace processors cannot be
        // EN: delegated by the ordinary allowlist; require privileged + Ed25519.
        $sensitive = strpos($processor, 'security/') === 0
            || strpos($processor, 'workspace/package/') === 0
            || strpos($processor, 'workspace/namespace/') === 0;
        if ($sensitive) {
            if ($mode === 'privileged' && isset($privileged[$processor])) {
                return true;
            }
            throw new \RuntimeException(
                'AUTH_DENIED: sensitive processor requires privileged authorization.'
            );
        }
        if (isset($allowed[$processor])) { return true; }
        if ($mode === 'privileged' && isset($privileged[$processor])) { return true; }
        throw new \RuntimeException('AUTH_DENIED: processor not explicitly approved.');
    }
}
