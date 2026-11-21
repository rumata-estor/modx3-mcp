<?php
namespace ModxMcp\Tools;

class MediaFileRenameTool implements ToolInterface
{
    public function name() { return 'rename_media_file'; }
    public function group() { return 'media'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx(); }

    public function execute($context, array $data)
    {
        $source = MediaSourceMutationSupport::resolveForFileOperation($context, $data);
        $path = MediaSourceMutationSupport::relativePath(
            isset($data['path']) ? $data['path'] : ''
        );
        $newName = MediaSourceMutationSupport::leafName(
            isset($data['new_name']) ? $data['new_name'] : ''
        );
        $result = $source->renameObject($path, $newName);
        if ($result === false) {
            throw new \ModxMCPClientException(
                'rename_media_file failed: '
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
                'path' => $path,
                'new_name' => $newName,
            )
        );
        return array(
            'renamed' => true,
            'path' => $path,
            'new_name' => $newName,
        );
    }
}
