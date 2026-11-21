<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Инструмент MCP «Virtual Page Clear Cache»: выполняется через защищённый диспетчер.
 * EN: MCP tool «Virtual Page Clear Cache»: invoked through the guarded dispatcher.
 */
namespace ModxMcp\Tools;

class VirtualPageClearCacheTool implements ToolInterface
{
    public function name() { return 'virtualpage_clear_cache'; }
    public function group() { return 'virtualpage'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx(); }

    public function execute($context, array $data)
    {
        return VirtualPageSupport::clearCache($context);
    }
}
