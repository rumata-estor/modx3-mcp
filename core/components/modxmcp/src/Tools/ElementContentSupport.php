<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Вспомогательная логика «Element Content» для инструментов MODX MCP.
 * EN: Shared «Element Content» implementation used by MODX MCP tools.
 */
namespace ModxMcp\Tools;

class ElementContentSupport
{
    public static function map($context, $type)
    {
        $map = array(
            'chunk' => array('class' => 'chunk', 'field' => 'snippet'),
            'snippet' => array('class' => 'snippet', 'field' => 'snippet'),
            'template' => array('class' => 'template', 'field' => 'content'),
            'plugin' => array('class' => 'plugin', 'field' => 'plugincode'),
            // RU: По идее @AgelxNash PR #55: содержимое TV — default_text.
            // EN: TV content is its default_text, proposed by @AgelxNash PR #55.
            'tv' => array('class' => 'tv', 'field' => 'default_text'),
        );
        if (!isset($map[$type])) {
            throw new \ModxMCPClientException('type must be one of: chunk, snippet, template, plugin, tv.');
        }
        $map[$type]['class'] = $context->platform()->className($map[$type]['class']);
        return $map[$type];
    }

    public static function resolve($context, array $data)
    {
        $type = isset($data['type']) ? (string)$data['type'] : '';
        $map = self::map($context, $type);
        $id = ElementSupport::resolveId($context, $type, $data);
        if ($id <= 0) {
            throw new \ModxMCPClientException('Element not found (provide id or name).');
        }
        $element = $context->modx()->getObject($map['class'], $id, false);
        if (!$element) {
            throw new \ModxMCPClientException($type . ' ' . $id . ' not found.');
        }
        return array($element, $type, $id, $map);
    }

    public static function read($context, $element, array $map)
    {
        $isStatic = (bool)$element->get('static');
        $absolute = null;
        if ($isStatic) {
            $relative = (string)$element->get('static_file');
            if ($relative !== '') {
                $modx = $context->modx();
                $relative = strtr($relative, array(
                    '{base_path}' => $modx->getOption('base_path'),
                    '{core_path}' => $modx->getOption('core_path'),
                    '{assets_path}' => $modx->getOption('assets_path'),
                    '[[++base_path]]' => $modx->getOption('base_path'),
                    '[[++core_path]]' => $modx->getOption('core_path'),
                    '[[++assets_path]]' => $modx->getOption('assets_path'),
                ));
                $absolute = FilesystemSupport::isAbsolutePath($relative)
                    ? $relative
                    : rtrim($modx->getOption('base_path'), '/\\') . '/' . ltrim($relative, '/\\');
            }
            if ($absolute !== null) {
                self::assertSafeStaticPath($context, $absolute);
                if (is_file($absolute)) {
                    return array((string)file_get_contents($absolute), true, $absolute);
                }
            }
        }
        return array((string)$element->get($map['field']), $isStatic, $absolute);
    }

