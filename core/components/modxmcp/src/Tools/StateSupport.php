<?php
namespace ModxMcp\Tools;

class StateSupport
{
    const REVISION_KEY = 'modxmcp.site_revision';

    public static function siteState($context)
    {
        return array(
            'site_revision' => self::revision($context),
            'atomic_preconditions' => true,
            'precondition_version' => 1,
        );
    }

    public static function revision($context)
    {
        $modx = $context->modx();
        $class = $context->platform()->className('system_setting');
        $setting = $modx->getObject($class, array('key' => self::REVISION_KEY));
        if (!$setting) {
            return 0;
        }
        return max(0, (int)$setting->get('value'));
    }

    public static function incrementRevision($context)
    {
        $modx = $context->modx();
        $class = $context->platform()->className('system_setting');
        $setting = $modx->getObject($class, array('key' => self::REVISION_KEY));
        if (!$setting) {
            $setting = $modx->newObject($class);
            $setting->fromArray(
                array(
                    'key' => self::REVISION_KEY,
                    'value' => 0,
                    'xtype' => 'textfield',
                    'namespace' => 'modxmcp',
                    'area' => 'modxmcp:runtime',
                ),
                '',
                true,
                true
            );
        }
        $next = max(0, (int)$setting->get('value')) + 1;
        $setting->set('value', (string)$next);
        if (!$setting->save()) {
            throw new \RuntimeException('Failed to persist MODX MCP site revision.');
        }
        return $next;
    }

    public static function withMutationLock($context, $callback)
    {
        $modx = $context->modx();
        $cachePath = (string)$modx->getOption(
            'cache_path',
            null,
            rtrim((string)$modx->getOption('core_path'), '/\\')
                . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR
        );
        $directory = rtrim($cachePath, '/\\')
            . DIRECTORY_SEPARATOR . 'modxmcp' . DIRECTORY_SEPARATOR;
        if (!is_dir($directory) && !@mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new \RuntimeException('Cannot create MODX MCP mutation lock directory.');
        }
        $path = $directory . 'runtime-cas.lock';
        $handle = @fopen($path, 'c+');
        if (!$handle) {
            throw new \RuntimeException('Cannot open MODX MCP mutation lock.');
        }
        try {
            if (!flock($handle, LOCK_EX)) {
                throw new \RuntimeException('Cannot acquire MODX MCP mutation lock.');
            }
            return call_user_func($callback);
        } finally {
            @flock($handle, LOCK_UN);
            @fclose($handle);
        }
    }

    private static function isListArray(array $value)
    {
        if (empty($value)) {
            return true;
        }
        return array_keys($value) === range(0, count($value) - 1);
    }

    private static function canonicalize($value)
    {
        if (!is_array($value)) {
            return $value;
        }
        if (self::isListArray($value)) {
            $out = array();
            foreach ($value as $item) {
                $out[] = self::canonicalize($item);
            }
            return $out;
        }
        ksort($value, SORT_STRING);
        $out = array();
        foreach ($value as $key => $item) {
            $out[(string)$key] = self::canonicalize($item);
        }
        return $out;
    }

    public static function canonicalHash($value)
    {
        $json = json_encode(
            self::canonicalize($value),
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_PRESERVE_ZERO_FRACTION
        );
        if ($json === false) {
            throw new \RuntimeException('Cannot encode state fingerprint payload.');
        }
        return hash('sha256', $json);
    }

