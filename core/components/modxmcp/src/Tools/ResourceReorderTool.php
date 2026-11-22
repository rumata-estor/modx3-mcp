<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Инструмент MCP «Resource Reorder»: выполняется через защищённый диспетчер.
 * EN: MCP tool «Resource Reorder»: invoked through the guarded dispatcher.
 */
namespace ModxMcp\Tools;

class ResourceReorderTool implements ToolInterface
{
    public function name() { return 'reorder_resources'; }
    public function group() { return 'elements'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        $items = isset($data['items']) && is_array($data['items'])
            ? $data['items']
            : null;
        if (!$items) {
            throw new \ModxMCPClientException(
                'reorder_resources: "items" (list of {id, menuindex, parent?}) is required.'
            );
        }

        // RU: Проверяем весь набор до первого изменения. Иначе ошибочный
        // RU: второй ID оставляет первую страницу изменённой при успехе MCP.
        // EN: Validate every target before mutating any resource: returning
        // EN: an OK response with skipped/failed rows hides partial writes.
        $resourceClass = $context->platform()->className('resource');
        $seen = array();
        foreach ($items as $item) {
            if (!is_array($item)
                || !array_key_exists('id', $item)
                || !array_key_exists('menuindex', $item)
                || !in_array(gettype($item['id']), array('integer', 'string'), true)
                || filter_var($item['id'], FILTER_VALIDATE_INT,
                    array('options' => array('min_range' => 1))) === false
                || !in_array(gettype($item['menuindex']), array('integer', 'string'), true)
                || filter_var($item['menuindex'], FILTER_VALIDATE_INT,
                    array('options' => array('min_range' => 0))) === false
                || (array_key_exists('parent', $item)
                    && (!in_array(gettype($item['parent']), array('integer', 'string'), true)
                        || filter_var($item['parent'], FILTER_VALIDATE_INT,
                            array('options' => array('min_range' => 0))) === false))) {
                throw new \ModxMCPClientException(
                    'VALIDATION_ERROR: reorder_resources requires positive '
                    . 'resource IDs, nonnegative menuindex and parent; no writes attempted.'
                );
            }
            $id = (int)$item['id'];
            if (isset($seen[$id])) {
                throw new \ModxMCPClientException(
                    'VALIDATION_ERROR: reorder_resources duplicate resource ID; '
                    . 'no writes attempted.'
                );
            }
            $seen[$id] = true;
        }
        foreach (array_keys($seen) as $id) {
            if (!$context->modx()->getObject($resourceClass, $id)) {
                throw new \ModxMCPClientException(
                    'NOT_FOUND: reorder_resources resource ' . $id
                    . ' does not exist; no writes attempted.'
                );
            }
        }

        $results = array();
        foreach ($items as $item) {
            $update = array(
                'type' => 'resource',
                'id' => (int)$item['id'],
                'menuindex' => (int)$item['menuindex'],
            );
            if (isset($item['parent'])) {
                $update['parent'] = (int)$item['parent'];
            }

            try {
                (new ElementUpdateTool())->execute($context, $update);
            } catch (\Throwable $e) {
                // RU: После частичного выполнения результат неизвестен;
                // RU: не раскрываем исходное сообщение исключения/секреты.
                // EN: The batch may have partially committed; never return
                // EN: a successful mixed-status response or leak errors.
                try { ElementMutationSupport::refreshCache($context); }
                catch (\Throwable $ignored) {}
                throw new \ModxMCPClientException(
                    'MUTATION_OUTCOME_UNCERTAIN: reorder_resources failed while '
                    . 'updating resource ' . (int)$item['id']
                    . '; inspect all target resources before retry.'
                );
            }
            $results[] = array(
                'id' => (int)$item['id'],
                'status' => 'ok',
                'menuindex' => (int)$item['menuindex'],
            );
        }

        ElementMutationSupport::refreshCache($context);
        AuditSupport::log(
            $context,
            $this->name(),
            'resource',
            array('count' => count($results))
        );
        return array('count' => count($results), 'results' => $results);
    }
}
