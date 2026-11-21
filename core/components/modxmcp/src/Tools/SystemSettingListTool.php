<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Инструмент MCP «System Setting List»: выполняется через защищённый диспетчер.
 * EN: MCP tool «System Setting List»: invoked through the guarded dispatcher.
 */
namespace ModxMcp\Tools;

class SystemSettingListTool implements ToolInterface
{
    public function name() { return 'list_system_settings'; }
    public function group() { return 'system'; }
    public function isMutation() { return false; }

    public function supports($context)
    {
        return $context && $context->modx() && $context->platform();
    }

    public function execute($context, array $data)
    {
        $modx = $context->modx();
        $class = $context->platform()->className('system_setting');
        $criteria = array();
        if (!empty($data['namespace'])) { $criteria['namespace'] = $data['namespace']; }
        if (!empty($data['area'])) { $criteria['area'] = $data['area']; }

        $result = array();
        foreach ($modx->getCollection($class, $criteria) as $setting) {
            $result[] = self::normalize($setting);
        }
        return $result;
    }

    /**
     * RU: При чтении через MCP скрываем токены, пароли и другие секреты.
     * RU: Публичные ключи и обычные настройки оставляем доступными.
     * EN: Redact tokens, passwords and credentials in MCP read responses.
     * EN: Public keys and ordinary setting values remain readable.
     */
    public static function normalize($setting)
    {
        $key = (string)$setting->get('key');
        $value = $setting->get('value');
        // RU: Проверяем отдельные сегменты имени, а не произвольные слова.
        // EN: Match sensitive key segments, not arbitrary substrings.
        $sensitive = preg_match(
            '/(?:\\A|[._-])(?:api_token|access_token|refresh_token|'
            . 'secret|password|passwd|client_secret|private_key|api_key|'
            . 'credentials)(?:\\z|[._-])/iD',
            $key
        ) === 1;
        return array(
            'id' => $setting->get('id'),
            'key' => $key,
            'value' => $sensitive ? '[REDACTED]' : $value,
            'xtype' => $setting->get('xtype'),
            'namespace' => $setting->get('namespace'),
            'area' => $setting->get('area'),
        );
    }
}