    private static function elementState($type, array $raw)
    {
        $state = array('exists' => true);
        if (array_key_exists('id', $raw) && $raw['id'] !== null) {
            $state['id'] = is_numeric($raw['id']) ? (int)$raw['id'] : $raw['id'];
        }

        if ($type === 'resource') {
            $state['name'] = (string)(
                isset($raw['pagetitle']) ? $raw['pagetitle'] : (
                    isset($raw['name']) ? $raw['name'] : ''
                )
            );
        } elseif ($type === 'template') {
            $state['name'] = (string)(
                isset($raw['templatename']) ? $raw['templatename'] : (
                    isset($raw['name']) ? $raw['name'] : ''
                )
            );
        } elseif ($type === 'category') {
            $state['name'] = (string)(
                isset($raw['category']) ? $raw['category'] : (
                    isset($raw['name']) ? $raw['name'] : ''
                )
            );
        } else {
            $state['name'] = isset($raw['name']) ? (string)$raw['name'] : '';
        }

        $safeFields = array(
            'chunk' => array(
                'description', 'category', 'static', 'static_file',
                'source', 'property_preprocess',
            ),
            'snippet' => array(
                'description', 'category', 'static', 'static_file',
                'source', 'property_preprocess',
            ),
            'template' => array(
                'description', 'category', 'static', 'static_file', 'source',
            ),
            'resource' => array(
                'longtitle', 'description', 'alias', 'parent', 'template',
                'published', 'context_key', 'class_key', 'isfolder',
                'hidemenu', 'introtext', 'menutitle', 'menuindex',
            ),
            'tv' => array(
                'caption', 'description', 'category', 'type', 'elements',
                'display', 'default_text', 'rank', 'field_type', 'templates',
                'media_source', 'input_properties',
            ),
            'category' => array('parent', 'rank'),
            'plugin' => array(
                'description', 'category', 'disabled', 'static',
                'static_file', 'source', 'property_preprocess', 'events',
            ),
        );
        if (isset($safeFields[$type])) {
            foreach ($safeFields[$type] as $field) {
                if (array_key_exists($field, $raw)) {
                    $state[$field] = $raw[$field];
                }
            }
        }

        foreach (array('content', 'snippet', 'plugincode') as $field) {
            if (array_key_exists($field, $raw)) {
                $state['content_sha256'] = hash(
                    'sha256',
                    (string)$raw[$field]
                );
                break;
            }
        }
        return $state;
    }

    private static function systemSettingState($context, array $item)
    {
        $key = isset($item['key']) ? trim((string)$item['key']) : '';
        if ($key === '' && isset($item['name'])) {
            $key = trim((string)$item['name']);
        }
        if ($key === '') {
            throw new \ModxMCPClientException(
                'STALE_STATE system_setting precondition requires key.'
            );
        }
        $class = $context->platform()->className('system_setting');
        $setting = $context->modx()->getObject($class, array('key' => $key));
        if (!$setting) {
            return array('exists' => false, 'key' => $key);
        }
        $raw = $setting->toArray();
        $state = array(
            'exists' => true,
            'id' => isset($raw['id']) ? (int)$raw['id'] : null,
            'name' => $key,
        );
        foreach (array('key', 'namespace', 'area', 'xtype') as $field) {
            if (array_key_exists($field, $raw)) {
                $state[$field] = $raw[$field];
            }
        }
        if (array_key_exists('value', $raw)) {
            $state['value_sha256'] = hash(
                'sha256',
                (string)$raw['value']
            );
        }
        return $state;
    }

    public static function fingerprintReadItem($context, array $item)
    {
        $type = strtolower(trim((string)(
            isset($item['type']) ? $item['type'] : (
                isset($item['kind']) ? $item['kind'] : ''
            )
        )));
        if ($type === 'system_setting') {
            $state = self::systemSettingState($context, $item);
        } elseif (in_array(
            $type,
            array('resource', 'template', 'chunk', 'snippet', 'plugin', 'tv', 'category'),
            true
        )) {
            $args = array('type' => $type);
            if (isset($item['id']) && $item['id'] !== null) {
                $args['id'] = (int)$item['id'];
            } elseif (!empty($item['name'])) {
                $args['name'] = (string)$item['name'];
            } else {
                throw new \ModxMCPClientException(
                    'STALE_STATE element precondition requires id or name.'
                );
            }
            $raw = (new ElementGetTool())->execute($context, $args);
            if (!is_array($raw)) {
                throw new \ModxMCPClientException(
                    'STALE_STATE element precondition could not be read.'
                );
            }
            $state = self::elementState($type, $raw);
        } else {
            throw new \ModxMCPClientException(
                'STALE_STATE unsupported read-set type: ' . $type
            );
        }

        $fields = isset($item['fields']) && is_array($item['fields'])
            ? $item['fields']
            : array();
        if (!empty($fields)) {
            $selected = array();
            foreach ($fields as $field) {
                $field = (string)$field;
                $selected[$field] = array_key_exists($field, $state)
                    ? $state[$field]
                    : null;
            }
            $state = $selected;
        }
        return self::canonicalHash($state);
    }

