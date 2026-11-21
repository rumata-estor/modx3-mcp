<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Инструмент MCP «Context Get»: выполняется через защищённый диспетчер.
 * EN: MCP tool «Context Get»: invoked through the guarded dispatcher.
 */
namespace ModxMcp\Tools;

class ContextGetTool implements ToolInterface
{
    public function name() { return 'get_context'; }
    public function group() { return 'contexts'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        return ProcessorSupport::run(
            $context, 'context/get', $data, false,
            array('core:default', 'core:context', 'core:setting')
        );
    }
}
