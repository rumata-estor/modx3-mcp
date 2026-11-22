<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Инструмент MCP «Context Setting Get»: выполняется через защищённый диспетчер.
 * EN: MCP tool «Context Setting Get»: invoked through the guarded dispatcher.
 */
namespace ModxMcp\Tools;

class ContextSettingGetTool implements ToolInterface
{
    public function name() { return 'get_context_setting'; }
    public function group() { return 'contexts'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        $result = ProcessorSupport::run(
            $context, 'context/setting/get', $data, false,
            array('core:default', 'core:context', 'core:setting')
        );
        // RU: Штатный процессор также возвращает секреты как поле value.
        // EN: Native context processors can expose credential values.
        return SettingSecretSupport::contextResponse($result);
    }
}
