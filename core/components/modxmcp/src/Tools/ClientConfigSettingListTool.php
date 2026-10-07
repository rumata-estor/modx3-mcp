<?php
namespace ModxMcp\Tools;

class ClientConfigSettingListTool implements ToolInterface
{
    public function name() { return 'clientconfig_list_settings'; }
    public function group() { return 'clientconfig'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx(); }

    public function execute($context, array $data)
    {
        ClientConfigSupport::modelPath($context);
        $query = $context->modx()->newQuery('cgSetting');
        if (!empty($data['query'])) {
            $q = '%' . $data['query'] . '%';
            $query->where(array(
                'key:LIKE' => $q,
                'OR:label:LIKE' => $q,
            ));
        }
        $query->sortby('sortorder', 'ASC');
        $query->sortby('cgSetting.key', 'ASC');
        $rows = array();
        foreach ($context->modx()->getCollection('cgSetting', $query) as $setting) {
            $rows[] = ClientConfigSupport::normalize($context, $setting);
        }
        return array('total' => count($rows), 'results' => $rows);
    }
}
