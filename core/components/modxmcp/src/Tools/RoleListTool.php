<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Инструмент MCP «Role List»: выполняется через защищённый диспетчер.
 * EN: MCP tool «Role List»: invoked through the guarded dispatcher.
 */
namespace ModxMcp\Tools;

class RoleListTool implements ToolInterface
{
    public function name() { return 'list_roles'; }
    public function group() { return 'access'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        return AclProcessorSupport::run(
            $context, 'security/role/getlist', $data, true
        );
    }
}
