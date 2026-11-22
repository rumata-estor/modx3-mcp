<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Инструмент MCP «Media Folder Delete»: выполняется через защищённый диспетчер.
 * EN: MCP tool «Media Folder Delete»: invoked through the guarded dispatcher.
 */
namespace ModxMcp\Tools;

class MediaFolderDeleteTool implements ToolInterface
{
    public function name() { return 'delete_media_folder'; }
    public function group() { return 'media'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        $source = MediaSourceMutationSupport::resolveForFileOperation($context, $data);
        $path = MediaSourceMutationSupport::relativePath(
            isset($data['path']) ? $data['path'] : ''
        );
        $removePath = $path;
        // RU: MODX2 FileMediaSource принимает абсолютный путь, но кастомному
        // RU: драйверу передаём только путь относительно его виртуального root.
        // EN: MODX2 local FS needs an absolute path; custom drivers must not
        // EN: receive host filesystem paths instead of source-relative keys.
        if ((int)$context->platform()->majorVersion() === 2
            && MediaSourceSupport::isBuiltinLocalFileSource($source)) {
            $removePath = FilesystemSupport::joinPath(
                MediaSourceSupport::rootPath($context, $source),
                $path
            );
        }

        $result = $source->removeContainer($removePath);
        if ($result === false) {
            throw new \ModxMCPClientException(
                'delete_media_folder failed: '
                . MediaSourceMutationSupport::error($source, 'unknown error')
            );
        }
        MediaSourceMutationSupport::refresh($context);
        AuditSupport::log(
            $context,
            $this->name(),
            'source',
            array('source' => (int)$source->get('id'), 'path' => $removePath)
        );
        return array('deleted' => true, 'path' => $removePath);
    }
}
