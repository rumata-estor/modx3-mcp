<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Инструмент MCP «Virtual Page Event Get»: выполняется через защищённый диспетчер.
 * EN: MCP tool «Virtual Page Event Get»: invoked through the guarded dispatcher.
 */
namespace ModxMcp\Tools;

class VirtualPageEventGetTool implements ToolInterface
{
    public function name() { return 'virtualpage_get_event'; }
    public function group() { return 'virtualpage'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx(); }

    public function execute($context, array $data)
    {
        $event = VirtualPageSupport::resolveObject($context, 'vpEvent', $data);
        return VirtualPageSupport::normalizeEvent($context, $event, true);
    }
}
