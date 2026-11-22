<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Инструмент MCP «System Setting Update»: выполняется через защищённый диспетчер.
 * EN: MCP tool «System Setting Update»: invoked through the guarded dispatcher.
 */
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
        // RU: Проверяем исходный и новый ключ, в том числе при обновлении по ID.
        // EN: Validate existing and replacement keys, including ID-based updates.
        \ModxMcp\Security\ProtectedSettingsGuard::assertMutableKey($key);
        if (isset($data['key'])) {
            \ModxMcp\Security\ProtectedSettingsGuard::assertMutableKey(
                (string)$data['key']
            );
        }

        // RU: Переименование предложено @AgelxNash, PR #56.
        // RU: Изменение первичного key через xPDOObject::save() создаёт
        // RU: ложный success (UPDATE WHERE уже по новому ключу).
        // EN: Reported by @AgelxNash, PR #56: xPDOObject::save() can
        // EN: report success while renaming a primary key updates zero rows.
        $modx = $context->modx();
        $class = $context->platform()->className('system_setting');
        $newKey = null;
        if (array_key_exists('new_key', $data)) {
            if (!is_string($data['new_key']) || trim($data['new_key']) === '') {
                throw new \ModxMCPClientException('new_key must be a non-empty string.');
            }
            $newKey = $data['new_key'];
            \ModxMcp\Security\ProtectedSettingsGuard::assertMutableKey($newKey);
            if ($newKey !== $key
                && $modx->getObject($class, array('key' => $newKey))) {
                throw new \ModxMCPClientException(
                    'System setting already exists: ' . $newKey
                );
            }
            if ($newKey === $key) { $newKey = null; }
        }
        $changes = array();
        foreach (array('value', 'xtype', 'namespace', 'area') as $field) {
            if (array_key_exists($field, $data)) {
                $changes[$field] = $data[$field];
            }
        }
        if ($newKey !== null) {
            // RU: Один UPDATE по исходному ключу внутри общей DB-CAS транзакции.
            // EN: One UPDATE on the original key, under the DB-CAS transaction.
            $updated = $modx->updateCollection(
                $class, array_merge(array('key' => $newKey), $changes),
                array('key' => $key)
            );
            if ($updated !== 1) {
                throw new \ModxMCPClientException(
                    'Failed to update system setting: rename affected no single row.'
                );
            }
            $setting = $modx->getObject($class, array('key' => $newKey));
            if (!$setting) {
                throw new \ModxMCPClientException(
                    'MUTATION_OUTCOME_UNCERTAIN: setting rename readback failed.'
                );
            }
        } else {
            foreach ($changes as $field => $value) {
                $setting->set($field, $value);
            }
            if (!$setting->save()) {
                throw new \ModxMCPClientException('Failed to update system setting.');
            }
        }

        ElementMutationSupport::refreshCache($context);
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
        // RU: Старый id-селектор оставлен для обратной совместимости
        // RU: и защиты legacy-путей; у штатного modSystemSetting PK — key.
        // EN: Retain the legacy id selector for backward-compatible guards;
        // EN: native modSystemSetting has a string key primary key, not id.
        if (!empty($data['id'])) {
            return $context->modx()->getObject(
                $class, array('id' => (int)$data['id'])
            );
        }
        return null;
    }
}