    private static function identity(array $item)
    {
        $out = array();
        foreach (array('role', 'type', 'id', 'name', 'key', 'path') as $key) {
            if (isset($item[$key]) && $item[$key] !== '') {
                $out[$key] = $item[$key];
            }
        }
        return $out;
    }

    public static function assertPreconditions($context, array $preconditions)
    {
        $expectedRevision = isset($preconditions['observed_site_revision'])
            ? $preconditions['observed_site_revision']
            : null;
        $actualRevision = self::revision($context);
        $revisionChanged = (
            $expectedRevision !== null
            && (string)$expectedRevision !== (string)$actualRevision
        );

        $changedTargets = array();
        $changedDependencies = array();
        $readSet = isset($preconditions['read_set'])
            && is_array($preconditions['read_set'])
            ? $preconditions['read_set']
            : array();

        foreach ($readSet as $item) {
            if (!is_array($item)) {
                continue;
            }
            $expected = strtolower(trim((string)(
                isset($item['fingerprint']) ? $item['fingerprint'] : ''
            )));
            try {
                $actual = self::fingerprintReadItem($context, $item);
                $matches = ($expected !== '' && hash_equals($expected, $actual));
            } catch (\Throwable $e) {
                $matches = false;
            }
            if ($matches) {
                continue;
            }
            $identity = self::identity($item);
            if (isset($item['role']) && $item['role'] === 'target') {
                $changedTargets[] = $identity;
            } else {
                $changedDependencies[] = $identity;
            }
        }

        if (
            isset($preconditions['target_absent'])
            && is_array($preconditions['target_absent'])
        ) {
            $target = $preconditions['target_absent'];
            $targetType = strtolower(trim((string)(
                isset($target['type']) ? $target['type'] : ''
            )));
            if ($targetType !== '') {
                $selector = array('type' => $targetType);
                if (
                    isset($target['id'])
                    && $target['id'] !== null
                    && $target['id'] !== ''
                ) {
                    $selector['id'] = (int)$target['id'];
                } elseif (!empty($target['name'])) {
                    $selector['name'] = (string)$target['name'];
                }
                try {
                    $existingId = ElementSupport::resolveId(
                        $context,
                        $targetType,
                        $selector
                    );
                } catch (\Throwable $e) {
                    $existingId = 0;
                    $changedTargets[] = array(
                        'role' => 'target',
                        'type' => $targetType,
                        'name' => isset($target['name'])
                            ? (string)$target['name']
                            : null,
                        'reason' => 'target_absence_unverifiable',
                    );
                }
                if ($existingId > 0) {
                    $changedTargets[] = array(
                        'role' => 'target',
                        'type' => $targetType,
                        'id' => (int)$existingId,
                        'name' => isset($target['name'])
                            ? (string)$target['name']
                            : null,
                        'reason' => 'target_now_exists',
                    );
                }
            }
        }

        $strictRevision = !empty($preconditions['strict_site_revision']);
        if (
            !empty($changedTargets)
            || !empty($changedDependencies)
            || ($strictRevision && $revisionChanged)
        ) {
            $reason = !empty($changedTargets)
                ? 'target_changed'
                : (!empty($changedDependencies)
                    ? 'dependency_changed'
                    : 'site_revision_changed');
            $diagnostic = array(
                'status' => 'STALE_STATE',
                'reason' => $reason,
                'expected_site_revision' => $expectedRevision,
                'actual_site_revision' => $actualRevision,
                'site_revision_changed' => $revisionChanged,
                'changed_targets' => $changedTargets,
                'changed_dependencies' => $changedDependencies,
            );
            throw new \ModxMCPClientException(
                'STALE_STATE ' . json_encode(
                    $diagnostic,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                )
            );
        }

        return array(
            'expected_site_revision' => $expectedRevision,
            'actual_site_revision' => $actualRevision,
            'site_revision_changed' => $revisionChanged,
            'read_set_count' => count($readSet),
        );
    }
}
