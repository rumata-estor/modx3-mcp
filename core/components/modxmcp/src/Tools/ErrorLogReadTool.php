<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Инструмент MCP «Error Log Read»: выполняется через защищённый диспетчер.
 * EN: MCP tool «Error Log Read»: invoked through the guarded dispatcher.
 */
namespace ModxMcp\Tools;

class ErrorLogReadTool implements ToolInterface
{
    public function name() { return 'read_error_log'; }
    public function group() { return 'ops'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx(); }

    public function execute($context, array $data)
    {
        $path = rtrim($context->modx()->getOption('core_path'), '/') . '/cache/logs/error.log';
        if (!file_exists($path)) {
            return array(
                'total' => 0,
                'lines' => array(),
                'path' => $path,
                'result_complete' => true,
                'scanned_bytes' => 0,
                'truncated_lines' => 0,
            );
        }
        $limit = isset($data['limit']) ? max(1, (int)$data['limit']) : 100;
        // RU: Поиск по номеру инцидента без чтения всего журнала и выдачи соседних строк.
        // EN: Filter inside the bounded log tail; retain existing protected ops ACL.
        $errorId = isset($data['error_id']) ? (string)$data['error_id'] : '';
        if ($errorId !== '' && !preg_match(
            '/\\A(?:modxmcp_[a-f0-9]{16,64}|mcpnode_[a-f0-9]{24})\\z/D',
            $errorId
        )) {
            throw new \ModxMCPClientException('INVALID_REQUEST: invalid diagnostic identifier.');
        }
        $filter = null;
        if ($errorId !== '') {
            $needle = '[' . $errorId . ']';
            $filter = function ($line) use ($needle) {
                return strpos($line, $needle) !== false ? $line : null;
            };
        }
        $metadata = array();
        $tail = FilesystemSupport::readTailLines($path, $limit, $filter, $metadata);
        $tail = array_map(array('ModxMcp\\Security\\SecretRedactor', 'text'), $tail);
        return array_merge(array('total' => count($tail), 'lines' => $tail,
            'error_id' => $errorId !== '' ? $errorId : null), $metadata);
    }
}
