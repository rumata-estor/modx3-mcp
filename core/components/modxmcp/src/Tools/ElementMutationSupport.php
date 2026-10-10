<?php
namespace ModxMcp\Tools;

class ElementMutationSupport
{
    public static function prepare($context, $type, array $data, $action)
    {
        if (empty($data['id']) && !empty($data['name'])) {
            $resolved = ElementSupport::resolveId($context, $type, $data);
            if ($resolved > 0) { $data['id'] = $resolved; }
        }

        if (isset($data['type']) && $data['type'] === $type) {
            unset($data['type']);
        }
        if ($type === 'tv' && !empty($data['field_type'])) {
            $data['type'] = $data['field_type'];
        }

        if (in_array($action, array('create_element', 'update_element'), true)
            && isset($data['static_file'])
            && in_array($type, array('chunk', 'snippet', 'template', 'plugin'), true)) {
            $data['static_file'] = self::validateStaticFile($context, $data['static_file']);
        }

        if (isset($data['name'])) {
            if ($type === 'template' && !isset($data['templatename'])) {
                $data['templatename'] = $data['name'];
            }
            if ($type === 'resource' && !isset($data['pagetitle'])) {
                $data['pagetitle'] = $data['name'];
            }
            if ($type === 'category' && !isset($data['category'])) {
                $data['category'] = $data['name'];
            }
        }

        if ($type === 'resource' && $action === 'create_element') {
            if (!isset($data['context_key'])) { $data['context_key'] = 'web'; }
            if (!isset($data['parent'])) { $data['parent'] = 0; }
            if (!isset($data['published'])) { $data['published'] = 1; }
        }

        if (isset($data['content'])) {
            if ($type === 'chunk' || $type === 'snippet') {
                $data['snippet'] = $data['content'];
            }
            if ($type === 'plugin') {
                $data['plugincode'] = $data['content'];
            }
        }
        return $data;
    }

    public static function filterData($type, array $data)
    {
        $allowed = array(
            'chunk' => array('id', 'name', 'description', 'snippet', 'category', 'static', 'static_file', 'source', 'property_preprocess'),
            'snippet' => array('id', 'name', 'description', 'snippet', 'category', 'static', 'static_file', 'source', 'property_preprocess'),
            'template' => array('id', 'templatename', 'description', 'content', 'category', 'static', 'static_file', 'source'),
            'resource' => array('id', 'pagetitle', 'longtitle', 'description', 'alias', 'parent', 'template', 'content', 'published', 'context_key', 'class_key', 'isfolder', 'hidemenu', 'introtext', 'menutitle', 'menuindex', 'article', 'price', 'old_price', 'weight', 'remains', 'vendor', 'made_in', 'new', 'popular', 'favorite', 'tags', 'color', 'size'),
            'tv' => array('id', 'name', 'caption', 'description', 'category', 'type', 'elements', 'display', 'default_text', 'rank'),
            'category' => array('id', 'category', 'parent', 'rank'),
            'plugin' => array('id', 'name', 'description', 'plugincode', 'category', 'disabled', 'static', 'static_file', 'source', 'property_preprocess'),
        );
        if (!isset($allowed[$type])) { return $data; }
        return array_intersect_key($data, array_flip($allowed[$type]));
    }

    // Client-supplied static_file must resolve (the way MODX reads/writes static
    // element files) inside the static elements root: core_path + elements/.
    // Absolute paths are not accepted from API input.
    public static function validateStaticFile($context, $value)
    {
        $value = trim((string)$value);
        if ($value === '') { return ''; }
        if (FilesystemSupport::isAbsolutePath($value)) {
            throw new \ModxMCPClientException('static_file must stay inside the static elements directory.');
        }
        $normalized = FilesystemSupport::normalizeRelativePath($value);
        if ($normalized === '') { return ''; }
        $modx = $context->modx();
        $resolved = strtr($normalized, array(
            '{base_path}' => $modx->getOption('base_path'),
            '{core_path}' => $modx->getOption('core_path'),
            '{assets_path}' => $modx->getOption('assets_path'),
            '[[++base_path]]' => $modx->getOption('base_path'),
            '[[++core_path]]' => $modx->getOption('core_path'),
            '[[++assets_path]]' => $modx->getOption('assets_path'),
        ));
        $root = FilesystemSupport::normalizePath($modx->getOption('core_path')) . DIRECTORY_SEPARATOR . 'elements';
        $absolute = FilesystemSupport::isAbsolutePath($resolved)
            ? FilesystemSupport::normalizePath($resolved)
            : FilesystemSupport::normalizePath(rtrim($modx->getOption('base_path'), '/\\') . '/' . ltrim($resolved, '/\\'));
        if (strpos($absolute, $root . DIRECTORY_SEPARATOR) !== 0) {
            throw new \ModxMCPClientException('static_file must stay inside the static elements directory.');
        }
        // A lexical path check alone misses symbolic links into other directories.
        // Resolve the closest existing path (or the target itself) before trusting it.
        $rootReal = realpath($root);
        if ($rootReal === false) {
            throw new \ModxMCPClientException('Static elements directory is missing.');
        }
        $probe = $absolute;
        while (!file_exists($probe) && !is_link($probe)) {
            $parent = dirname($probe);
            if ($parent === $probe) {
                throw new \ModxMCPClientException('Cannot resolve static file path.');
            }
            $probe = $parent;
        }
        $resolvedReal = realpath($probe);
        if ($resolvedReal === false
            || ($resolvedReal !== $rootReal
                && strpos($resolvedReal, $rootReal . DIRECTORY_SEPARATOR) !== 0)) {
            throw new \ModxMCPClientException('static_file resolves outside the static elements directory.');
        }
        return $normalized;
    }

