<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Инструмент MCP «Resource Tv Update»: выполняется через защищённый диспетчер.
 * EN: MCP tool «Resource Tv Update»: invoked through the guarded dispatcher.
 */
namespace ModxMcp\Tools;

class ResourceTvUpdateTool implements ToolInterface
{
    public function name() { return 'update_resource_tvs'; }
    public function group() { return 'resource_tvs'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        return $this->applyUpdate($context, $data, false);
    }

    /**
     * Used only by the modular dispatcher while a database transaction is
     * already open. The dispatcher commits the TV values and site revision as
     * one unit. Do not expose the transaction mode as client-supplied data.
     */
    public function executeWithinRevisionTransaction($context, array $data)
    {
        return $this->applyUpdate($context, $data, true);
    }

    public function afterCommittedRevision($context, array $data)
    {
        $context->modx()->cacheManager->refresh();
        AuditSupport::log(
            $context,
            $this->name(),
            'resource_tv',
            array(
                'resource_id' => (int)$data['resource_id'],
                'tv_keys' => array_keys($data['tvs']),
            )
        );
    }

    /**
     * RU: Строки уже содержат сырое значение TV. Массив допустим только
     * RU: для listbox-multiple; нельзя допускать вложения и разделитель ||.
     * EN: Strings are already raw TV values. Arrays are accepted only for
     * EN: listbox-multiple, without nested values or embedded || delimiters.
     */
    public static function normalizeMultiValue($tv, $value)
    {
        if (is_string($value)) { return $value; }
        if (!is_array($value) || (string)$tv->get('type') !== 'listbox-multiple') {
            throw new \ModxMCPClientException(
                'TV array values are only supported for listbox-multiple.'
            );
        }
        $parts = array();
        foreach ($value as $item) {
            if (!is_string($item) && !is_int($item)) {
                throw new \ModxMCPClientException('Invalid listbox-multiple TV entry.');
            }
            $item = (string)$item;
            if ($item === '' || strpos($item, '||') !== false) {
                throw new \ModxMCPClientException('Invalid listbox-multiple TV separator.');
            }
            $parts[] = $item;
        }
        return implode('||', $parts);
    }

    private function applyUpdate($context, array $data, $outerTransaction)
    {
        $resourceId = !empty($data['resource_id'])
            ? (int)$data['resource_id']
            : 0;
        if ($resourceId <= 0) {
            throw new \ModxMCPClientException('resource_id is required.');
        }
        if (empty($data['tvs']) || !is_array($data['tvs'])) {
            throw new \ModxMCPClientException(
                'tvs payload must be a non-empty object/array.'
            );
        }

        $resource = $context->modx()->getObject(
            $context->platform()->className('resource'),
            $resourceId
        );
        if (!$resource) {
            throw new \ModxMCPClientException(
                'Resource not found: ' . $resourceId . '.'
            );
        }

        // The dispatcher's site-wide mutation lock and CAS guard execute
        // before this tool is called. Make multi-TV changes all-or-nothing,
        // and reject unknown/unassigned TV names before any write.
        $modx = $context->modx();
        $templateId = (int)$resource->get('template');
        $tvObjects = array();
        foreach ($data['tvs'] as $name => $value) {
            if (!is_string($name) || $name === ''
                || (!is_string($value) && !is_array($value))) {
                throw new \ModxMCPClientException(
                    'TV updates require non-empty TV names and string values '
                    . '(arrays only for listbox-multiple).'
                );
            }
            $tv = $modx->getObject(
                $context->platform()->className('tv'),
                array('name' => $name)
            );
            if (!$tv) {
                throw new \ModxMCPClientException('Unknown TV: ' . $name);
            }
            $link = $modx->getObject(
                $context->platform()->className('template_var_template'),
                array('tmplvarid' => $tv->get('id'), 'templateid' => $templateId)
            );
            if (!$link) {
                throw new \ModxMCPClientException(
                    'TV is not assigned to the resource template: ' . $name
                );
            }
            // RU: Массивы разрешены только для listbox-multiple. Сохраняем
            // RU: стандартный MODX-разделитель, не меняя другие TV.
            // EN: Arrays are valid only for listbox-multiple; serialize via
            // EN: MODX's || delimiter without touching unrelated TV values.
            $data['tvs'][$name] = self::normalizeMultiValue($tv, $value);
            $tvObjects[$name] = $tv;
        }
        // Execute the field-level compare under the same connector-wide
        // mutation lock as the site revision check. This catches an edit
        // that bypassed revision tracking before we reached the lock.
        $guard = isset($data['_runtime_preconditions'])
            && is_array($data['_runtime_preconditions'])
            ? $data['_runtime_preconditions'] : null;
        if ($guard !== null) {
            $expected = isset($guard['expected_tvs'])
                && is_array($guard['expected_tvs'])
                ? $guard['expected_tvs'] : null;
            if ($expected === null
                || count($expected) !== count($data['tvs'])
                || array_diff_key($expected, $data['tvs'])
                || array_diff_key($data['tvs'], $expected)
            ) {
                throw new \ModxMCPClientException(
                    'Resource TV CAS requires matching expected_tvs names.'
                );
            }
            foreach ($expected as $name => $beforeValue) {
                if (!method_exists($tvObjects[$name], 'getValue')) {
                    throw new \ModxMCPClientException('Raw TV CAS is unavailable.');
                }
                $actual = (string)$tvObjects[$name]->getValue($resourceId);
                if (!is_string($beforeValue) || $actual !== $beforeValue) {
                    throw new \ModxMCPClientException(
                        'STALE_STATE {"status":"STALE_STATE","reason":"tv_values_changed"}'
                    );
                }
            }
        }

        // The normal entrypoint retains its independent TV transaction for
        // direct tool users. The dispatcher entrypoint must NOT commit here:
        // the site_revision update is performed in its parent transaction.
        if (!$outerTransaction && $modx->beginTransaction() === false) {
            throw new \RuntimeException('Could not begin resource TV transaction.');
        }
        try {
            foreach ($data['tvs'] as $name => $value) {
                // RU: MODX Manager не использует блокировку MCP. Повторно
                // RU: сравниваем штатное raw TV непосредственно перед записью.
                // EN: Manager does not take the MCP lock. Recheck the native
                // EN: TV raw value immediately before every setTVValue call.
                // EN: This reduces (but cannot eliminate) the external race.
                if ($guard !== null) {
                    $current = (string)$tvObjects[$name]->getValue($resourceId);
                    if (!hash_equals($expected[$name], $current)) {
                        throw new \ModxMCPClientException(
                            'STALE_STATE: TV changed outside MCP; compare current value in MODX Manager.'
                        );
                    }
                }
                $written = ($resource->setTVValue($name, $value) !== false);
                if (!$written) {
                    throw new \ModxMCPClientException('Failed to write TV: ' . $name);
                }
            }
            if (!$outerTransaction && $modx->commit() === false) {
                throw new \RuntimeException('Could not commit resource TV transaction.');
            }
        } catch (\Throwable $e) {
            if (!$outerTransaction) {
                $modx->rollback();
            }
            throw $e;
        }

        // Do not emit an audit entry or refresh caches until the outer
        // transaction successfully commits. A failed revision must be silent.
        if (!$outerTransaction) {
            $this->afterCommittedRevision($context, $data);
        }
        return (new ResourceTvListTool())->execute(
            $context,
            array('resource_id' => $resourceId)
        );
    }
}
