<?php
namespace ModxMcp\Tools;

class SystemSettingUpdateTool implements ToolInterface
{
    public function name() { return 'update_system_setting'; }
    public function group() { return 'system'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        $setting = $this->resolve($context, $data);
        if (!$setting) {
            throw new \ModxMCPClientException('System setting not found.');
        }

        $key = (string) $setting->get('key');
        if (strpos($key, 'modxmcp.') === 0
            || (isset($data['key']) && strpos((string) $data['key'], 'modxmcp.') === 0)
            || (isset($data['new_key']) && strpos((string) $data['new_key'], 'modxmcp.') === 0)
        ) {
            throw new \ModxMCPClientException(
                'Managing modxmcp.* settings via MCP API is not allowed; use the manager or regenerate_token.'
            );
        }

        $class = $context->platform()->className('system_setting');
        $originalKey = (string) $setting->get('key');
        $newKey = null;
        if (array_key_exists('new_key', $data)) {
            $newKey = (string) $data['new_key'];
            if ($newKey === '') {
                throw new \ModxMCPClientException('new_key must be a non-empty string.');
            }
            if ($newKey !== $originalKey
                && $context->modx()->getObject($class, array('key' => $newKey))
            ) {
                throw new \ModxMCPClientException(
                    'System setting already exists: ' . $newKey
                );
            }
            if ($newKey === $originalKey) {
                $newKey = null;
            }
        }

        foreach (array('value', 'xtype', 'namespace', 'area') as $field) {
            if (array_key_exists($field, $data)) {
                $setting->set($field, $data[$field]);
            }
        }

        if ($newKey !== null) {
            // RU: PK-поле 'key' нельзя менять через xPDOObject::save(): UPDATE WHERE
            // RU: строится по уже изменённому значению поля (xPDOObject::save()
            // RU: берёт getPrimaryKey() до записи), совпадений 0 — тихий no-op при
            // RU: формальном success. Переименование выполняем одним атомарным
            // RU: UPDATE по исходному ключу.
            // EN: The PK field 'key' cannot be renamed via xPDOObject::save(): the
            // EN: UPDATE WHERE clause is built from the already-modified value
            // EN: (getPrimaryKey() is read before the write), so zero rows match
            // EN: and save() still reports success. Rename as one atomic UPDATE
            // EN: on the original key instead.
            $set = array('key' => $newKey);
            foreach (array('value', 'xtype', 'namespace', 'area') as $field) {
                if (array_key_exists($field, $data)) {
                    $set[$field] = $data[$field];
                }
            }
            $updated = $context->modx()->updateCollection($class, $set, array('key' => $originalKey));
            if ($updated !== 1) {
                throw new \ModxMCPClientException(
                    'Failed to update system setting.'
                );
            }
            $setting = $context->modx()->getObject($class, array('key' => $newKey));
            if (!$setting) {
                throw new \ModxMCPClientException(
                    'MUTATION_OUTCOME_UNCERTAIN: system setting was renamed but readback failed.'
                );
            }
        } elseif (!$setting->save()) {
            throw new \ModxMCPClientException(
                'Failed to update system setting.'
            );
        }

        $context->modx()->cacheManager->refresh();
        AuditSupport::log(
            $context,
            $this->name(),
            'system_setting',
            array('key' => $setting->get('key'))
        );
        return SystemSettingListTool::normalize($setting);
    }

    private function resolve($context, array $data)
    {
        $class = $context->platform()->className('system_setting');
        if (!empty($data['key'])) {
            return $context->modx()->getObject(
                $class,
                array('key' => $data['key'])
            );
        }
        return null;
    }
}
