<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Инструмент MCP «Media Folder Create»: выполняется через защищённый диспетчер.
 * EN: MCP tool «Media Folder Create»: invoked through the guarded dispatcher.
 */
namespace ModxMcp\Tools;

class MediaFolderCreateTool implements ToolInterface
{
    public function name() { return 'create_media_folder'; }
    public function group() { return 'media'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx(); }

    public function execute($context, array $data)
    {
        // RU: Раньше path игнорировался и папка создавалась в корне.
        // RU: Отклоняем неоднозначный запрос до любого файлового изменения.
        // EN: Previously path was ignored and folders could be created at root.
        // EN: Reject ambiguous requests before any filesystem side effect.
        if (array_key_exists('path', $data)) {
            throw new \ModxMCPClientException(
                'create_media_folder: unsupported path field; '
                . 'use parent for the destination directory and name for the new folder.'
            );
        }
        $source = MediaSourceMutationSupport::resolveForFileOperation($context, $data);
        $parentDir = MediaSourceMutationSupport::relativePath(
            isset($data['parent']) ? $data['parent'] : '/',
            true
        );
        $parent = $parentDir === '' ? '/' : $parentDir . '/';
        $name = MediaSourceMutationSupport::leafName(
            isset($data['name']) ? $data['name'] : ''
        );
        $result = $source->createContainer($name, $parent);
        if ($result === false) {
            throw new \ModxMCPClientException(
                'create_media_folder failed: '
                . MediaSourceMutationSupport::error($source, 'unknown error')
            );
        }
        MediaSourceMutationSupport::refresh($context);
        AuditSupport::log(
            $context,
            $this->name(),
            'source',
            array(
                'source' => (int)$source->get('id'),
                'parent' => $parent,
                'name' => $name,
            )
        );
        return array(
            'created' => true,
            'parent' => $parent,
            'name' => $name,
        );
    }
}
