<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Детерминированный отказ до внешнего эффекта и CAS-ревизии.
 * EN: Deterministic authorization denials before effects and CAS reservation.
 */
namespace ModxMcp\Tools;

final class MutationPreflight
{
    /**
     * RU: Дублирующие проверки в процессорах остаются защитой при прямом
     * RU: вызове инструмента. Правила делегируются существующим guard-классам.
     * EN: Processor-level guards remain defense in depth; shared guard
     * EN: classes are authoritative for each security policy.
     */
    public static function assertAllowed($context, $action, array $data)
    {
        // Reject forbidden hidden ACL/credential fields before CAS revision
        // reservation, while retaining identical processor-level checks.
        ProcessorMutationTool::assertAclMutationFields($action, $data);
        if (in_array($action, array(
            'create_system_setting','update_system_setting',
            'delete_system_setting'
        ), true)) {
            if ($action === 'create_system_setting' && empty($data['key'])) {
                throw new \ModxMCPClientException('System setting key is required.');
            }
            foreach (array('key','new_key') as $field) {
                if (array_key_exists($field, $data)) {
                    \ModxMcp\Security\ProtectedSettingsGuard::assertMutableKey(
                        (string)$data[$field]
                    );
                }
            }
        }
        if (in_array($action, array(
            'create_context_setting', 'update_context_setting',
            'delete_context_setting'
        ), true)) {
            foreach (array('key', 'name') as $field) {
                if (isset($data[$field])) {
                    \ModxMcp\Security\ProtectedSettingsGuard::assertMutableKey(
                        (string)$data[$field]
                    );
                }
            }
        }
        if (in_array($action, array('create_context','update_context'), true)) {
            \ModxMcp\Security\ProtectedSettingsGuard::assertNoOpaqueContextSettings(
                $data
            );
        }
        if ($action === 'create_context') {
            foreach (array_keys($data) as $field) {
                if (!is_string($field)
                    || strtolower(trim($field)) !== 'enableanonymous') {
                    continue;
                }
                if ($field !== 'enableAnonymous'
                    || !in_array($data[$field], array(false, 0, '0'), true)) {
                    throw new \ModxMCPClientException(
                        'AUTH_DENIED: anonymous context access requires explicit ACL grant.'
                    );
                }
            }
        }
        if ($action === 'run_processor') {
            RunProcessorTool::assertDispatchAllowed($context, $data);
        }
        if ($action === 'reorder_resources') {
            ResourceReorderTool::validateBatch($context, $data);
        }
        if ($action === 'bulk_resources') {
            BulkResourcesTool::validateBatch($context, $data);
        }
        if ($action === 'replace_across') {
            ReplaceAcrossTool::validateSelection($context, $data);
        }
        // Paths/names are deterministic and must be rejected BEFORE
        // reserving a CAS revision or an operation ID. The media driver
        // revalidates these inputs before its actual filesystem effect.
        if ($action === 'create_media_folder') {
            if (array_key_exists('path', $data)) {
                throw new \ModxMCPClientException(
                    'create_media_folder: unsupported path field; '
                    . 'use parent for the destination directory and name for the new folder.'
                );
            }
            MediaSourceMutationSupport::relativePath(
                isset($data['parent']) ? $data['parent'] : '/', true
            );
            MediaSourceMutationSupport::leafName(
                isset($data['name']) ? $data['name'] : ''
            );
        } elseif ($action === 'create_media_file') {
            MediaSourceMutationSupport::relativePath(
                isset($data['path']) ? $data['path'] : '', true
            );
            MediaSourceMutationSupport::leafName(
                isset($data['name']) ? $data['name'] : ''
            );
        } elseif (in_array($action, array(
            'update_media_file', 'delete_media_file',
            'rename_media_file', 'delete_media_folder'
        ), true)) {
            MediaSourceMutationSupport::relativePath(
                isset($data['path']) ? $data['path'] : ''
            );
            if ($action === 'rename_media_file') {
                MediaSourceMutationSupport::leafName(
                    isset($data['new_name']) ? $data['new_name'] : ''
                );
            }
        }
    }
}
