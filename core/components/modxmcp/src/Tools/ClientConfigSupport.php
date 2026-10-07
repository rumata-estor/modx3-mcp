<?php
namespace ModxMcp\Tools;

class ClientConfigSupport
{
    public static function modelPath($context)
    {
        $modx = $context->modx();
        $core = $modx->getOption(
            'clientconfig.core_path',
            null,
            rtrim((string)$modx->getOption('core_path'), '/\\')
                . DIRECTORY_SEPARATOR . 'components'
                . DIRECTORY_SEPARATOR . 'clientconfig'
                . DIRECTORY_SEPARATOR
        );
        $model = rtrim((string)$core, '/\\')
            . DIRECTORY_SEPARATOR . 'model' . DIRECTORY_SEPARATOR;
        if (!is_dir($model . 'clientconfig')) {
            throw new \ModxMCPClientException(
                'ClientConfig is not installed or its model path is unavailable.'
            );
        }
        $modx->addPackage('clientconfig', $model);
        return $model;
    }

    public static function setting($context, array $data)
    {
        self::modelPath($context);
        $criteria = array();
        if (!empty($data['id'])) {
            $criteria['id'] = (int)$data['id'];
        } elseif (!empty($data['key'])) {
            $criteria['key'] = (string)$data['key'];
        } else {
            throw new \ModxMCPClientException(
                'ClientConfig setting requires id or key.'
            );
        }
        return $context->modx()->getObject('cgSetting', $criteria);
    }

    public static function normalize($context, $setting)
    {
        $out = $setting->toArray();
        $out['context_values'] = array();
        $values = $setting->getMany('ContextValues');
        if (is_array($values)) {
            foreach ($values as $value) {
                $out['context_values'][(string)$value->get('context')]
                    = $value->get('value');
            }
        }
        return $out;
    }

    public static function applyFields($setting, array $data)
    {
        foreach (array(
            'key', 'label', 'xtype', 'description', 'is_required',
            'sortorder', 'value', 'default', 'group', 'options',
            'process_options', 'source'
        ) as $field) {
            if (array_key_exists($field, $data)) {
                $setting->set($field, $data[$field]);
            }
        }
    }

    public static function applyContextValues($context, $setting, array $data)
    {
        if (!array_key_exists('context_values', $data)) {
            return;
        }
        if (!is_array($data['context_values'])) {
            throw new \ModxMCPClientException(
                'context_values must be an object/map.'
            );
        }
        $modx = $context->modx();
        foreach ($data['context_values'] as $contextKey => $value) {
            $criteria = array(
                'setting' => (int)$setting->get('id'),
                'context' => (string)$contextKey,
            );
            $row = $modx->getObject('cgContextValue', $criteria);
            if ($value === null) {
                if ($row && !$row->remove()) {
                    throw new \ModxMCPClientException(
                        'Failed to remove ClientConfig context value.'
                    );
                }
                continue;
            }
            if (!$row) {
                $row = $modx->newObject('cgContextValue');
                $row->set('setting', (int)$setting->get('id'));
                $row->set('context', (string)$contextKey);
            }
            $row->set('value', (string)$value);
            if (!$row->save()) {
                throw new \ModxMCPClientException(
                    'Failed to save ClientConfig context value.'
                );
            }
        }
    }

    public static function refresh($context)
    {
        $manager = $context->modx()->getCacheManager();
        if ($manager) {
            $manager->refresh();
        }
    }
}
