<?php
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

    public static function normalize($setting)
    {
        $key = (string)$setting->get('key');
        $value = $setting->get('value');
        if ($key === 'modxmcp.api_token' && is_string($value) && $value !== '') {
            $value = substr($value, 0, 6) . '…' . substr($value, -4);
        }
        return array(
            'id' => $setting->get('id'),
            'key' => $key,
            'value' => $value,
            'xtype' => $setting->get('xtype'),
            'namespace' => $setting->get('namespace'),
            'area' => $setting->get('area'),
        );
    }
}
