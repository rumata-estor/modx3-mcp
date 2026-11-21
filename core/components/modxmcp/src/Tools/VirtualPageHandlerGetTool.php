<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Инструмент MCP «Virtual Page Handler Get»: выполняется через защищённый диспетчер.
 * EN: MCP tool «Virtual Page Handler Get»: invoked through the guarded dispatcher.
 */
namespace ModxMcp\Tools;

class VirtualPageHandlerGetTool implements ToolInterface
{
    public function name() { return 'virtualpage_get_handler'; }
    public function group() { return 'virtualpage'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx(); }

    public function execute($context, array $data)
    {
        $handler = VirtualPageSupport::resolveObject($context, 'vpHandler', $data);
        return VirtualPageSupport::normalizeHandler($context, $handler, true);
    }
}
