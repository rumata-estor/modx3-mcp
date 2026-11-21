<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Инструмент MCP «Access Policy Template List»: выполняется через защищённый диспетчер.
 * EN: MCP tool «Access Policy Template List»: invoked through the guarded dispatcher.
 */
namespace ModxMcp\Tools;

class AccessPolicyTemplateListTool implements ToolInterface
{
    public function name() { return 'list_access_policy_templates'; }
    public function group() { return 'access'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        return AclProcessorSupport::run(
            $context, 'security/access/policy/template/getlist', $data, true
        );
    }
}
