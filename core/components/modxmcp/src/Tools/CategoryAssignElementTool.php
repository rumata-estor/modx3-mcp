<?php
namespace ModxMcp\Tools;
class CategoryAssignElementTool implements ToolInterface
{
    public function name() { return 'assign_element_category'; }
    public function group() { return 'elements'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }
    public function execute($context, array $data) { return CategorySupport::assignElement($context, $data); }
}
