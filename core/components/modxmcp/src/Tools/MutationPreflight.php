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
    public static function assertAllowed($context, $action, array $data, $preconditions = null)
    {
        if (in_array($action, array('create_property_set', 'update_property_set'), true)) {
            PropertySetValidation::validateData($data);
        }
        // RU: Политики/шаблоны меняем только после снимка цели из MODX.
        // RU: Даже без protected mode: fail closed до CAS-резерва и процессора.
        // EN: Mandatory policy/template target snapshot in all security modes.
        if (in_array($action, array(
            'update_access_policy', 'delete_access_policy',
            'update_access_policy_template', 'delete_access_policy_template'
        ), true)) {
            StateSupport::requireProtectedTargetReadSet(
                $action, $data,
                is_array($preconditions) ? $preconditions : array()
            );
        }
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
            ProcessorMutationTool::normalizeAnonymousContextAccess($data);
        }
        if ($action === 'run_processor') {
            RunProcessorTool::assertDispatchAllowed($context, $data);
        }
        // RU: Пакетные процессоры могут менять файлы/БД. Неверный тип
        // RU: параметров — отказ до CAS, подписанного nonce и скачивания.
        // EN: Reject malformed package selectors before CAS/nonce/provider I/O.
        if ($action === 'install_package') {
            PackageInstallTool::validateInput($data);
        } elseif ($action === 'uninstall_package') {
            PackageUninstallTool::validateInput($data);
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
        // RU: Даже прямой HTTP-клиент не сможет обойти защиту перезаписи.
        // EN: Reject unversioned media overwrites before site CAS reservation.
        if ($action === 'update_media_file') {
            $digest = isset($data['expected_sha256'])
                && is_string($data['expected_sha256'])
                ? $data['expected_sha256'] : '';
            if (!preg_match('/^[a-fA-F0-9]{64}$/D', $digest)) {
                throw new \ModxMCPClientException(
                    'MANUAL_REVIEW_REQUIRED: read and compare the current media file'
                    . ' before supplying expected_sha256.'
                );
            }
            // Use MODX Media Source API only. A second check inside the tool
            // closes most ordinary delays between preflight and publication.
            $source = MediaSourceMutationSupport::resolveForFileOperation(
                $context, $data
            );
            $path = MediaSourceMutationSupport::relativePath(
                isset($data['path']) ? $data['path'] : ''
            );
            MediaFileUpdateTool::assertFresh($source, $path, $data);
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