    /**
     * RU: Сохранённый static_file нельзя считать доверенным: он мог быть
     * RU: изменён через Manager или другой компонент после проверки ввода.
     * EN: Stored static_file is untrusted: Manager or plugins can modify it.
     * EN: Require the real path or nearest existing parent inside core/elements.
     */
    public static function assertSafeStaticPath($context, $absolute)
    {
        $root = rtrim((string)$context->modx()->getOption('core_path'), '/\\')
            . DIRECTORY_SEPARATOR . 'elements';
        $rootReal = realpath($root);
        $coreReal = realpath((string)$context->modx()->getOption('core_path'));
        // RU: core/elements не должен быть симлинком на внешний каталог.
        // RU: Сам core_path допускает штатный симлинк хостинга.
        // EN: core/elements cannot be redirected by a symlink; hosting's
        // EN: own core_path may legitimately resolve through a symlink.
        if (is_link($root) || $coreReal === false || $rootReal === false
            || !is_dir($rootReal)
            || $rootReal !== rtrim($coreReal, DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR . 'elements') {
            throw new \ModxMCPClientException('Static elements directory is unavailable or unsafe.');
        }
        $absolute = (string)$absolute;
        if (!FilesystemSupport::isAbsolutePath($absolute)) {
            throw new \ModxMCPClientException('static_file path must be absolute after resolution.');
        }
        // RU: Не принимаем traversal до поиска существующих родителей.
        // EN: Reject traversal before checking any existing ancestor.
        $slashPath = str_replace('\\', '/', $absolute);
        if (preg_match('#(?:^|/)\.\.(?:/|$)#', $slashPath)) {
            throw new \ModxMCPClientException('static_file traversal is forbidden.');
        }
        $candidate = $absolute;
        while (!file_exists($candidate) && !is_link($candidate)) {
            $parent = dirname($candidate);
            if ($parent === $candidate) {
                throw new \ModxMCPClientException('Cannot resolve static_file parent.');
            }
            $candidate = $parent;
        }
        $real = realpath($candidate);
        if ($real === false || ($real !== $rootReal
            && strpos($real, $rootReal . DIRECTORY_SEPARATOR) !== 0)) {
            throw new \ModxMCPClientException(
                'static_file resolves outside core/elements.'
            );
        }
        // RU: realpath() не видит hard link: файл внутри elements может
        // RU: разделять inode с секретным файлом вне дерева.
        // EN: realpath() cannot detect hard links to outside files;
        // EN: reject multiply linked files before reads or writes.
        if (is_file($absolute)) {
            clearstatcache(true, $absolute);
            $stat = @stat($absolute);
            if ($stat === false || !isset($stat['nlink'])
                || (int)$stat['nlink'] !== 1) {
                throw new \ModxMCPClientException(
                    'static_file hard link or unreadable link metadata is forbidden.'
                );
            }
        }
    }

    public static function write(
        $context,
        $element,
        $type,
        array $map,
        $isStatic,
        $absolute,
        $content
    ) {
        if ($isStatic && $absolute !== null) {
            // RU: Повторная проверка после чтения: симлинк мог измениться.
            // EN: Revalidate before writes; a symlink may change after read.
            self::assertSafeStaticPath($context, $absolute);
            $directory = dirname($absolute);
            if (!is_dir($directory) && !@mkdir($directory, 0755, true)) {
                throw new \ModxMCPClientException(
                    'cannot create directory ' . $directory
                );
            }
            // RU: Не усекать исходный файл до завершения записи.
            // EN: Write a same-directory temporary file before replacing original.
            $written = StaticFileWriteSupport::replace($context, $absolute, $content);
            if ($written === false || $written !== strlen($content)) {
                // RU: Частичная запись могла повредить файл; DB ещё не изменена.
                // EN: Even a short write may corrupt existing static content.
                throw new \ModxMCPClientException(
                    'MUTATION_OUTCOME_UNCERTAIN: static file write incomplete; '
                    . 'inspect filesystem and MODX state before retry.'
                );
            }
        }

        $data = $element->toArray();
        $data[$map['field']] = $content;
        $data = ElementMutationSupport::filterData($type, $data);
        $response = $context->platform()->runProcessor(
            $context->modx(),
            ElementSupport::processorBase($type) . 'update',
            $data
        );
        if (!$response || $response->isError()) {
            $detail = $response
                ? ProcessorSupport::error($response)
                : 'no response.';
            if ($isStatic && $absolute !== null) {
                // RU: Файл уже изменён, но процессор MODX мог откатить БД.
                // RU: Возвращаем неопределённый исход, не безопасный retry.
                // EN: Static bytes were written before a failed processor;
                // EN: database rollback cannot undo the filesystem effect.
                throw new \ModxMCPClientException(
                    'MUTATION_OUTCOME_UNCERTAIN: static element file was written '
                    . 'but MODX processor failed; inspect file and element before retry. '
                    . $detail
                );
            }
            throw new \ModxMCPClientException('element save failed: ' . $detail);
        }
    }
}
