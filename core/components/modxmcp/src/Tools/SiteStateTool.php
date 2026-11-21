<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Инструмент MCP «Site State»: выполняется через защищённый диспетчер.
 * EN: MCP tool «Site State»: invoked through the guarded dispatcher.
 */
namespace ModxMcp\Tools;

class SiteStateTool implements ToolInterface
{
    public function name() { return 'get_site_state'; }
    public function group() { return 'ops'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx(); }

    public function execute($context, array $arguments)
    {
        return StateSupport::observedState($context, $arguments);
    }
}
