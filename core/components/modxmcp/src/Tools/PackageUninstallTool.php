<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Инструмент MCP «Package Uninstall»: выполняется через защищённый диспетчер.
 * EN: MCP tool «Package Uninstall»: invoked through the guarded dispatcher.
 */
namespace ModxMcp\Tools;

class PackageUninstallTool implements ToolInterface
{
    public function name() { return 'uninstall_package'; }
    public function group() { return 'package_management'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        $signature = isset($data['signature'])
            ? (string)$data['signature']
            : '';
        if ($signature === '') {
            throw new \ModxMCPClientException(
                'uninstall_package: "signature" is required '
                . '(e.g. migx-2.13.0-pl).'
            );
        }

        $response = $context->platform()->runProcessor(
            $context->modx(),
            'workspace/packages/uninstall',
            array('signature' => $signature)
        );
        if (!$response || $response->isError()) {
            // RU: Процессор удаления способен удалить файлы и данные до
            // RU: возврата ошибки. Результат неопределён — повтор опасен.
            // EN: Package uninstall may remove files/data before reporting
            // EN: an error. Do not echo processor text or allow blind retry.
            throw new \ModxMCPClientException(
                'MUTATION_OUTCOME_UNCERTAIN: package uninstall did not confirm '
                . 'completion; verify installed package state via MCP before retry.'
            );
        }

        $manager = $context->modx()->getCacheManager();
        if ($manager) { $manager->refresh(); }
        AuditSupport::log(
            $context,
            $this->name(),
            'system',
            array('signature' => $signature)
        );
        return ProcessorSupport::normalize($response);
    }
}
