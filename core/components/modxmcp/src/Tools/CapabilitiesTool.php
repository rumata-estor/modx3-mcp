<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Инструмент MCP «Capabilities»: выполняется через защищённый диспетчер.
 * EN: MCP tool «Capabilities»: invoked through the guarded dispatcher.
 */
namespace ModxMcp\Tools;

class CapabilitiesTool implements ToolInterface
{
    public function name() { return 'get_capabilities'; }
    public function group() { return 'ops'; }
    public function isMutation() { return false; }

    public function supports($context)
    {
        return $context && $context->modx() && $context->platform();
    }

    public function execute($context, array $arguments)
    {
        return \ModxMcp\Registry\CapabilityService::report($context->modx(), true);
    }
}
