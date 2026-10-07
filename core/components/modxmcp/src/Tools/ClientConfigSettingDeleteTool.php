<?php
namespace ModxMcp\Tools;

class ClientConfigSettingDeleteTool implements ToolInterface
{
    public function name() { return 'clientconfig_delete_setting'; }
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
        $id = (int)$setting->get('id');
        $key = $setting->get('key');
        if (!$setting->remove()) {
            throw new \ModxMCPClientException(
                'Failed to delete ClientConfig setting.'
            );
        }
        ClientConfigSupport::refresh($context);
        AuditSupport::log(
            $context,
            $this->name(),
            'clientconfig',
            array('id' => $id, 'key' => $key)
        );
        return array('deleted' => true, 'id' => $id, 'key' => $key);
    }
}
