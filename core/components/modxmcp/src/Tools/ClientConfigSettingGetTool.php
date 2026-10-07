<?php
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
