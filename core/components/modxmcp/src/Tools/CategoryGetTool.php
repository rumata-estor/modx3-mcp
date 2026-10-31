<?php
namespace ModxMcp\Tools;
class CategoryGetTool implements ToolInterface
{
    public function name() { return 'get_category'; }
    public function group() { return 'elements'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }
    public function execute($context, array $data) { return CategorySupport::get($context, $data); }
}
