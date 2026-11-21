<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Инструмент MCP «Category Create»: выполняется через защищённый диспетчер.
 * EN: MCP tool «Category Create»: invoked through the guarded dispatcher.
 */
namespace ModxMcp\Tools;
class CategoryCreateTool implements ToolInterface
{
    public function name() { return 'create_category'; }
    public function group() { return 'elements'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }
    public function execute($context, array $data) { return CategorySupport::create($context, $data); }
}
