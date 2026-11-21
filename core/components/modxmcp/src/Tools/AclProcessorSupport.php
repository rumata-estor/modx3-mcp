<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Вспомогательная логика «Acl Processor» для инструментов MODX MCP.
 * EN: Shared «Acl Processor» implementation used by MODX MCP tools.
 */
namespace ModxMcp\Tools;

class AclProcessorSupport
{
    public static function run($context, $processor, array $data, $isList)
    {
        $props = $data;
        unset($props['action'], $props['elementType'], $props['type']);
        $result = ProcessorSupport::run(
            $context,
            $processor,
            $props,
            $isList,
            array('core:default', 'core:user', 'core:access', 'core:policy', 'core:role')
        );
        // RU: Получение одного пользователя должно скрывать секреты так же,
        // RU: как list_users. Не раскрываем пароль, соль и сессионные ключи.
        // EN: A single user read must redact credentials just like list_users.
        // EN: Never expose password hashes, salts or session secrets.
        if (!$isList && $processor === 'security/user/get'
            && is_array($result)) {
            $safe = ProcessorSupport::stripNoiseFields(array($result));
            return $safe[0];
        }
        return $result;
    }
}
