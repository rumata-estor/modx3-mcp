<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Вспомогательная логика «Mini Shop2» для инструментов MODX MCP.
 * EN: Shared «Mini Shop2» implementation used by MODX MCP tools.
 */
namespace ModxMcp\Tools;

class MiniShop2Support
{
    public static function corePath($context)
    {
        $modx = $context->modx();
        return $modx->getOption('minishop2.core_path', null, $modx->getOption('core_path') . 'components/minishop2/');
    }

    public static function load($context)
    {
        $modx = $context->modx();
        $corePath = self::corePath($context);
        $service = $modx->getService('miniShop2', 'miniShop2', $corePath . 'model/minishop2/');
        if (!$service) {
            throw new \ModxMCPClientException('Could not load miniShop2 service. Is miniShop2 installed on this MODX site?');
        }
        $service->initialize($modx->context ? $modx->context->get('key') : 'mgr');
        return $service;
    }

    public static function runProcessor($context, $action, array $payload)
    {
        self::load($context);
        $response = $context->modx()->runProcessor(
            $action,
            $payload,
            array('processors_path' => self::corePath($context) . 'processors/')
        );
        if (!$response || $response->isError()) {
            throw new \ModxMCPClientException(
                'miniShop2 processor failed: '
                . ($response ? ProcessorSupport::error($response) : 'No processor response.')
            );
        }
        return ProcessorSupport::normalize($response);
    }

    public static function cleanPayload(array $data)
    {
        unset($data['action'], $data['elementType']);
        return $data;
    }

    public static function positiveInt(array $data, $key)
    {
        $value = !empty($data[$key]) ? (int)$data[$key] : 0;
        if ($value <= 0) {
            throw new \ModxMCPClientException($key . ' is required and must be a positive integer.');
        }
        return $value;
    }

    /**
     * RU: miniShop2 productlink/create interpolates these values into SQL.
     * RU: Accept only canonical positive decimal IDs before its processor runs.
     * EN: Validate every identifier before upstream string-interpolated SQL.
     */
    public static function productLinkPayload(array $data)
    {
        $payload = array();
        foreach (array('link', 'master', 'slave') as $field) {
            if (!array_key_exists($field, $data)
                || (!is_int($data[$field]) && !is_string($data[$field]))) {
                throw new \\ModxMCPClientException(
                    $field . ' is required and must be a positive integer.'
                );
            }
            $raw = (string)$data[$field];
            // RU: Reject casts of 1.2, 1e0, trailing text and overflow.
            // EN: Reject partial numeric casts, exponent notation and overflow.
            $number = (int)$raw;
            if (!preg_match('/\\A[1-9][0-9]*\\z/D', $raw)
                || $number <= 0
                || (string)$number !== $raw) {
                throw new \\ModxMCPClientException(
                    $field . ' is required and must be a positive integer.'
                );
            }
            $payload[$field] = $number;
        }
        // RU: No incidental or underscore-prefixed fields reach the addon.
        // EN: All other untrusted request properties are deliberately dropped.
        return $payload;
    }

    public static function assertProduct($context, $productId)
    {
        $resourceClass = $context->platform()->className('resource');
        $product = $context->modx()->getObject($resourceClass, $productId);
        if (!$product) {
            throw new \ModxMCPClientException('Product resource not found: ' . $productId . '.');
        }
        if ($product->get('class_key') !== 'msProduct') {
            throw new \ModxMCPClientException('Resource ' . $productId . ' is not an msProduct.');
        }
    }
}
