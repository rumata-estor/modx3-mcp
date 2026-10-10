<?php
namespace ModxMcp\Tools;

class ResourceTvUpdateTool implements ToolInterface
{
    public function name() { return 'update_resource_tvs'; }
    public function group() { return 'resource_tvs'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        $resourceId = !empty($data['resource_id'])
            ? (int)$data['resource_id']
            : 0;
        if ($resourceId <= 0) {
            throw new \ModxMCPClientException('resource_id is required.');
        }
        if (empty($data['tvs']) || !is_array($data['tvs'])) {
            throw new \ModxMCPClientException(
                'tvs payload must be a non-empty object/array.'
            );
        }

        $resource = $context->modx()->getObject(
            $context->platform()->className('resource'),
            $resourceId
        );
        if (!$resource) {
            throw new \ModxMCPClientException(
                'Resource not found: ' . $resourceId . '.'
            );
        }

        // The dispatcher's site-wide mutation lock and CAS guard execute
        // before this tool is called. Make multi-TV changes all-or-nothing,
        // and reject unknown/unassigned TV names before any write.
        $modx = $context->modx();
        $templateId = (int)$resource->get('template');
        foreach ($data['tvs'] as $name => $value) {
            if (!is_string($name) || $name === '' || !is_string($value)) {
                throw new \ModxMCPClientException(
                    'TV updates require non-empty TV names and string values.'
                );
            }
            $tv = $modx->getObject(
                $context->platform()->className('tv'),
                array('name' => $name)
            );
            if (!$tv) {
                throw new \ModxMCPClientException('Unknown TV: ' . $name);
            }
            $link = $modx->getObject(
                $context->platform()->className('template_var_template'),
                array('tmplvarid' => $tv->get('id'), 'templateid' => $templateId)
            );
            if (!$link) {
                throw new \ModxMCPClientException(
                    'TV is not assigned to the resource template: ' . $name
                );
            }
        }
        // Execute the field-level compare under the same connector-wide
        // mutation lock as the site revision check. This catches an edit
        // that bypassed revision tracking before we reached the lock.
        $guard = isset($data['_runtime_preconditions'])
            && is_array($data['_runtime_preconditions'])
            ? $data['_runtime_preconditions'] : null;
        if ($guard !== null) {
            $expected = isset($guard['expected_tvs'])
                && is_array($guard['expected_tvs'])
                ? $guard['expected_tvs'] : null;
            if ($expected === null
                || count($expected) !== count($data['tvs'])
                || array_diff_key($expected, $data['tvs'])
                || array_diff_key($data['tvs'], $expected)
            ) {
                throw new \ModxMCPClientException(
                    'Resource TV CAS requires matching expected_tvs names.'
                );
            }
            foreach ($expected as $name => $beforeValue) {
                if (!is_string($beforeValue)
                    || (string)$resource->getTVValue($name) !== $beforeValue
                ) {
                    throw new \ModxMCPClientException(
                        'STALE_STATE {"status":"STALE_STATE","reason":"tv_values_changed"}'
                    );
                }
            }
        }

        $modx->beginTransaction();
        try {
            foreach ($data['tvs'] as $name => $value) {
                if ($resource->setTVValue($name, $value) === false) {
                    throw new \ModxMCPClientException('Failed to write TV: ' . $name);
                }
            }
            $modx->commit();
        } catch (\Throwable $e) {
            $modx->rollback();
            throw $e;
        }

        $context->modx()->cacheManager->refresh();
        AuditSupport::log(
            $context,
            $this->name(),
            'resource_tv',
            array(
                'resource_id' => $resourceId,
                'tv_keys' => array_keys($data['tvs']),
            )
        );
        return (new ResourceTvListTool())->execute(
            $context,
            array('resource_id' => $resourceId)
        );
    }
}
