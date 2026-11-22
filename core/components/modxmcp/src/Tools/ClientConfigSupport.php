<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Вспомогательная логика «Client Config» для инструментов MODX MCP.
 * EN: Shared «Client Config» implementation used by MODX MCP tools.
 */
namespace ModxMcp\Tools;

class ClientConfigSupport
{
    public static function modelPath($context)
    {
        $modx = $context->modx();
        $core = $modx->getOption(
            'clientconfig.core_path',
            null,
            rtrim((string)$modx->getOption('core_path'), '/\\')
                . DIRECTORY_SEPARATOR . 'components'
                . DIRECTORY_SEPARATOR . 'clientconfig'
                . DIRECTORY_SEPARATOR
        );
        $model = rtrim((string)$core, '/\\')
            . DIRECTORY_SEPARATOR . 'model' . DIRECTORY_SEPARATOR;
        if (!is_dir($model . 'clientconfig')) {
            throw new \ModxMCPClientException(
                'ClientConfig is not installed or its model path is unavailable.'
            );
        }
        $modx->addPackage('clientconfig', $model);
        return $model;
    }

    public static function setting($context, array $data)
    {
        self::modelPath($context);
        $criteria = array();
        if (!empty($data['id'])) {
            $criteria['id'] = (int)$data['id'];
        } elseif (!empty($data['key'])) {
            $criteria['key'] = (string)$data['key'];
        } else {
            throw new \ModxMCPClientException(
                'ClientConfig setting requires id or key.'
            );
        }
        return $context->modx()->getObject('cgSetting', $criteria);
    }

    public static function normalize($context, $setting)
    {
        $out = $setting->toArray();
        $out['context_values'] = array();
        $values = $setting->getMany('ContextValues');
        if (is_array($values)) {
            foreach ($values as $value) {
                $out['context_values'][(string)$value->get('context')]
                    = $value->get('value');
            }
        }
        return $out;
    }

    public static function applyFields($setting, array $data)
    {
        foreach (array(
            'key', 'label', 'xtype', 'description', 'is_required',
            'sortorder', 'value', 'default', 'group', 'options',
            'process_options', 'source'
        ) as $field) {
            if (array_key_exists($field, $data)) {
                $setting->set($field, $data[$field]);
            }
        }
    }

    // RU: Проверяем контексты до сохранения основной настройки.
    // EN: Reject invalid context-value payloads before the first DB write.
    public static function validateContextValues(array $data)
    {
        if (!array_key_exists('context_values', $data)) return;
        if (!is_array($data['context_values'])) {
            throw new \ModxMCPClientException('context_values must be an object/map.');
        }
        foreach ($data['context_values'] as $contextKey => $value) {
            if (!is_string($contextKey) || $contextKey === '') {
                throw new \ModxMCPClientException('Invalid ClientConfig context key.');
            }
            if ($value !== null && !is_scalar($value)) {
                throw new \ModxMCPClientException(
                    'ClientConfig context value must be scalar or null.'
                );
            }
        }
    }

    // RU: Не обещаем откат после частично успешной записи дочерних настроек.
    // EN: Post-save context failures are uncertain, not rolled back by CAS.
    public static function applyContextValuesAfterSave($context, $setting, array $data)
    {
        try {
            self::applyContextValues($context, $setting, $data);
        } catch (\Throwable $e) {
            // RU: Даже при частичном сохранении инвалидируем кеш, чтобы
            // RU: MODX не продолжал читать заведомо устаревшую конфигурацию.
            // EN: Best-effort cache invalidation after a partial write.
            try {
                self::refresh($context);
            } catch (\Throwable $ignored) {
                // Preserve the uncertain outcome and do not disclose internals.
            }
            throw new \ModxMCPClientException(
                'MUTATION_OUTCOME_UNCERTAIN: ClientConfig context values may be partially saved.'
            );
        }
    }

    public static function applyContextValues($context, $setting, array $data)
    {
        if (!array_key_exists('context_values', $data)) {
            return;
        }
        if (!is_array($data['context_values'])) {
            throw new \ModxMCPClientException(
                'context_values must be an object/map.'
            );
        }
        $modx = $context->modx();
        foreach ($data['context_values'] as $contextKey => $value) {
            $criteria = array(
                'setting' => (int)$setting->get('id'),
                'context' => (string)$contextKey,
            );
            $row = $modx->getObject('cgContextValue', $criteria);
            if ($value === null) {
                if ($row && !$row->remove()) {
                    throw new \ModxMCPClientException(
                        'Failed to remove ClientConfig context value.'
                    );
                }
                continue;
            }
            if (!$row) {
                $row = $modx->newObject('cgContextValue');
                $row->set('setting', (int)$setting->get('id'));
                $row->set('context', (string)$contextKey);
            }
            $row->set('value', (string)$value);
            if (!$row->save()) {
                throw new \ModxMCPClientException(
                    'Failed to save ClientConfig context value.'
                );
            }
        }
    }

    public static function refresh($context)
    {
        $modx = $context->modx();
        $manager = $modx->getCacheManager();
        if (!$manager) {
            throw new \ModxMCPClientException(
                'MUTATION_OUTCOME_UNCERTAIN: ClientConfig was saved but cache manager is unavailable.'
            );
        }
        // RU: ClientConfig хранит собственный ключ clientconfig в разделе
        // RU: system_settings; общий refresh() не гарантирует его удаления.
        // EN: ClientConfig uses the clientconfig key in system_settings;
        // EN: generic refresh() is not a reliable substitute for deletion.
        $options = array('cache_key' => 'system_settings');
        $manager->delete('clientconfig', $options);
        // RU: Проверяем, что устаревшая запись не осталась доступна.
        // EN: Verify the stale cache entry is no longer readable.
        if ($manager->get('clientconfig', $options) !== null) {
            throw new \ModxMCPClientException(
                'MUTATION_OUTCOME_UNCERTAIN: ClientConfig was saved but cache invalidation failed.'
            );
        }
        // RU: Очистка ресурса соответствует настройке самого дополнения.
        // EN: Respect ClientConfig's own resource-cache clear switch.
        if ($modx->getOption('clientconfig.clear_cache', null, true)) {
            $manager->delete('', array('cache_key' => 'resource'));
        }
        $modx->invokeEvent('ClientConfig_ConfigChange');
    }
}
