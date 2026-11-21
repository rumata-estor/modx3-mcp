<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Инструмент MCP «Context Setting List»: выполняется через защищённый диспетчер.
 * EN: MCP tool «Context Setting List»: invoked through the guarded dispatcher.
 */
namespace ModxMcp\Tools;

class ContextSettingListTool implements ToolInterface
{
    public function name() { return 'list_context_settings'; }
    public function group() { return 'contexts'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        return ProcessorSupport::run(
            $context, 'context/setting/getlist', $data, true,
            array('core:default', 'core:context', 'core:setting')
        );
    }
}
