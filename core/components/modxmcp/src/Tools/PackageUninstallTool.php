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

        try {
            $response = $context->platform()->runProcessor(
                $context->modx(),
                'workspace/packages/uninstall',
                array('signature' => $signature)
            );
        } catch (\Throwable $e) {
            // RU: Удаление могло частично изменить файлы и БД.
            // EN: Uninstall may have modified files and DB before throwing.
            throw new \ModxMCPClientException(
                'MUTATION_OUTCOME_UNCERTAIN: package uninstall interrupted; '
                . 'inspect installed package state before retry.'
            );
        }
        try {
            $uninstallOk = $response && !$response->isError();
        } catch (\Throwable $e) {
            throw new \ModxMCPClientException(
                'MUTATION_OUTCOME_UNCERTAIN: package removal response unreadable; '
                . 'verify installed package state via MCP before retry.'
            );
        }
        if (!$uninstallOk) {
            throw new \ModxMCPClientException(
                'MUTATION_OUTCOME_UNCERTAIN: package uninstall did not confirm '
                . 'completion; verify installed package state via MCP before retry.'
            );
        }
        try {
            $result = ProcessorSupport::normalize($response);
            $manager = $context->modx()->getCacheManager();
            if ($manager) { $manager->refresh(); }
            AuditSupport::log(
                $context,
                $this->name(),
                'system',
                array('signature' => $signature)
            );
            return $result;
        } catch (\Throwable $e) {
            throw new \ModxMCPClientException(
                'MUTATION_OUTCOME_UNCERTAIN: package removed but post-uninstall '
                . 'verification failed; inspect installed state before retry.'
            );
        }
    }
}
