<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Инструмент MCP «Search Code»: выполняется через защищённый диспетчер.
 * EN: MCP tool «Search Code»: invoked through the guarded dispatcher.
 */
namespace ModxMcp\Tools;

class SearchCodeTool implements ToolInterface
{
    public function name() { return 'search_code'; }
    public function group() { return 'elements'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }
    public function execute($context, array $data) { return SearchSupport::search($context, $data); }
}
