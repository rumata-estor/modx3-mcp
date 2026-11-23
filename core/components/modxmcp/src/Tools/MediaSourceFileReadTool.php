<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Инструмент MCP «Media Source File Read»: выполняется через защищённый диспетчер.
 * EN: MCP tool «Media Source File Read»: invoked through the guarded dispatcher.
 */
namespace ModxMcp\Tools;

class MediaSourceFileReadTool implements ToolInterface
{
    public function name() { return 'read_media_source_file'; }
    public function group() { return 'media'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        $source = MediaSourceSupport::resolve($context, $data);
        if (!$source) {
            throw new \ModxMCPClientException('Media source not found.');
        }
        MediaSourceSupport::assertLocalReadAllowed($context, $source);
        if (empty($data['path'])) {
            throw new \ModxMCPClientException('File path is required.');
        }

        $rootPath = MediaSourceSupport::rootPath($context, $source);
        $relativePath = FilesystemSupport::normalizeRelativePath($data['path']);
        $absolutePath = FilesystemSupport::joinPath($rootPath, $relativePath);
        if (!is_file($absolutePath)) {
            throw new \ModxMCPClientException("File not found: {$relativePath}");
        }

        $bounded = FilesystemSupport::readFileBounded(
            $context->modx(), $absolutePath, $data
        );
        // RU: Хеш выдаётся лишь для полного, сверенного с MODX чтения.
        // RU: Хеш частичного или противоречивого снимка использовать нельзя.
        // EN: Advertise a media CAS hash only for a complete snapshot
        // EN: matching the native MODX source bytes, never partial reads.
        if ($bounded['offset'] === 0 && !$bounded['truncated']
            && $bounded['returned_bytes'] === $bounded['size']
            && method_exists($source, 'getObjectContents')) {
            try {
                $source->initialize();
                $native = $source->getObjectContents($relativePath);
                if (is_array($native) && array_key_exists('content', $native)) {
                    $native = $native['content'];
                }
                $bytes = $bounded['encoding'] === 'base64'
                    ? base64_decode($bounded['content'], true)
                    : $bounded['content'];
                if (is_string($native) && is_string($bytes)
                    && hash_equals(hash('sha256', $native), hash('sha256', $bytes))) {
                    $bounded['sha256'] = hash('sha256', $native);
                }
            } catch (\Throwable $e) {
                // No hash means no safe automatic media overwrite.
            }
        }
        return array_merge(
            array(
                'media_source' => MediaSourceSupport::normalize($context, $source, false),
                'path' => $relativePath,
            ),
            $bounded
        );
    }
}
