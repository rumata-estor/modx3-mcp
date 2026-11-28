<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Инструмент MCP «Resource Tv List»: выполняется через защищённый диспетчер.
 * EN: MCP tool «Resource Tv List»: invoked through the guarded dispatcher.
 */
namespace ModxMcp\Tools;

class ResourceTvListTool implements ToolInterface
{
    public function name() { return 'get_resource_tvs'; }
    public function group() { return 'resource_tvs'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public static function requestsNativeRendering(array $data)
    {
        $mode = array_key_exists('value_mode', $data) ? $data['value_mode'] : 'raw';
        if (!is_string($mode) || !in_array($mode, array('raw','both','rendered'), true)) {
            throw new \ModxMCPClientException('VALIDATION_ERROR: value_mode must be raw, both or rendered.');
        }
        return $mode !== 'raw';
    }

    public static function assertNativeRenderingAllowed($context)
    {
        try {
            $allowed = \ModxMcp\Security\ProtectedAuthorization::nativeTvRenderingEnabled($context->modx());
        } catch (\Throwable $e) {
            $allowed = false;
        }
        if (!$allowed) {
            throw new \ModxMCPClientException(
                'TV_RENDERING_DISABLED: use value_mode=raw; native rendering requires administrator opt-in in ordinary mode.'
            );
        }
    }

    public function execute($context, array $data)
    {
        $resourceId = !empty($data['resource_id']) ? (int) $data['resource_id'] : 0;
        if ($resourceId <= 0) {
            throw new \ModxMCPClientException('resource_id is required.');
        }

        $render = self::requestsNativeRendering($data);
        $modx = $context->modx();
        $platform = $context->platform();
        if ($render) self::assertNativeRenderingAllowed($context);
        $resource = $modx->getObject($platform->className('resource'), $resourceId, false);
        if (!$resource) {
            throw new \ModxMCPClientException("Resource not found: {$resourceId}.");
        }

        $templateId = (int)$resource->get('template');
        $result = array();
        $entries = array();
        if (method_exists($resource, 'getTemplateVars')) {
            $collection = $resource->getTemplateVars();
            if (is_array($collection) || $collection instanceof \Traversable) {
                foreach ($collection as $tv) {
                    $entries[] = array($tv, (string)$tv->get('value'));
                }
            }
        }
        if (!$entries && $templateId > 0) {
            $links = $modx->getCollection($platform->className('template_var_template'),
                array('templateid'=>$templateId));
            foreach ($links ?: array() as $link) {
                $tv = $modx->getObject($platform->className('tv'), $link->get('tmplvarid'), false);
                if (!$tv) continue;
                // Never substitute modResource::getTVValue(), which renders.
                if (!method_exists($tv, 'getValue')) {
                    throw new \ModxMCPClientException('Raw TV read is unavailable.');
                }
                $entries[] = array($tv, (string)$tv->getValue($resourceId));
            }
        }
        foreach ($entries as $entry) {
            list($tv, $raw) = $entry;
            // Keep value raw in EVERY mode for existing CAS clients.
            $row = array('id'=>(int)$tv->get('id'), 'name'=>(string)$tv->get('name'),
                'caption'=>(string)$tv->get('caption'), 'type'=>(string)$tv->get('type'),
                'value'=>$raw, 'raw'=>$raw);
            if ($render) {
                $row['rendered'] = null;
                // MODX renderers may alter the current resource. Restore the
                // original request-local resource and identifier even on error.
                $previous = $modx->resource;
                $previousId = $modx->resourceIdentifier;
                $bufferLevel = ob_get_level();
                ob_start();
                try {
                    $modx->resource = $resource;
                    $modx->resourceIdentifier = $resourceId;
                    $rendered = $tv->renderOutput($resourceId);
                    if (is_string($rendered) || is_int($rendered) || is_float($rendered) || is_bool($rendered)) {
                        $row['rendered'] = (string)$rendered;
                        $row['render_status'] = 'rendered';
                    } else {
                        $row['render_status'] = 'unavailable';
                    }
                } catch (\Throwable $e) {
                    // Never expose native renderer exceptions or claim raw is rendered.
                    $row['render_status'] = 'failed';
                } finally {
                    while (ob_get_level() > $bufferLevel) {
                        if (!@ob_end_clean()) break;
                    }
                    $modx->resource = $previous;
                    $modx->resourceIdentifier = $previousId;
                }
            }
            $result[] = $row;
        }
        $response = array('resource_id'=>$resourceId, 'template_id'=>$templateId, 'tvs'=>$result);
        if ($render) $response['render_effects'] = 'not_verified';
        return $response;
    }
}
