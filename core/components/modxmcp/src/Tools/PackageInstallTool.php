<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Инструмент MCP «Package Install»: выполняется через защищённый диспетчер.
 * EN: MCP tool «Package Install»: invoked through the guarded dispatcher.
 */
namespace ModxMcp\Tools;

class PackageInstallTool implements ToolInterface
{
    public function name() { return 'install_package'; }
    public function group() { return 'package_management'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        $name = isset($data['package'])
            ? trim((string)$data['package'])
            : '';
        if ($name === '') {
            throw new \ModxMCPClientException(
                'install_package: "package" (name) is required.'
            );
        }

        $providerId = isset($data['provider'])
            ? (int)$data['provider']
            : PackageSupport::defaultProviderId($context);
        if (!$providerId) {
            throw new \ModxMCPClientException(
                'install_package: no transport provider is configured.'
            );
        }

        $targetSignature = isset($data['target_signature'])
            ? trim((string)$data['target_signature'])
            : '';

        $packageClass = $context->platform()->className('transport_package');
        $existing = $context->modx()->getObject(
            $packageClass,
            array('package_name' => $name, 'installed:!=' => null)
        );
        if ($existing && $targetSignature === '') {
            return array(
                'status' => 'already_installed',
                'package' => $name,
                'signature' => $existing->get('signature'),
            );
        }
        if (
            $existing
            && $targetSignature !== ''
            && strcasecmp((string)$existing->get('signature'), $targetSignature) === 0
        ) {
            return array(
                'status' => 'already_installed_target',
                'package' => $name,
                'signature' => $existing->get('signature'),
            );
        }
        $previousSignature = $existing
            ? (string)$existing->get('signature')
            : null;

        $listResponse = $context->platform()->runProcessor(
            $context->modx(),
            'workspace/packages/rest/getlist',
            array(
                'provider' => $providerId,
                'query' => $name,
                'limit' => 20,
            )
        );
        if (!$listResponse || $listResponse->isError()) {
            throw new \ModxMCPClientException(
                'install_package: provider search failed: '
                . 'provider did not return a usable response'
            );
        }
        // RU: Поиск пакетов должен одинаково работать на MODX 2 и MODX 3.
        // EN: Package lookup must accept both MODX 2 and MODX 3 responses.
        $listData = ProcessorSupport::decodedResponse($listResponse);
        $rows = isset($listData['results'])
            ? $listData['results']
            : array();
        if (empty($rows)) {
            throw new \ModxMCPClientException(
                "install_package: no package named '{$name}' found on the provider."
            );
        }

        $chosen = null;
        if ($targetSignature !== '') {
            foreach ($rows as $row) {
                if (
                    is_array($row)
                    && isset($row['name'],$row['signature'])
                    && is_string($row['name'])
                    && is_string($row['signature'])
                    && strcasecmp($row['name'], $name) === 0
                    && strcasecmp($row['signature'], $targetSignature) === 0
                ) {
                    $chosen = $row;
                    break;
                }
            }
            if (!$chosen) {
                throw new \ModxMCPClientException(
                    "install_package: target signature '{$targetSignature}' "
                    . 'was not returned by the provider search.'
                );
            }
        } else {
            foreach ($rows as $row) {
                if (is_array($row) && isset($row['name'])
                    && is_string($row['name'])
                    && strcasecmp($row['name'], $name) === 0) {
                    $chosen = $row;
                    break;
                }
            }
            if (!$chosen) {
                // RU: Частичное совпадение поиска не разрешает замену пакета.
                // EN: Never install the first fuzzy-search result as another package.
                throw new \ModxMCPClientException(
                    'install_package: provider returned no exact package-name match.'
                );
            }
        }
        if (!is_array($chosen)
            || !isset($chosen['name']) || !is_string($chosen['name'])
            || strcasecmp($chosen['name'],$name)!==0) {
            throw new \ModxMCPClientException(
                'install_package: provider result does not match requested package.'
            );
        }
        if (empty($chosen['location']) || empty($chosen['signature'])) {
            throw new \ModxMCPClientException(
                'install_package: provider result is missing location/signature.'
            );
        }

        try {
            $downloadResponse = $context->platform()->runProcessor(
                $context->modx(),
                'workspace/packages/rest/download',
                array(
                    'info' => $chosen['location'] . '::' . $chosen['signature'],
                    'provider' => $providerId,
                )
            );
        } catch (\Throwable $e) {
            // RU: Загрузка могла оставить неполный файл в transport-кэше.
            // EN: Download may leave partial transport bytes after throwing.
            throw new \ModxMCPClientException(
                'MUTATION_OUTCOME_UNCERTAIN: package download interrupted; '
                . 'inspect package state before retry.'
            );
        }
        if (!$downloadResponse || $downloadResponse->isError()) {
            // RU: Ошибка скачивания способна оставить неполный transport-файл.
            // EN: A failed download may leave partial transport files behind.
            throw new \ModxMCPClientException(
                'MUTATION_OUTCOME_UNCERTAIN: package download did not confirm '
                . 'completion; inspect package state before retry.'
            );
        }

        $downloadObject = $downloadResponse->getObject();
        $signature = is_array($downloadObject) && !empty($downloadObject['signature'])
            ? $downloadObject['signature']
            : $chosen['signature'];
        // RU: Загрузчик может сообщить другой артефакт; никогда не
        // RU: передавать его установщику под видом выбранной версии.
        // EN: Reject a downloaded signature differing from the exact provider
        // EN: selection before invoking the package install processor.
        if (!is_string($signature)
            || strcasecmp($signature, (string)$chosen['signature']) !== 0) {
            throw new \ModxMCPClientException(
                'MUTATION_OUTCOME_UNCERTAIN: downloaded package identity differs '
                . 'from selected package; inspect transport cache before retry.'
            );
        }

        try {
            $installResponse = $context->platform()->runProcessor(
                $context->modx(),
                'workspace/packages/install',
                array('signature' => $signature)
            );
        } catch (\Throwable $e) {
            // RU: Исключение при установке не отменяет записанные файлы.
            // EN: Thrown install errors do not roll back transport files.
            throw new \ModxMCPClientException(
                'MUTATION_OUTCOME_UNCERTAIN: package install interrupted; '
                . 'inspect installed package state before retry.'
            );
        }
        if (!$installResponse || $installResponse->isError()) {
            // RU: Во время установки транспортный пакет может изменить
            // RU: файлы и БД до ошибки. Не обещаем откат или безопасный повтор.
            // EN: Transport installation can affect files and database
            // EN: before an error; outcome must be reconciled before retry.
            throw new \ModxMCPClientException(
                'MUTATION_OUTCOME_UNCERTAIN: package install did not confirm '
                . 'completion; verify installed package state via MCP before retry.'
            );
        }

        $manager = $context->modx()->getCacheManager();
        if ($manager) { $manager->refresh(); }
        AuditSupport::log(
            $context,
            $this->name(),
            'system',
            array(
                'package' => $name,
                'signature' => $signature,
                'previous_signature' => $previousSignature,
            )
        );
        return array(
            'status' => $previousSignature !== null
                ? 'version_changed'
                : 'installed',
            'package' => isset($chosen['name']) ? $chosen['name'] : $name,
            'signature' => $signature,
            'previous_signature' => $previousSignature,
            'version' => isset($chosen['version']) ? $chosen['version'] : null,
        );
    }
}
