<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Инструмент MCP «Bulk Resources»: выполняется через защищённый диспетчер.
 * EN: MCP tool «Bulk Resources»: invoked through the guarded dispatcher.
 */
namespace ModxMcp\Tools;

class BulkResourcesTool implements ToolInterface
{
    public function name() { return 'bulk_resources'; }
    public function group() { return 'elements'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        $operation = isset($data['operation']) ? (string)$data['operation'] : '';
        $operations = array('publish', 'unpublish', 'set_template', 'move', 'delete');
        if (!in_array($operation, $operations, true)) {
            throw new \ModxMCPClientException(
                'bulk_resources: operation must be one of: '
                . implode(', ', $operations) . '.'
            );
        }

        $limit = isset($data['limit']) ? max(1, (int)$data['limit']) : 200;
        $ids = array();

        if (isset($data['ids']) && is_array($data['ids'])) {
            foreach ($data['ids'] as $id) {
                // RU: Молчаливое отбрасывание неверного ID может изменить
                // RU: лишь часть запрошенных ресурсов без предупреждения.
                // EN: Silently coercing invalid IDs can mutate only a
                // EN: subset of the caller's requested resource targets.
                $validId = (is_int($id) || is_string($id))
                    && filter_var($id, FILTER_VALIDATE_INT,
                        array('options' => array('min_range' => 1))) !== false;
                if (!$validId) {
                    throw new \ModxMCPClientException(
                        'VALIDATION_ERROR: bulk_resources ids must contain '
                        . 'only positive integer resource IDs; no writes attempted.'
                    );
                }
                $ids[] = (int)$id;
            }
        } elseif (
            isset($data['parent'])
            || !empty($data['context'])
            || !empty($data['query'])
        ) {
            $listed = (new ResourceListTool())->execute(
                $context,
                array(
                    'parent' => isset($data['parent']) ? $data['parent'] : '',
                    'context' => isset($data['context']) ? $data['context'] : '',
                    'query' => isset($data['query']) ? $data['query'] : '',
                    'limit' => $limit,
                )
            );
            foreach ($listed['results'] as $resource) {
                $ids[] = (int)$resource['id'];
            }
        }

        $ids = array_values(array_unique($ids));
        if (!$ids) {
            throw new \ModxMCPClientException(
                'bulk_resources: no targets — pass "ids" or a parent/context/query filter.'
            );
        }
        if (count($ids) > $limit) {
            // RU: Тихая обрезка списка создаёт ложный частичный успех.
            // EN: Silent limit truncation creates apparent partial success.
            throw new \ModxMCPClientException(
                'VALIDATION_ERROR: bulk_resources targets exceed limit; '
                . 'increase limit explicitly or reduce the target set.'
            );
        }

        if ($operation === 'set_template') {
            $valid = array_key_exists('template', $data)
                && $data['template'] !== null
                && $data['template'] !== ''
                && filter_var($data['template'], FILTER_VALIDATE_INT) !== false
                && (int)$data['template'] >= 0;
            if (!$valid) {
                throw new \ModxMCPClientException(
                    'bulk_resources: set_template requires "template" as a non-negative integer (0 = no template).'
                );
            }
        }

        if (
            $operation === 'move'
            && !isset($data['parent_to'])
            && empty($data['context_to'])
        ) {
            throw new \ModxMCPClientException(
                'bulk_resources: move requires "parent_to" and/or "context_to".'
            );
        }

        $dryRun = !empty($data['dry_run']);
        $results = array();
        $resourceClass = $context->platform()->className('resource');

        if (!$dryRun) {
            // RU: Если один из ID отсутствует, не начинаем менять другие.
            // RU: Предварительная проверка не заменяет CAS, но исключает
            // RU: заведомо частичное выполнение запроса с ошибочным списком.
            // EN: Reject an already-incomplete target set before the first
            // EN: mutation. Preflight does not replace mandatory CAS checks.
            foreach ($ids as $candidateId) {
                if (!$context->modx()->getObject($resourceClass, $candidateId)) {
                    throw new \ModxMCPClientException(
                        'NOT_FOUND: bulk_resources target ' . $candidateId
                        . ' does not exist; no batch writes attempted.'
                    );
                }
            }
        }
        $attemptedWrites = 0;
        foreach ($ids as $id) {
            $resource = $context->modx()->getObject($resourceClass, $id);
            if (!$resource) {
                if (!$dryRun) {
                    // RU: Объект исчез между preflight и выполнением:
                    // RU: предыдущие изменения уже могли произойти.
                    // EN: A target vanished after preflight; the batch
                    // EN: outcome is uncertain even if no write succeeded.
                    try { ElementMutationSupport::refreshCache($context); }
                    catch (\Throwable $ignored) {}
                    throw new \ModxMCPClientException(
                        'MUTATION_OUTCOME_UNCERTAIN: bulk_resources target '
                        . $id . ' vanished during execution; inspect targets.'
                    );
                }
                $results[] = array('id' => $id, 'status' => 'not_found');
                continue;
            }

            $row = array(
                'id' => $id,
                'pagetitle' => $resource->get('pagetitle'),
            );

            if ($dryRun) {
                if ($operation === 'publish') {
                    $row['change'] = 'published: '
                        . (int)$resource->get('published') . ' -> 1';
                } elseif ($operation === 'unpublish') {
                    $row['change'] = 'published: '
                        . (int)$resource->get('published') . ' -> 0';
                } elseif ($operation === 'set_template') {
                    $row['change'] = 'template: '
                        . (int)$resource->get('template')
                        . ' -> ' . (int)$data['template'];
                } elseif ($operation === 'move') {
                    $row['change'] = 'parent: ' . (int)$resource->get('parent')
                        . (isset($data['parent_to'])
                            ? ' -> ' . (int)$data['parent_to']
                            : '')
                        . (!empty($data['context_to'])
                            ? '; context -> ' . $data['context_to']
                            : '');
                } elseif ($operation === 'delete') {
                    $row['change'] = 'DELETE';
                    $row['child_resources'] = (int)$context->modx()->getCount(
                        $resourceClass,
                        array('parent' => $id)
                    );
                }
                $results[] = $row;
                continue;
            }

            try {
                if ($operation === 'delete') {
                    (new ElementDeleteTool())->execute(
                        $context,
                        array('type' => 'resource', 'id' => $id)
                    );
                    $row['status'] = 'deleted';
                } else {
                    $update = array('type' => 'resource', 'id' => $id);
                    if ($operation === 'publish') {
                        $update['published'] = 1;
                    } elseif ($operation === 'unpublish') {
                        $update['published'] = 0;
                    } elseif ($operation === 'set_template') {
                        $update['template'] = (int)$data['template'];
                    } elseif ($operation === 'move') {
                        if (isset($data['parent_to'])) {
                            $update['parent'] = (int)$data['parent_to'];
                        }
                        if (!empty($data['context_to'])) {
                            $update['context_key'] = (string)$data['context_to'];
                        }
                    }
                    (new ElementUpdateTool())->execute($context, $update);
                    $row['status'] = 'ok';
                }
            } catch (\Throwable $e) {
                // RU: Частичный отказ нельзя выдавать за успешную операцию.
                // RU: Транзакция CAS может откатить БД, но не события MODX,
                // RU: файлы или другие внешние побочные действия.
                // EN: A per-resource failure must not become a successful
                // EN: batch response. CAS rollback cannot undo plugins,
                // EN: files or other external processor side effects.
                try { ElementMutationSupport::refreshCache($context); }
                catch (\Throwable $ignored) {}
                throw new \ModxMCPClientException(
                    'MUTATION_OUTCOME_UNCERTAIN: bulk_resources failed while '
                    . 'processing resource ' . $id . ' (' . $operation . '); '
                    . 'inspect all batch targets before retry.'
                );
            }
            $results[] = $row;
        }

        if (!$dryRun) {
            ElementMutationSupport::refreshCache($context);
        }
        AuditSupport::log(
            $context,
            $this->name(),
            'resource',
            array(
                'operation' => $operation,
                'count' => count($ids),
                'dry_run' => $dryRun,
            )
        );

        return array(
            'operation' => $operation,
            'dry_run' => $dryRun,
            'count' => count($results),
            'results' => $results,
        );
    }
}
