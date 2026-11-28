<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Инструмент MCP «Media File Create»: выполняется через защищённый диспетчер.
 * EN: MCP tool «Media File Create»: invoked through the guarded dispatcher.
 */
namespace ModxMcp\Tools;

class MediaFileCreateTool implements ToolInterface
{
    public function name() { return 'create_media_file'; }
    public function group() { return 'media'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx(); }

    public function execute($context, array $data)
    {
        $source = MediaSourceMutationSupport::resolveForFileOperation($context, $data);
        $relativeDir = MediaSourceMutationSupport::relativePath(
            isset($data['path']) ? $data['path'] : '',
            true
        );
        $dir = $relativeDir === '' ? '/' : $relativeDir . '/';
        $name = isset($data['name']) ? (string)$data['name'] : '';
        $name = MediaSourceMutationSupport::leafName($name);
        $result = $source->createObject(
            $dir,
            $name,
            isset($data['content']) ? (string)$data['content'] : ''
        );
        if ($result === false) {
            throw new \ModxMCPClientException(
                MediaSourceMutationSupport::fileWriteError($source, $name, 'create_media_file')
            );
        }
        MediaSourceMutationSupport::refresh($context);
        AuditSupport::log(
            $context,
            $this->name(),
            'source',
            array(
                'source' => (int)$source->get('id'),
                'path' => $dir,
                'name' => $name,
            )
        );
        return array(
            'created' => true,
            'path' => rtrim($dir, '/') . '/' . $name,
        );
    }
}
