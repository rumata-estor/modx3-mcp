<?php
namespace ModxMcp\Tools;

class ResourceTvListTool implements ToolInterface
{
    public function name() { return 'get_resource_tvs'; }
    public function group() { return 'resource_tvs'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        $resourceId = !empty($data['resource_id']) ? (int) $data['resource_id'] : 0;
        if ($resourceId <= 0) {
            throw new \ModxMCPClientException('resource_id is required.');
        }

        $modx = $context->modx();
        $platform = $context->platform();
        $resource = $modx->getObject($platform->className('resource'), $resourceId);
        if (!$resource) {
            throw new \ModxMCPClientException("Resource not found: {$resourceId}.");
        }

        $templateId = (int) $resource->get('template');
        $result = array();

        if (method_exists($resource, 'getTemplateVars')) {
            $tvCollection = $resource->getTemplateVars();
            if (is_array($tvCollection) || $tvCollection instanceof \Traversable) {
                foreach ($tvCollection as $tv) {
                    $result[] = array(
                        'id' => (int) $tv->get('id'),
                        'name' => (string) $tv->get('name'),
                        'caption' => (string) $tv->get('caption'),
                        'type' => (string) $tv->get('type'),
                        'value' => (string) $tv->get('value'),
                    );
                }
            }
        }

        if (empty($result) && $templateId > 0) {
            $tvLinks = $modx->getCollection(
                $platform->className('template_var_template'),
                array('templateid' => $templateId)
            );
            if ($tvLinks) {
                foreach ($tvLinks as $link) {
                    $tv = $modx->getObject($platform->className('tv'), $link->get('tmplvarid'));
                    if (!$tv) { continue; }
                    $result[] = array(
                        'id' => (int) $tv->get('id'),
                        'name' => (string) $tv->get('name'),
                        'caption' => (string) $tv->get('caption'),
                        'type' => (string) $tv->get('type'),
                        'value' => method_exists($tv, 'getValue')
                            ? (string) $tv->getValue($resourceId)
                            : (string) $resource->getTVValue($tv->get('name')),
                    );
                }
            }
        }

        return array(
            'resource_id' => $resourceId,
            'template_id' => $templateId,
            'tvs' => $result,
        );
    }
}
