<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Единая проверка доверенных ключей настроек MODX MCP.
 * EN: Centralized validation of protected MODX MCP setting keys.
 */
namespace ModxMcp\Security;

final class ProtectedSettingsGuard
{
    /**
     * RU: MODX нормализует ключи при сохранении; проверяем такой же
     * RU: ключ ДО запуска процессора, без учёта регистра и пробелов.
     * EN: MODX may normalize keys before saving. Validate the normalized
     * EN: key BEFORE processor dispatch, ignoring whitespace and case.
     */
    public static function isProtectedKey($key)
    {
        return is_string($key)
            && stripos(trim($key), 'modxmcp.') === 0;
    }

    /**
     * RU: Запрещает изменение защищённых настроек с любого MCP-маршрута.
     * EN: Rejects attempts to mutate protected settings via any MCP route.
     */
    public static function assertMutableKey($key)
    {
        if (self::isProtectedKey($key)) {
            throw new \ModxMCPClientException(
                'Managing modxmcp.* settings via MCP API is not allowed.'
            );
        }
    }

    /**
     * RU: context/update умеет принимать вложенные настройки. Не передаём
     * RU: этот непрозрачный payload универсальному процессору: отдельные
     * RU: инструменты context_setting обеспечивают проверку каждого ключа.
     * EN: context/update accepts nested settings. Deny opaque settings
     * EN: payloads: dedicated context_setting actions validate each key.
     */
    public static function assertNoOpaqueContextSettings(array $data)
    {
        foreach (array('settings', 'context_settings') as $name) {
            if (array_key_exists($name, $data)) {
                throw new \ModxMCPClientException(
                    'Context settings must use dedicated validated MCP actions.'
                );
            }
        }
    }
}
