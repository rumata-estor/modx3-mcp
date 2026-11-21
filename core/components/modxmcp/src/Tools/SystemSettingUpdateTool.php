<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Инструмент MCP «System Setting Update»: выполняется через защищённый диспетчер.
 * EN: MCP tool «System Setting Update»: invoked through the guarded dispatcher.
 */
namespace ModxMcp\Tools;

class SystemSettingUpdateTool implements ToolInterface
{
    public function name() { return 'update_system_setting'; }
    public function group() { return 'system'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        $setting = $this->resolve($context, $data);
        if (!$setting) {
            throw new \ModxMCPClientException('System setting not found.');
        }

        $key = (string) $setting->get('key');
        // RU: Проверяем исходный и новый ключ, в том числе при обновлении по ID.
        // EN: Validate existing and replacement keys, including ID-based updates.
        \ModxMcp\Security\ProtectedSettingsGuard::assertMutableKey($key);
        if (isset($data['key'])) {
            \ModxMcp\Security\ProtectedSettingsGuard::assertMutableKey(
                (string)$data['key']
            );
        }

        foreach (array('key', 'value', 'xtype', 'namespace', 'area') as $field) {
            if (array_key_exists($field, $data)) {
                $setting->set($field, $data[$field]);
            }
        }
        if (!$setting->save()) {
            throw new \ModxMCPClientException(
                'Failed to update system setting.'
            );
        }

        ElementMutationSupport::refreshCache($context);
        AuditSupport::log(
            $context,
            $this->name(),
            'system_setting',
            array('key' => $setting->get('key'))
        );
        return SystemSettingListTool::normalize($setting);
    }

    private function resolve($context, array $data)
    {
        $class = $context->platform()->className('system_setting');
        if (!empty($data['key'])) {
            return $context->modx()->getObject(
                $class,
                array('key' => $data['key'])
            );
        }
        if (!empty($data['id'])) {
            return $context->modx()->getObject(
                $class,
                array('id' => (int)$data['id'])
            );
        }
        return null;
    }
}
