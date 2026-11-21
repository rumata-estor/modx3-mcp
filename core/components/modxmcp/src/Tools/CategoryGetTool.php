<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Инструмент MCP «Category Get»: выполняется через защищённый диспетчер.
 * EN: MCP tool «Category Get»: invoked through the guarded dispatcher.
 */
namespace ModxMcp\Tools;
class CategoryGetTool implements ToolInterface
{
    public function name() { return 'get_category'; }
    public function group() { return 'elements'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }
    public function execute($context, array $data) { return CategorySupport::get($context, $data); }
}
