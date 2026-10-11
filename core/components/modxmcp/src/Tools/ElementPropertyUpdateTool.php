<?php
namespace ModxMcp\Tools;

/**
 * Обновление только значения существующего свойства элемента.
 * Сохраняет определения остальных свойств и код элемента;
 * ожидаемое старое значение защищает от перезаписи чужого изменения.
 */
class ElementPropertyUpdateTool implements ToolInterface
{
    public function name() { return 'update_element_property'; }
    public function group() { return 'elements'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        $type = ElementSupport::type($data);
        if (!in_array($type, array('snippet', 'chunk', 'plugin', 'template'), true)) {
            throw new \ModxMCPClientException('Element properties support only snippet, chunk, plugin and template.');
        }
        $name = isset($data['property_name']) ? (string)$data['property_name'] : '';
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_.-]{0,127}$/D', $name)) {
            throw new \ModxMCPClientException('Invalid property_name.');
        }
        if (!array_key_exists('value', $data) || !array_key_exists('expected_value', $data)) {
            throw new \ModxMCPClientException('value and expected_value are required.');
        }
        $next = $data['value'];
        $expected = $data['expected_value'];
        if ((!is_scalar($next) && $next !== null) || (!is_scalar($expected) && $expected !== null)) {
            throw new \ModxMCPClientException('Only scalar property values are supported.');
        }
        if (strlen((string)$next) > 65536) {
            throw new \ModxMCPClientException('Property value exceeds 65536 bytes.');
        }

        $id = ElementSupport::resolveId($context, $type, $data);
        if ($id <= 0) {
            throw new \ModxMCPClientException('Element not found by name or ID.');
        }
        $preconditions = isset($data['_runtime_preconditions']) && is_array($data['_runtime_preconditions'])
            ? $data['_runtime_preconditions'] : null;

        ElementMutationSupport::loadLexicons($context);
        return ElementMutationSupport::transaction($context, function () use (
            $context, $type, $id, $name, $next, $expected, $preconditions
        ) {
            if (is_array($preconditions)) {
                StateSupport::assertPreconditions($context, $preconditions);
            }
            // Читаем под общей блокировкой MCP: даже две независимые операции
            // над одним свойством не должны молча перезаписывать друг друга.
            $base = ElementSupport::processorBase($type);
            $current = $context->platform()->runProcessor(
                $context->modx(), $base . 'get', array('id' => $id)
            );
            if (!$current || $current->isError()) {
                throw new \ModxMCPClientException('Unable to read element before property update.');
            }
            $raw = $current->getObject();
            if (!is_array($raw)) {
                throw new \ModxMCPClientException('Unexpected element response.');
            }
            $properties = isset($raw['properties']) ? $raw['properties'] : array();
            if (is_string($properties)) {
                $properties = json_decode($properties, true);
            }
            if (!is_array($properties) || !isset($properties[$name])
                || !is_array($properties[$name])
                || !array_key_exists('value', $properties[$name])
            ) {
                throw new \ModxMCPClientException('Existing element property not found: ' . $name);
            }
            $old = $properties[$name]['value'];
            if ($old !== $expected && (string)$old !== (string)$expected) {
                throw new \ModxMCPClientException('STALE_STATE: property value changed since read.');
            }
            if ($old === $next || (string)$old === (string)$next) {
                return array('id' => $id, 'type' => $type, 'property_name' => $name, 'changed' => false);
            }

            $properties[$name]['value'] = $next;
            // Используем штатный MODX processor: события сохранения и ACL
            // остаются действующими. Не изменяем PHP-код и другие свойства.
            $update = ElementMutationSupport::filterData($type, $raw);
            $update['id'] = $id;
            $update['properties'] = $properties;
            $response = $context->platform()->runProcessor(
                $context->modx(), $base . 'update', $update
            );
            if (!$response || $response->isError()) {
                throw new \ModxMCPClientException('Property update failed: '
                    . ($response ? ProcessorSupport::error($response) : 'no response'));
            }
            $verify = $context->platform()->runProcessor(
                $context->modx(), $base . 'get', array('id' => $id)
            );
            if (!$verify || $verify->isError()) {
                throw new \ModxMCPClientException('Property verification failed.');
            }
            $check = $verify->getObject();
            $actual = isset($check['properties']) ? $check['properties'] : array();
            if (is_string($actual)) { $actual = json_decode($actual, true); }
            if (!is_array($actual) || !isset($actual[$name])
                || !array_key_exists('value', $actual[$name])
                || (string)$actual[$name]['value'] !== (string)$next
            ) {
                throw new \ModxMCPClientException('Property verification mismatch; transaction rolled back.');
            }
            ElementMutationSupport::refreshCache($context);
            AuditSupport::log($context, 'update_element_property', $type,
                array('id' => $id, 'property_name' => $name));
            return array('id' => $id, 'type' => $type, 'property_name' => $name, 'changed' => true);
        });
    }
}
