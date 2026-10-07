<?php
namespace ModxMcp\Tools;

class ResourceGroupGetTool implements ToolInterface
{
    public function name() { return 'get_resource_group'; }
    public function group() { return 'access'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        $id = isset($data['id']) ? (int)$data['id'] : 0;
        if ($id <= 0) {
            throw new \ModxMCPClientException('get_resource_group: id is required.');
        }
        $object = $context->modx()->getObject(
            $context->platform()->className('resource_group'),
            $id
        );
        if (!$object) {
            throw new \ModxMCPClientException(
                'Resource group not found: ' . $id . '.'
            );
        }
        return $object->toArray();
    }
}
