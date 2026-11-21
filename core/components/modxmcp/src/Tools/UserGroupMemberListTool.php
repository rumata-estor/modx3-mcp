<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Инструмент MCP «User Group Member List»: выполняется через защищённый диспетчер.
 * EN: MCP tool «User Group Member List»: invoked through the guarded dispatcher.
 */
namespace ModxMcp\Tools;

class UserGroupMemberListTool implements ToolInterface
{
    public function name() { return 'list_user_group_members'; }
    public function group() { return 'access'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        return AclProcessorSupport::run(
            $context, 'security/group/user/getlist', $data, true
        );
    }
}
