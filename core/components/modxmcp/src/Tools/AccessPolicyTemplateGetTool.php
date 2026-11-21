<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Инструмент MCP «Access Policy Template Get»: выполняется через защищённый диспетчер.
 * EN: MCP tool «Access Policy Template Get»: invoked through the guarded dispatcher.
 */
namespace ModxMcp\Tools;

class AccessPolicyTemplateGetTool implements ToolInterface
{
    public function name() { return 'get_access_policy_template'; }
    public function group() { return 'access'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        $id = isset($data['id']) ? (int)$data['id'] : 0;
        if ($id <= 0) {
            throw new \ModxMCPClientException(
                'get_access_policy_template: id is required.'
            );
        }
        $object = $context->modx()->getObject(
            $context->platform()->className('access_policy_template'),
            $id
        );
        if (!$object) {
            throw new \ModxMCPClientException(
                'Access policy template not found: ' . $id . '.'
            );
        }
        return $object->toArray();
    }
}
