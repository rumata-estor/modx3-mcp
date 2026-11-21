<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Инструмент MCP «Media File Update»: выполняется через защищённый диспетчер.
 * EN: MCP tool «Media File Update»: invoked through the guarded dispatcher.
 */
namespace ModxMcp\Tools;

class MediaFileUpdateTool implements ToolInterface
{
    public function name() { return 'update_media_file'; }
    public function group() { return 'media'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx(); }

    public function execute($context, array $data)
    {
        $source = MediaSourceMutationSupport::resolveForFileOperation($context, $data);
        $path = MediaSourceMutationSupport::relativePath(
            isset($data['path']) ? $data['path'] : ''
        );
        if (!array_key_exists('content', $data)) {
            throw new \ModxMCPClientException(
                'update_media_file: "content" is required.'
            );
        }
        $result = $source->updateObject($path, (string)$data['content']);
        if ($result === false) {
            throw new \ModxMCPClientException(
                'update_media_file failed: '
                . MediaSourceMutationSupport::error($source, 'unknown error')
            );
        }
        MediaSourceMutationSupport::refresh($context);
        AuditSupport::log(
            $context,
            $this->name(),
            'source',
            array('source' => (int)$source->get('id'), 'path' => $path)
        );
        return array('updated' => true, 'path' => $path);
    }
}
