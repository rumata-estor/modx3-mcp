<?php
namespace ModxMcp\Tools;

class AccessPolicyGetTool implements ToolInterface
{
    public function name() { return 'get_access_policy'; }
    public function group() { return 'access'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        $id = isset($data['id']) ? (int)$data['id'] : 0;
        if ($id <= 0) {
            throw new \ModxMCPClientException('get_access_policy: id is required.');
        }
        $object = $context->modx()->getObject(
            $context->platform()->className('access_policy'),
            $id
        );
        if (!$object) {
            throw new \ModxMCPClientException('Access policy not found: ' . $id . '.');
        }
        return $object->toArray();
    }
}
