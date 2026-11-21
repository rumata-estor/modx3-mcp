<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Инструмент MCP «System Setting Delete»: выполняется через защищённый диспетчер.
 * EN: MCP tool «System Setting Delete»: invoked through the guarded dispatcher.
 */
namespace ModxMcp\Tools;

class SystemSettingDeleteTool implements ToolInterface
{
    public function name() { return 'delete_system_setting'; }
    public function group() { return 'system'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        $class = $context->platform()->className('system_setting');
        $setting = null;
        if (!empty($data['key'])) {
            $setting = $context->modx()->getObject(
                $class,
                array('key' => $data['key'])
            );
        } elseif (!empty($data['id'])) {
            $setting = $context->modx()->getObject(
                $class,
                array('id' => (int)$data['id'])
            );
        }
        if (!$setting) {
            throw new \ModxMCPClientException('System setting not found.');
        }

        $key = $setting->get('key');
        // RU: Удаление по ID также должно проверять нормализованный ключ.
        // EN: ID-based deletion must validate the normalized stored key.
        \ModxMcp\Security\ProtectedSettingsGuard::assertMutableKey(
            (string)$key
        );
        if (!$setting->remove()) {
            throw new \ModxMCPClientException(
                'Failed to delete system setting: ' . $key . '.'
            );
        }
        ElementMutationSupport::refreshCache($context);
        AuditSupport::log(
            $context,
            $this->name(),
            'system_setting',
            array('key' => $key)
        );
        return 'Successfully deleted system setting (' . $key . ').';
    }
}
