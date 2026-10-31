<?php
namespace ModxMcp\Tools;
class CategoryUpdateTool implements ToolInterface
{
    public function name() { return 'update_category'; }
    public function group() { return 'elements'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }
    public function execute($context, array $data) { return CategorySupport::update($context, $data); }
}
