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
        if ($action === 'run_processor') {
            RunProcessorTool::assertDispatchAllowed($context, $data);
        }
    }
}
