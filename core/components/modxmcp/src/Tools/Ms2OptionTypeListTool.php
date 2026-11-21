<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Инструмент MCP «Ms2 Option Type List»: выполняется через защищённый диспетчер.
 * EN: MCP tool «Ms2 Option Type List»: invoked through the guarded dispatcher.
 */
namespace ModxMcp\Tools;

class Ms2OptionTypeListTool implements ToolInterface
{
    public function name() { return 'ms2_list_option_types'; }
    public function group() { return 'minishop2'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx(); }

    public function execute($context, array $data)
    {
        return MiniShop2Support::runProcessor(
            $context,
            'mgr/settings/option/gettypes',
            $data
        );
    }
}
