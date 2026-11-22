<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Инструмент MCP «Replace Across»: выполняется через защищённый диспетчер.
 * EN: MCP tool «Replace Across»: invoked through the guarded dispatcher.
 */
namespace ModxMcp\Tools;

class ReplaceAcrossTool implements ToolInterface
{
    public function name() { return 'replace_across'; }
    public function group() { return 'code_search'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        $find = isset($data['find']) ? (string)$data['find'] : '';
        if ($find === '') {
            throw new \ModxMCPClientException(
                'replace_across: "find" is required.'
            );
        }
        if (!array_key_exists('replacement', $data)) {
            throw new \ModxMCPClientException(
                'replace_across: "replacement" is required (use "" to delete the string).'
            );
        }

        $replacement = (string)$data['replacement'];
        $caseSensitive = array_key_exists('case_sensitive', $data)
            ? !empty($data['case_sensitive'])
            : true;
        $dryRun = !empty($data['dry_run']);
        $allowed = array('chunk', 'snippet', 'template', 'plugin');
        $types = isset($data['types']) && is_array($data['types']) && $data['types']
            ? array_values(array_filter(
                $data['types'],
                function ($type) use ($allowed) {
                    return in_array($type, $allowed, true);
                }
            ))
            : $allowed;

        if (!$types) {
            throw new \ModxMCPClientException(
                'replace_across: types must be among chunk, snippet, template, plugin.'
            );
        }

        $limit = isset($data['limit']) ? max(1, (int)$data['limit']) : 200;
        $hits = SearchSupport::search(
            $context,
            array(
                'query' => $find,
                'types' => $types,
                'limit' => $limit,
                'case_sensitive' => $caseSensitive,
            )
        );

        $results = array();
        $totalOccurrences = 0;
        $attemptedWrites = 0;
        $prepared = array();

        // RU: Сначала читаем ВСЕ найденные элементы и проверяем пути.
        // RU: Ошибка чтения второй цели не должна оставлять первую изменённой.
        // EN: Read and validate ALL selected targets before any write.
        // EN: A second-target read failure must not mutate the first target.
        foreach ($hits['results'] as $hit) {
            $type = $hit['type'];
            if (!in_array($type, $allowed, true)) { continue; }
            $map = ElementContentSupport::map($context, $type);
            $element = $context->modx()->getObject(
                $map['class'], (int)$hit['id'], false
            );
            if (!$element) {
                throw new \ModxMCPClientException(
                    'NOT_FOUND: replace_across target ' . $type . ' '
                    . (int)$hit['id'] . ' is unavailable; no writes attempted.'
                );
            }
            list($content, $isStatic, $absolute) = ElementContentSupport::read(
                $context, $element, $map
            );
            $count = $caseSensitive
                ? substr_count($content, $find)
                : substr_count(strtolower($content), strtolower($find));
            if ($count === 0) { continue; }
            $prepared[] = array($hit, $type, $map, $element,
                $content, $isStatic, $absolute, $count);
        }

        // RU: Общая CAS-транзакция может откатить БД, но не статические
        // RU: файлы и события процессоров. После начала пакетной записи
        // RU: исключение требует независимой проверки всех объектов.
        // EN: A shared CAS transaction may roll back database rows, not
        // EN: static files or processor side effects. Any error after a
        // EN: batch write begins requires independent reconciliation.
        try {
        foreach ($prepared as $preparedTarget) {
            list($hit, $type, $map, $element, $content,
                $isStatic, $absolute, $count) = $preparedTarget;

            if (!$dryRun) {
                // RU: Между поиском и записью другой процесс мог изменить
                // RU: файл или поле статического элемента. Не перезаписываем
                // RU: данные из устаревшего снимка; после первых записей
                // RU: внешний результат пакета уже мог стать частичным.
                // EN: Recheck content and static target immediately before
                // EN: writing; preflight snapshots are not durable locks.
                $currentElement = $context->modx()->getObject(
                    $map['class'], (int)$hit['id'], false
                );
                if (!$currentElement) {
                    if ($attemptedWrites === 0) {
                        throw new \ModxMCPClientException(
                            'STALE_STATE: replace_across target disappeared '
                            . 'before the first write.'
                        );
                    }
                    throw new \ModxMCPClientException(
                        'MUTATION_OUTCOME_UNCERTAIN: replace_across target '
                        . 'disappeared during batch execution.'
                    );
                }
                list($currentContent, $currentStatic, $currentPath) =
                    ElementContentSupport::read($context, $currentElement, $map);
                if ($currentContent !== $content
                    || $currentStatic !== $isStatic
                    || $currentPath !== $absolute) {
                    if ($attemptedWrites === 0) {
                        throw new \ModxMCPClientException(
                            'STALE_STATE: replace_across target changed before '
                            . 'the first write; reload and prepare a fresh request.'
                        );
                    }
                    throw new \ModxMCPClientException(
                        'MUTATION_OUTCOME_UNCERTAIN: replace_across target '
                        . 'changed during batch execution; inspect all targets.'
                    );
                }
                $newContent = $caseSensitive
                    ? str_replace($find, $replacement, $content)
                    : str_ireplace($find, $replacement, $content);

                $attemptedWrites++;
                ElementMutationSupport::transaction(
                    $context,
                    function () use (
                        $context,
                        $currentElement,
                        $type,
                        $map,
                        $isStatic,
                        $absolute,
                        $newContent
                    ) {
                        ElementContentSupport::write(
                            $context,
                            $currentElement,
                            $type,
                            $map,
                            $isStatic,
                            $absolute,
                            $newContent
                        );
                        return true;
                    }
                );

                AuditSupport::log(
                    $context,
                    $this->name(),
                    $type,
                    array(
                        'id' => (int)$hit['id'],
                        'occurrences' => $count,
                    )
                );
            }

            $row = array(
                'type' => $type,
                'id' => (int)$hit['id'],
                'name' => $hit['name'],
                'occurrences' => $count,
                'static' => $isStatic,
            );

            if ($dryRun) {
                $lines = explode(
                    "\n",
                    str_replace("\r\n", "\n", $content)
                );
                $needle = $caseSensitive ? $find : strtolower($find);
                $preview = array();

                foreach ($lines as $index => $line) {
                    $haystack = $caseSensitive ? $line : strtolower($line);
                    if (strpos($haystack, $needle) !== false) {
                        $preview[] = array(
                            'line' => $index + 1,
                            'line_text' => $line,
                        );
                        if (count($preview) >= 5) { break; }
                    }
                }
                $row['preview'] = $preview;
            }

            $results[] = $row;
            $totalOccurrences += $count;
        }

        if (!$dryRun && $results) {
            ElementMutationSupport::refreshCache($context);
        }
        } catch (\Throwable $e) {
            if (!$dryRun && $attemptedWrites > 0) {
                // RU: При частичной записи кэш может содержать старые данные.
                // RU: Обновление — best effort; ошибка всё равно неопределённа.
                // EN: Partial effects may leave stale cache. Refresh on a
                // EN: best-effort basis, without claiming the batch succeeded.
                try { ElementMutationSupport::refreshCache($context); }
                catch (\Throwable $ignored) {}
                // RU: Даже неуспешная первая попытка могла изменить файл;
                // RU: не скрываем частичный результат пакетной операции.
                // EN: Even the first failed attempt may have changed a file;
                // EN: never report a clean rollback for the batch.
                throw new \ModxMCPClientException(
                    'MUTATION_OUTCOME_UNCERTAIN: replace_across attempted '
                    . $attemptedWrites . ' element writes before failure; '
                    . 'inspect affected elements and static files before retry.'
                );
            }
            throw $e;
        }

        return array(
            'find' => $find,
            'replacement' => $replacement,
            'case_sensitive' => $caseSensitive,
            'dry_run' => $dryRun,
            'elements' => count($results),
            'total_occurrences' => $totalOccurrences,
            'capped_at' => $limit,
            'results' => $results,
        );
    }
}
