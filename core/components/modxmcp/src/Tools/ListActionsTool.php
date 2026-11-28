<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Инструмент MCP «List Actions»: выполняется через защищённый диспетчер.
 * EN: MCP tool «List Actions»: invoked through the guarded dispatcher.
 */
namespace ModxMcp\Tools;

class ListActionsTool implements ToolInterface
{
    public function name() { return 'list_actions'; }
    public function group() { return 'ops'; }
    public function isMutation() { return false; }
    public function supports($context)
    {
        return $context && $context->modx() && $context->platform();
    }

    public function execute($context, array $data)
    {
        return \ModxMcp\Registry\CapabilityService::groups();
    }
}
