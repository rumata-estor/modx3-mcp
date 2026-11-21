<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Инструмент MCP «System Setting Create»: выполняется через защищённый диспетчер.
 * EN: MCP tool «System Setting Create»: invoked through the guarded dispatcher.
 */
namespace ModxMcp\Tools;

class SystemSettingCreateTool implements ToolInterface
{
    public function name() { return 'create_system_setting'; }
    public function group() { return 'system'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        if (empty($data['key'])) {
            throw new \ModxMCPClientException('System setting key is required.');
        }
        // RU: Нормализуем регистр и пробелы до выполнения записи.
        // EN: Normalize case and whitespace before persisting the setting.
        \ModxMcp\Security\ProtectedSettingsGuard::assertMutableKey(
            (string)$data['key']
        );
        $modx = $context->modx();
        $class = $context->platform()->className('system_setting');
        if ($modx->getObject($class, array('key' => $data['key']))) {
            throw new \ModxMCPClientException(
                'System setting already exists: ' . $data['key'] . '.'
            );
        }

        $setting = $modx->newObject($class);
        $setting->fromArray(
            array(
                'key' => $data['key'],
                'value' => array_key_exists('value', $data)
                    ? (string)$data['value']
                    : '',
                'xtype' => !empty($data['xtype'])
                    ? $data['xtype']
                    : 'textfield',
                'namespace' => !empty($data['namespace'])
                    ? $data['namespace']
                    : 'core',
                'area' => !empty($data['area'])
                    ? $data['area']
                    : 'default',
            ),
            '',
            true,
            true
        );
        if (!$setting->save()) {
            throw new \ModxMCPClientException(
                'Failed to create system setting: ' . $data['key'] . '.'
            );
        }

        ElementMutationSupport::refreshCache($context);
        AuditSupport::log(
            $context,
            $this->name(),
            'system_setting',
            array('key' => $data['key'])
        );
        return SystemSettingListTool::normalize($setting);
    }
}
