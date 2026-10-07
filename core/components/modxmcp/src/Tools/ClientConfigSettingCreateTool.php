<?php
namespace ModxMcp\Tools;

class ClientConfigSettingCreateTool implements ToolInterface
{
    public function name() { return 'clientconfig_create_setting'; }
    public function group() { return 'clientconfig'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx(); }

    public function execute($context, array $data)
    {
        ClientConfigSupport::modelPath($context);
        if (empty($data['key'])) {
            throw new \ModxMCPClientException(
                'clientconfig_create_setting: key is required.'
            );
        }
        if ($context->modx()->getObject(
            'cgSetting',
            array('key' => (string)$data['key'])
        )) {
            throw new \ModxMCPClientException(
                'ClientConfig setting already exists: ' . $data['key']
            );
        }
        $setting = $context->modx()->newObject('cgSetting');
        ClientConfigSupport::applyFields($setting, $data);
        if (!$setting->save()) {
            throw new \ModxMCPClientException(
                'Failed to save ClientConfig setting.'
            );
        }
        ClientConfigSupport::applyContextValues($context, $setting, $data);
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
