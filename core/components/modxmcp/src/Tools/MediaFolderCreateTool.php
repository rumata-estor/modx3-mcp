<?php
namespace ModxMcp\Tools;

class MediaFolderCreateTool implements ToolInterface
{
    public function name() { return 'create_media_folder'; }
    public function group() { return 'media'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx(); }

    public function execute($context, array $data)
    {
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
