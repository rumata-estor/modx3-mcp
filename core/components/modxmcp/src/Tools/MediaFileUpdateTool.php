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

    /**
     * RU: Проверяем сохранённую ранее версию через родной Media Source API.
     * RU: Если драйвер не умеет надёжно читать содержимое — стоп до записи.
     * EN: Best-effort optimistic guard through the native MODX source API.
     * EN: A foreign writer may still race after this read; no atomic claim.
     */
    public static function assertFresh($source, $path, array $data)
    {
        $expected = isset($data['expected_sha256'])
            && is_string($data['expected_sha256'])
            ? strtolower($data['expected_sha256']) : '';
        if (!preg_match('/^[a-f0-9]{64}$/D', $expected)) {
            throw new \ModxMCPClientException(
                'MANUAL_REVIEW_REQUIRED: media overwrite requires expected_sha256'
                . ' from an independently checked current file.'
            );
        }
        if (!method_exists($source, 'getObjectContents')) {
            throw new \ModxMCPClientException(
                'MANUAL_REVIEW_REQUIRED: media source cannot verify file contents.'
            );
        }
        try {
            $actual = $source->getObjectContents($path);
        } catch (\Throwable $e) {
            throw new \ModxMCPClientException(
                'MANUAL_REVIEW_REQUIRED: media source could not read file for CAS.'
            );
        }
        // MODX core returns an object description array with 'content';
        // some custom drivers return raw bytes directly. Accept only the
        // documented payload shapes; fail closed on all other forms.
        if (is_array($actual) && array_key_exists('content', $actual)) {
            $actual = $actual['content'];
        }
        if (!is_string($actual)) {
            throw new \ModxMCPClientException(
                'MANUAL_REVIEW_REQUIRED: media source returned no verifiable file contents.'
            );
        }
        if (!hash_equals($expected, hash('sha256', $actual))) {
            throw new \ModxMCPClientException(
                'STALE_STATE: media file changed; compare it manually before overwriting.'
            );
        }
    }

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
        self::assertFresh($source, $path, $data);
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
