<?php
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
                . ($listResponse
                    ? ProcessorSupport::error($listResponse)
                    : 'no response')
            );
        }
        $listData = json_decode($listResponse->getResponse(), true);
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
                    isset($row['signature'])
                    && strcasecmp((string)$row['signature'], $targetSignature) === 0
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
                if (isset($row['name']) && strcasecmp($row['name'], $name) === 0) {
                    $chosen = $row;
                    break;
                }
            }
            if (!$chosen) { $chosen = $rows[0]; }
        }
        if (empty($chosen['location']) || empty($chosen['signature'])) {
            throw new \ModxMCPClientException(
                'install_package: provider result is missing location/signature.'
            );
        }

        $downloadResponse = $context->platform()->runProcessor(
            $context->modx(),
            'workspace/packages/rest/download',
            array(
                'info' => $chosen['location'] . '::' . $chosen['signature'],
                'provider' => $providerId,
            )
        );
        if (!$downloadResponse || $downloadResponse->isError()) {
            throw new \ModxMCPClientException(
                'install_package: download failed: '
                . ($downloadResponse
                    ? ProcessorSupport::error($downloadResponse)
                    : 'no response')
            );
        }

        $downloadObject = $downloadResponse->getObject();
        $signature = is_array($downloadObject) && !empty($downloadObject['signature'])
            ? $downloadObject['signature']
            : $chosen['signature'];

        $installResponse = $context->platform()->runProcessor(
            $context->modx(),
            'workspace/packages/install',
            array('signature' => $signature)
        );
        if (!$installResponse || $installResponse->isError()) {
            throw new \ModxMCPClientException(
                'install_package: install failed: '
                . ($installResponse
                    ? ProcessorSupport::error($installResponse)
                    : 'no response')
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
