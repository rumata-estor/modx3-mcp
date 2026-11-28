<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Явная проверка перед сохранением, не выполняющая и не сохраняющая шаблон.
 * EN: Explicit pre-save syntax check; never renders or persists the template.
 */
namespace ModxMcp\Tools;

final class TemplateCheckTool implements ToolInterface
{
    public function name() { return 'check_template'; }
    public function group() { return 'components'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx(); }
    public function execute($context, array $data)
    {
        if (!array_key_exists('content',$data) || array_diff(array_keys($data),array('content'))) {
            throw new \ModxMCPClientException('VALIDATION_ERROR: provide content only.');
        }
        return FenomSyntaxSupport::check($context->modx(),$data['content']);
    }
}
