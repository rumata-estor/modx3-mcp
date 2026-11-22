<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Инструмент MCP «Client Config Setting Update»: выполняется через защищённый диспетчер.
 * EN: MCP tool «Client Config Setting Update»: invoked through the guarded dispatcher.
 */
namespace ModxMcp\Tools;

class ClientConfigSettingUpdateTool implements ToolInterface
{
    public function name() { return 'clientconfig_update_setting'; }
    public function group() { return 'clientconfig'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx(); }

    public function execute($context, array $data)
    {
        $setting = ClientConfigSupport::setting($context, $data);
        if (!$setting) {
            throw new \ModxMCPClientException(
                'ClientConfig setting not found.'
            );
        }
        ClientConfigSupport::validateContextValues($data);
        ClientConfigSupport::applyFields($setting, $data);
        if (!$setting->save()) {
            throw new \ModxMCPClientException(
                'Failed to update ClientConfig setting.'
            );
        }
        ClientConfigSupport::applyContextValuesAfterSave($context, $setting, $data);
        ClientConfigSupport::refresh($context);
        AuditSupport::log(
            $context,
            $this->name(),
            'clientconfig',
            array(
                'id' => (int)$setting->get('id'),
                'key' => $setting->get('key'),
            )
        );
        return ClientConfigSupport::normalize($context, $setting);
    }
}
