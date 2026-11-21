<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Инструмент MCP «User Group List»: выполняется через защищённый диспетчер.
 * EN: MCP tool «User Group List»: invoked through the guarded dispatcher.
 */
namespace ModxMcp\Tools;

class UserGroupListTool implements ToolInterface
{
    public function name() { return 'list_user_groups'; }
    public function group() { return 'access'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        return AclProcessorSupport::run(
            $context, 'security/group/getlist', $data, true
        );
    }
}