    public static function loadLexicons($context)
    {
        call_user_func_array(
            array($context->modx()->lexicon, 'load'),
            array('core:default', 'core:resource', 'core:element', 'core:tv', 'core:category', 'core:plugin')
        );
    }

    public static function transaction($context, $callback)
    {
        $modx = $context->modx();
        $modx->beginTransaction();
        try {
            $result = call_user_func($callback);
            $modx->commit();
            return $result;
        } catch (\Throwable $e) {
            $modx->rollback();
            throw $e;
        }
    }

    public static function handlePluginEvents($context, $pluginId, array $data)
    {
        if (!array_key_exists('events', $data)) { return; }

        if (is_string($data['events'])) {
            $raw = trim($data['events']);
            $events = $raw === '' ? array() : array_map('trim', explode(',', $raw));
        } else {
            $events = $data['events'];
        }
        if (!is_array($events)) {
            throw new \ModxMCPClientException('plugin events must be an array or comma-separated string.');
        }

        $normalized = array();
        foreach ($events as $eventName) {
            $eventName = trim((string)$eventName);
            if ($eventName === '') { continue; }
            if (!in_array($eventName, $normalized, true)) {
                $normalized[] = $eventName;
            }
        }

        $modx = $context->modx();
        $pluginEventClass = $context->platform()->className('plugin_event');
        $eventClass = $context->platform()->className('event');
        $pluginId = (int)$pluginId;

        // Validate the complete requested set before changing any relation.
        $missing = array();
        foreach ($normalized as $eventName) {
            if (!$modx->getObject($eventClass, array('name' => $eventName))) {
                $missing[] = $eventName;
            }
        }
        if (!empty($missing)) {
            throw new \ModxMCPClientException(
                'Unknown MODX event(s): ' . implode(', ', $missing)
            );
        }

        // Explicit [] means clear all subscriptions. Absence of the field was
        // handled above and leaves the current subscriptions unchanged.
        $modx->removeCollection($pluginEventClass, array('pluginid' => $pluginId));

        foreach ($normalized as $eventName) {
            $link = $modx->newObject($pluginEventClass);
            $link->fromArray(
                array(
                    'pluginid' => $pluginId,
                    'event' => $eventName,
                    'priority' => 0,
                    'propertyset' => 0,
                ),
                '',
                true,
                true
            );
            if (!$link->save()) {
                throw new \ModxMCPClientException(
                    'Failed to attach plugin event: ' . $eventName
                );
            }
        }
    }

    public static function handleTvRelations($context, $tvId, array $data)
    {
        $modx = $context->modx();
        $tvClass = $context->platform()->className('tv');
        $linkClass = $context->platform()->className('template_var_template');
        $sourceElementClass = $context->platform()->className('media_source_element');
        $tv = $modx->getObject($tvClass, (int)$tvId);
        if (!$tv) { return; }

        if (isset($data['templates']) && is_array($data['templates'])) {
            $modx->removeCollection($linkClass, array('tmplvarid' => (int)$tvId));
            foreach ($data['templates'] as $templateId) {
                if (empty($templateId)) { continue; }
                $link = $modx->newObject($linkClass);
                $link->fromArray(
                    array('tmplvarid' => (int)$tvId, 'templateid' => $templateId),
                    '',
                    true,
                    true
                );
                $link->save();
            }
        }

        if (isset($data['input_properties']) && is_array($data['input_properties'])) {
            $tv->set('input_properties', $data['input_properties']);
            $tv->save();
        }

        if (isset($data['media_source'])) {
            $source = $modx->getObject(
                $sourceElementClass,
                array(
                    'object' => (int)$tvId,
                    'object_class' => $tvClass,
                    'context_key' => 'web',
                )
            );
            if (!$source) {
                $source = $modx->newObject($sourceElementClass);
                $source->fromArray(
                    array(
                        'object' => (int)$tvId,
                        'object_class' => $tvClass,
                        'context_key' => 'web',
                    ),
                    '',
                    true,
                    true
                );
            }
            $source->set('source', (int)$data['media_source']);
            $source->save();
        }
    }

    public static function shouldAutoStatic($context, $type)
    {
        if (!in_array($type, array('chunk', 'snippet', 'template', 'plugin'), true)) {
            return false;
        }
        return (bool)$context->modx()->getOption('modxmcp.auto_static', null, false);
    }

