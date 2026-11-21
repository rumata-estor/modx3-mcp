<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Инструмент MCP «Ms2 Order Get»: выполняется через защищённый диспетчер.
 * EN: MCP tool «Ms2 Order Get»: invoked through the guarded dispatcher.
 */
namespace ModxMcp\Tools;

class Ms2OrderGetTool implements ToolInterface
{
    public function name() { return 'ms2_get_order'; }
    public function group() { return 'minishop2'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx(); }

    public function execute($context, array $data)
    {
        return MiniShop2Support::runProcessor(
            $context,
            'mgr/orders/get',
            MiniShop2Support::cleanPayload($data)
        );
    }
}
