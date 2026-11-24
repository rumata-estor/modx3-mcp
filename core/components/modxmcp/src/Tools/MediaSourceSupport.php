<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Вспомогательная логика «Media Source» для инструментов MODX MCP.
 * EN: Shared «Media Source» implementation used by MODX MCP tools.
 */
namespace ModxMcp\Tools;

class MediaSourceSupport
{
    public static function resolve($context, array $data)
    {
        $modx = $context->modx();
        $class = $context->platform()->className('media_source');

        if (!empty($data['id'])) {
            return $modx->getObject($class, (int) $data['id']);
        }
        if (!empty($data['name'])) {
            return $modx->getObject($class, array('name' => $data['name']));
        }
        return null;
    }

    public static function normalize($context, $source, $includeProperties = true)
    {
        $result = array(
            'id' => $source->get('id'),
            'name' => $source->get('name'),
            'class_key' => $source->get('class_key'),
            'description' => $source->get('description'),
        );

        if ($includeProperties) {
            $result['properties'] = self::normalizeProperties($source->get('properties'));
            try {
                $result['root_path'] = self::rootPath($context, $source);
            } catch (\Exception $e) {
                $result['root_path'] = null;
                $result['root_path_error'] = $e->getMessage();
            }
        }

        return $result;
    }

    public static function rootPath($context, $source)
    {
        $modx = $context->modx();
        $basePath = self::propertyValue($source->get('properties'), 'basePath');

        if (($basePath === '' || $basePath === null) && method_exists($source, 'initialize')) {
            try {
                $source->initialize();
            } catch (\Exception $e) {
                // Continue with the same fallback chain as the legacy implementation.
            }
        }

        if (($basePath === '' || $basePath === null) && method_exists($source, 'getProperty')) {
            $basePath = $source->getProperty('basePath');
        }
        if (($basePath === '' || $basePath === null) && method_exists($source, 'getBasePath')) {
            $basePath = $source->getBasePath();
        }
        if (($basePath === '' || $basePath === null) && method_exists($source, 'getBases')) {
            $bases = $source->getBases('');
            if (is_array($bases) && !empty($bases['path'])) {
                $basePath = $bases['path'];
            }
        }
        if (($basePath === '' || $basePath === null) && self::isFilesystem($source)) {
            $basePath = $modx->getOption('assets_path');
        }

        if ($basePath === '' || $basePath === null) {
            throw new \ModxMCPClientException("Media source {$source->get('id')} does not define basePath.");
        }

        $resolved = self::resolvePlaceholders($modx, (string) $basePath);
        if ($resolved === '' && self::isFilesystem($source)) {
            $resolved = (string) $modx->getOption('assets_path');
        }

        if (!FilesystemSupport::isAbsolutePath($resolved)) {
            $resolved = rtrim($modx->getOption('base_path'), '/\\')
                . DIRECTORY_SEPARATOR . ltrim($resolved, '/\\');
        }

        return FilesystemSupport::normalizePath($resolved);
    }

    private static function resolvePlaceholders($modx, $path)
    {
        return strtr((string) $path, array(
            '{base_path}' => $modx->getOption('base_path'),
            '{core_path}' => $modx->getOption('core_path'),
            '{assets_path}' => $modx->getOption('assets_path'),
            '[[++base_path]]' => $modx->getOption('base_path'),
            '[[++core_path]]' => $modx->getOption('core_path'),
            '[[++assets_path]]' => $modx->getOption('assets_path'),
        ));
    }

    private static function normalizeProperties($properties)
    {
        if (!is_array($properties)) {
            return $properties;
        }

        $normalized = array();
        foreach ($properties as $key => $value) {
            if (is_array($value) && array_key_exists('value', $value) && count($value) === 1) {
                $normalized[$key] = $value['value'];
                continue;
            }
            $normalized[$key] = is_array($value)
                ? self::normalizeProperties($value)
                : $value;
        }
        return $normalized;
    }

