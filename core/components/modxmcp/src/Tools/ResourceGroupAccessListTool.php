<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Инструмент MCP «Resource Group Access List»: выполняется через защищённый диспетчер.
 * EN: MCP tool «Resource Group Access List»: invoked through the guarded dispatcher.
 */
namespace ModxMcp\Tools;

class ResourceGroupAccessListTool implements ToolInterface
{
    public function name() { return 'list_resourcegroup_access'; }
    public function group() { return 'access'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        return AclProcessorSupport::run(
            $context, 'security/access/usergroup/resourcegroup/getlist', $data, true
        );
    }
}
