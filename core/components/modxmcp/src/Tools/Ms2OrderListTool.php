<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Инструмент MCP «Ms2 Order List»: выполняется через защищённый диспетчер.
 * EN: MCP tool «Ms2 Order List»: invoked through the guarded dispatcher.
 */
namespace ModxMcp\Tools;

class Ms2OrderListTool implements ToolInterface
{
    public function name() { return 'ms2_list_orders'; }
    public function group() { return 'minishop2'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx(); }

    public function execute($context, array $data)
    {
        $payload = MiniShop2Support::cleanPayload($data);
        if (!isset($payload['limit'])) { $payload['limit'] = 0; }
        return MiniShop2Support::runProcessor(
            $context, 'mgr/orders/getlist', $payload
        );
    }
}
