<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Проверка и нормализация определений Property Set до записи.
 * EN: Validate and normalize property-set definitions before any write.
 */
namespace ModxMcp\Tools;

/**
 * Validation approach inspired by dampilov94/mcp-component Kind/Properties.php.
 * Preserve the MCP name-keyed map and accept native MODX readback metadata.
 */
final class PropertySetValidation
{
    private static function reject($reason)
    {
        throw new \ModxMCPClientException('VALIDATION_ERROR: property set properties: '.$reason);
    }

    private static function scalarValue($value)
    {
        return is_scalar($value) && (!is_float($value) || is_finite($value));
    }

    public static function normalize($properties)
    {
        if (!is_array($properties)) self::reject('expected an object of property definitions.');
        $out = array();
        foreach ($properties as $name => $definition) {
            if (!is_string($name) || trim($name) === '' || strpos($name, "\0") !== false) {
                self::reject('expected non-empty named keys, not a list.');
            }
            if (!is_array($definition) || !array_key_exists('value', $definition)
                || !self::scalarValue($definition['value'])) {
                self::reject('each definition requires a scalar value (use an empty string to clear it).');
            }
            $allowed = array('name','type','value','desc','options','lexicon','area','desc_trans');
            if (array_diff(array_keys($definition), $allowed)) self::reject('unknown definition field.');
            if (array_key_exists('name', $definition) && $definition['name'] !== $name) {
                self::reject('definition name must match its map key.');
            }
            $type = array_key_exists('type', $definition) ? $definition['type'] : 'textfield';
            if (!is_string($type) || trim($type) === '' || strpos($type, "\0") !== false) {
                self::reject('type must be a non-empty string.');
            }
            foreach (array('desc','area','desc_trans') as $field) {
                if (array_key_exists($field, $definition) && !is_string($definition[$field])) {
                    self::reject($field.' must be a string.');
                }
            }
            if (array_key_exists('lexicon', $definition) && $definition['lexicon'] !== null
                && !is_string($definition['lexicon'])) self::reject('lexicon must be a string or null.');
            $options = array();
            if (array_key_exists('options', $definition)) {
                if (!is_array($definition['options'])) self::reject('options must be a list.');
                foreach ($definition['options'] as $index => $option) {
                    if ($index !== count($options) || !is_array($option)
                        || array_diff(array_keys($option), array('text','value','name'))
                        || !array_key_exists('value', $option) || !self::scalarValue($option['value'])) {
                        self::reject('options must contain objects with scalar values.');
                    }
                    foreach (array('text','name') as $field) {
                        if (array_key_exists($field, $option) && !is_string($option[$field])) {
                            self::reject('option labels must be strings.');
                        }
                    }
                    $text = isset($option['text']) ? $option['text']
                        : (isset($option['name']) ? $option['name'] : (string)$option['value']);
                    $options[] = array('text'=>$text, 'value'=>$option['value']);
                }
            }
            $value = $definition['value'];
            if ($type === 'combo-boolean' && is_numeric($value)) $value = (bool)$value;
            $out[$name] = array(
                'name'=>$name, 'desc'=>isset($definition['desc']) ? $definition['desc'] : '',
                'type'=>$type, 'options'=>$options, 'value'=>$value,
                'lexicon'=>isset($definition['lexicon']) && $definition['lexicon'] !== '' ? $definition['lexicon'] : null,
                'area'=>isset($definition['area']) ? $definition['area'] : '',
            );
        }
        return $out;
    }

    public static function validateData(array $data)
    {
        if (array_key_exists('properties', $data)) {
            $data['properties'] = self::normalize($data['properties']);
        }
        return $data;
    }
}
