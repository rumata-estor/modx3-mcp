<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Инструмент MCP «Ms2 Order Update»: выполняется через защищённый диспетчер.
 * EN: MCP tool «Ms2 Order Update»: invoked through the guarded dispatcher.
 */
namespace ModxMcp\Tools;

class Ms2OrderUpdateTool implements ToolInterface
{
    public function name() { return 'ms2_update_order'; }
    public function group() { return 'minishop2'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx(); }

    public function execute($context, array $data)
    {
        $payload = MiniShop2Support::cleanPayload($data);
        // RU: Не превращать отсутствие ID в неизвестный исход после процессора.
        // EN: Reject missing order target before any mutation is attempted.
        $payload['id'] = MiniShop2Support::positiveInt($data, 'id');

        // RU: Отсутствие компонента — доказанный отказ до записи заказа.
        // EN: Missing addon is a pre-dispatch error, not a partial order write.
        MiniShop2Support::load($context);
        try {
            $result = MiniShop2Support::runProcessor(
                $context,
                'mgr/orders/update',
                $payload
            );
            MiniShop2MutationSupport::auditProcessor(
                $context,
                $this->name(),
                $payload,
                array('id', 'status', 'delivery', 'payment')
            );
            return $result;
        } catch (\Throwable $error) {
            // RU: Upstream сохраняет объект ДО смены статуса; ошибка, отсутствие
            // RU: ответа или сбой аудита не доказывают отсутствие записи.
            // EN: Native processor may save before status transition; no retry
            // EN: is safe without independently confirming the order state.
            throw new \ModxMCPClientException(
                'MUTATION_OUTCOME_UNCERTAIN: miniShop2 order update may have '
                . 'partially changed the order; inspect via ms2_get_order '
                . 'before any new authorized mutation.'
            );
        }
    }
}