    private static function propertyValue($properties, $key)
    {
        if (!is_array($properties) || !array_key_exists($key, $properties)) {
            return '';
        }
        $value = $properties[$key];
        if (is_array($value) && array_key_exists('value', $value)) {
            return $value['value'];
        }
        return $value;
    }

    /**
     * RU: Только стандартный локальный драйвер допускает прямое чтение PHP.
     * EN: Raw PHP filesystem access is valid only for MODX's built-in local driver.
     */
    public static function isBuiltinLocalFileSource($source)
    {
        $classKey = ltrim((string)$source->get('class_key'), '\\');
        return $classKey === 'modFileMediaSource'
            || $classKey === 'sources.modFileMediaSource'
            || $classKey === 'MODX\\Revolution\\Sources\\modFileMediaSource';
    }

    /**
     * RU: Обычный локальный Media Source внутри assets доступен без
     * RU: глобального разрешения читать файловую систему сайта.
     * EN: Stock local sources inside canonical assets may be read without
     * EN: granting site-root or arbitrary filesystem browsing.
     *
     * Returns the approved root so the caller does not resolve it again.
     */
    public static function assertLocalReadAllowed($context, $source)
    {
        if (!self::isBuiltinLocalFileSource($source)) {
            throw new \ModxMCPClientException(
                'This media source uses a custom or remote driver. '
                . 'Direct filesystem browsing is not supported; use a supported local MODX file source.'
            );
        }
        return self::assertReadAllowed($context, $source);
    }

    private static function canonicalRoot($path)
    {
        if (!is_string($path) || $path === '' || strpos($path, "\0") !== false) {
            return false;
        }
        $canonical = realpath($path);
        if ($canonical === false || !is_dir($canonical)) {
            return false;
        }
        return rtrim($canonical, DIRECTORY_SEPARATOR) ?: DIRECTORY_SEPARATOR;
    }

    private static function pathWithin($path, $root)
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            return strcasecmp($path, $root) === 0
                || stripos($path, rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) === 0;
        }
        return $path === $root
            || strpos($path, rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) === 0;
    }

    public static function assertReadAllowed($context, $source)
    {
        $modx = $context->modx();
        $root = self::rootPath($context, $source);
        // RU: Администраторский opt-in сохраняет прежний расширенный доступ.
        // EN: Explicit opt-in keeps the previous extended local read behavior.
        if ((bool)$modx->getOption('modxmcp.allow_root_filesystem_read', null, false)) {
            return $root;
        }

        $assets = self::canonicalRoot((string)$modx->getOption('assets_path'));
        $sourceRoot = self::canonicalRoot($root);
        $site = self::canonicalRoot((string)$modx->getOption('base_path'));
        $core = self::canonicalRoot((string)$modx->getOption('core_path'));

        // RU: Отказ при неизвестном корне, при assets == корень сайта или
        // RU: при попадании источника в core/за пределы assets.
        // EN: Do not silently grant root/core/external access on a broken
        // EN: installation or when assets itself resolves to the site root.
        if ($assets !== false && $sourceRoot !== false
            && $assets !== DIRECTORY_SEPARATOR
            && ($site === false || $assets !== $site)
            && self::pathWithin($sourceRoot, $assets)
            && ($site === false || $sourceRoot !== $site)
            && ($core === false || !self::pathWithin($sourceRoot, $core))) {
            return $sourceRoot;
        }
        throw new \ModxMCPClientException(
            'Filesystem media source browsing is disabled by modxmcp.allow_root_filesystem_read '
            . 'outside canonical assets_path. Only stock local sources inside assets are readable by default.'
        );
    }

    private static function isFilesystem($source)
    {
        $classKey = (string) $source->get('class_key');
        $name = (string) $source->get('name');
        return stripos($classKey, 'File') !== false || strcasecmp($name, 'Filesystem') === 0;
    }
}
