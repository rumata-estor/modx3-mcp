<?php
namespace ModxMcp\Tools;

class ProtectedSettingsGuard
{
    public static function assertAllowed($processor, array $properties, $processorsPath = '')
    {
        $processor = self::normalizePath($processor);
        $combined = $processor;
        if (trim((string)$processorsPath) !== '') {
            $combined = self::normalizePath($processorsPath . '/' . $processor);
        }

        $settingsRoute = '(?:context/setting|system/settings|security/(?:user|group)/setting)';
        $directSettingMutation = preg_match(
            '#(?:^|/)' . $settingsRoute . '/(create|update|remove|updatefromgrid)(?:\.class)?(?:\.php)?$#',
            $combined
        ) === 1;
        $contextUpdate = preg_match(
            '#(?:^|/)context/update(?:\.class)?(?:\.php)?$#',
            $combined
        ) === 1;
        if (!$directSettingMutation && !$contextUpdate) {
            return;
        }

        $payloads = array($properties);
        foreach (array('data', 'settings') as $field) {
            if (!isset($properties[$field])) {
                continue;
            }
            $nested = is_array($properties[$field])
                ? $properties[$field]
                : json_decode((string)$properties[$field], true);
            if (is_array($nested)) {
                $payloads[] = $nested;
            }
        }
        foreach ($payloads as $payload) {
            if (self::containsProtectedKey($payload)) {
                throw new \ModxMCPClientException(
                    'Managing modxmcp.* settings via run_processor is not allowed.'
                );
            }
        }
    }

    private static function containsProtectedKey(array $payload)
    {
        foreach ($payload as $field => $value) {
            if (($field === 'key' || $field === 'oldkey') && is_scalar($value)) {
                $key = trim((string)$value);
                if ($key !== '' && stripos($key, 'modxmcp.') === 0) {
                    return true;
                }
            }
            if (is_array($value) && self::containsProtectedKey($value)) {
                return true;
            }
        }
        return false;
    }

    private static function normalizePath($path)
    {
        $path = str_replace('\\', '/', strtolower(trim((string)$path)));
        $path = trim(preg_replace('#/+#', '/', $path), '/');
        $segments = array();
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($segments);
                continue;
            }
            $segments[] = $segment;
        }
        return implode('/', $segments);
    }
}
