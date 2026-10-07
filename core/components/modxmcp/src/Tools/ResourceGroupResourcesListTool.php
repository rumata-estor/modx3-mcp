<?php
namespace ModxMcp\Tools;

class ResourceGroupResourcesListTool implements ToolInterface
{
    public function name() { return 'list_resource_group_resources'; }
    public function group() { return 'access'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        $group = isset($data['resourceGroup'])
            ? (int)$data['resourceGroup']
            : (isset($data['resource_group']) ? (int)$data['resource_group'] : 0);
        if ($group <= 0) {
            throw new \ModxMCPClientException(
                'list_resource_group_resources: resourceGroup is required.'
            );
        }
        $class = $context->platform()->className('resource_group_resource');
        $rows = array();
        foreach ($context->modx()->getCollection(
            $class,
            array('document_group' => $group)
        ) as $link) {
            $rows[] = array(
                'resourceGroup' => $group,
                'resource' => (int)$link->get('document'),
            );
        }
        return array('total' => count($rows), 'results' => $rows);
    }
}
