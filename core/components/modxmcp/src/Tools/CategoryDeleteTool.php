<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Инструмент MCP «Category Delete»: выполняется через защищённый диспетчер.
 * EN: MCP tool «Category Delete»: invoked through the guarded dispatcher.
 */
namespace ModxMcp\Tools;
class CategoryDeleteTool implements ToolInterface
{
    public function name() { return 'delete_category'; }
    public function group() { return 'elements'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }
    public function execute($context, array $data) { return CategorySupport::delete($context, $data); }
}
