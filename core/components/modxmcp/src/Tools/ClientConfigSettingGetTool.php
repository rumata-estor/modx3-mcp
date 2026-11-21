<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Инструмент MCP «Client Config Setting Get»: выполняется через защищённый диспетчер.
 * EN: MCP tool «Client Config Setting Get»: invoked through the guarded dispatcher.
 */
namespace ModxMcp\Tools;

class ClientConfigSettingGetTool implements ToolInterface
{
    public function name() { return 'clientconfig_get_setting'; }
    public function group() { return 'clientconfig'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx(); }

    public function execute($context, array $data)
    {
        $setting = ClientConfigSupport::setting($context, $data);
        if (!$setting) {
            throw new \ModxMCPClientException(
                'ClientConfig setting not found.'
            );
        }
        return ClientConfigSupport::normalize($context, $setting);
    }
}
