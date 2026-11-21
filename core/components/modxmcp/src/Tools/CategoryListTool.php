<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Инструмент MCP «Category List»: выполняется через защищённый диспетчер.
 * EN: MCP tool «Category List»: invoked through the guarded dispatcher.
 */
namespace ModxMcp\Tools;
class CategoryListTool implements ToolInterface
{
    public function name() { return 'list_categories'; }
    public function group() { return 'elements'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }
    public function execute($context, array $data) { return CategorySupport::listing($context, $data); }
}
