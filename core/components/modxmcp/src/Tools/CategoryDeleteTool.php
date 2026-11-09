<?php
namespace ModxMcp\Tools;
class CategoryDeleteTool implements ToolInterface
{
    public function name() { return 'delete_category'; }
    public function group() { return 'elements'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }
    public function execute($context, array $data) { return CategorySupport::delete($context, $data); }
}