    public static function staticMap($context)
    {
        return array(
            'chunk' => array('class' => $context->platform()->className('chunk'), 'field' => 'snippet', 'dir' => 'chunks', 'ext' => 'tpl'),
            'snippet' => array('class' => $context->platform()->className('snippet'), 'field' => 'snippet', 'dir' => 'snippets', 'ext' => 'php'),
            'template' => array('class' => $context->platform()->className('template'), 'field' => 'content', 'dir' => 'templates', 'ext' => 'tpl'),
            'plugin' => array('class' => $context->platform()->className('plugin'), 'field' => 'plugincode', 'dir' => 'plugins', 'ext' => 'php'),
        );
    }

    public static function makeStatic($context, $type, $id)
    {
        $map = self::staticMap($context);
        if (!isset($map[$type])) {
            throw new \ModxMCPClientException("make_static: unsupported type '{$type}'.");
        }
        $item = $map[$type];
        $modx = $context->modx();
        $element = $modx->getObject($item['class'], (int)$id);
        if (!$element) {
            throw new \ModxMCPClientException("make_static: {$type} {$id} not found.");
        }

        $nameField = $type === 'template' ? 'templatename' : 'name';
        $name = (string)$element->get($nameField);
        $slug = preg_replace('/[^A-Za-z0-9._-]+/', '-', $name);
        $slug = trim($slug, '-._');
        if (strlen(preg_replace('/[^A-Za-z0-9]/', '', $slug)) < 3) {
            $slug = $type . '-' . $id;
        }

        $relative = 'core/elements/' . $item['dir'] . '/' . $slug . '.' . $item['ext'];
        $base = rtrim($modx->getOption('base_path'), '/') . '/';
        $absolute = $base . $relative;
        if (file_exists($absolute) && !(bool)$element->get('static')) {
            $relative = 'core/elements/' . $item['dir'] . '/' . $slug . '-' . $id . '.' . $item['ext'];
            $absolute = $base . $relative;
        }

        $directory = dirname($absolute);
        if (!is_dir($directory) && !@mkdir($directory, 0755, true)) {
            throw new \ModxMCPClientException("make_static: cannot create directory {$directory}");
        }
        if (@file_put_contents($absolute, (string)$element->get($item['field'])) === false) {
            throw new \ModxMCPClientException("make_static: cannot write {$absolute}");
        }

        $element->set('static', true);
        $element->set('static_file', $relative);
        $element->set('source', 1);
        if (!$element->save()) {
            throw new \ModxMCPClientException("make_static: save failed for {$type} {$id}");
        }
        return array(
            'type' => $type,
            'id' => (int)$id,
            'name' => $name,
            'static_file' => $relative,
        );
    }

    public static function previewDelete($context, $type, $id)
    {
        $modx = $context->modx();
        if ($type === 'resource') {
            $class = $context->platform()->className('resource');
            $resource = $modx->getObject($class, (int)$id);
            if (!$resource) {
                throw new \ModxMCPClientException("resource {$id} not found.");
            }
            $children = (int)$modx->getCount($class, array('parent' => (int)$id));
            return array(
                'dry_run' => true,
                'would_delete' => array(
                    'type' => 'resource',
                    'id' => (int)$id,
                    'pagetitle' => $resource->get('pagetitle'),
                    'uri' => $resource->get('uri'),
                ),
                'child_resources' => $children,
                'warning' => $children > 0
                    ? "Has {$children} child resource(s) that would be affected."
                    : null,
            );
        }

        if (!in_array($type, array('chunk', 'snippet', 'template', 'tv', 'category', 'plugin'), true)) {
            throw new \ModxMCPClientException("Cannot preview delete for type {$type}.");
        }
        $class = $context->platform()->className($type);
        $nameField = ElementSupport::nameField($type);
        $object = $modx->getObject($class, (int)$id);
        if (!$object) {
            throw new \ModxMCPClientException("{$type} {$id} not found.");
        }
        $name = (string)$object->get($nameField);
        $usages = $type === 'category'
            ? array('name' => $name)
            : (new FindUsagesTool())->execute(
                $context,
                array('name' => $name, 'limit' => 100)
            );

        if (isset($usages['content_matches'])) {
            $usages['content_matches'] = array_values(array_filter(
                $usages['content_matches'],
                function ($hit) use ($id, $type) {
                    return !(
                        (int)$hit['id'] === (int)$id
                        && $hit['type'] === $type
                    );
                }
            ));
        }
        $count = isset($usages['content_matches'])
            ? count($usages['content_matches'])
            : 0;
        return array(
            'dry_run' => true,
            'would_delete' => array(
                'type' => $type,
                'id' => (int)$id,
                'name' => $name,
            ),
            'usages' => $usages,
            'warning' => $count > 0
                ? "Name '{$name}' is referenced in {$count} place(s) (and possibly more) — deleting may break them."
                : null,
        );
    }

    public static function refreshCache($context)
    {
        $manager = $context->modx()->getCacheManager();
        if ($manager) { $manager->refresh(); }
    }
}
