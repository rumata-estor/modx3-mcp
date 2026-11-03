<?php
namespace ModxMcp\Tools;

class AuditLogReadTool implements ToolInterface
{
    public function name() { return 'read_audit_log'; }
    public function group() { return 'ops'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx(); }

    public function execute($context, array $data)
    {
        $path = rtrim($context->modx()->getOption('core_path'), '/') . '/components/modxmcp/logs/audit.log';
        if (!file_exists($path)) { return array('total' => 0, 'entries' => array()); }
        $filterAction = isset($data['action']) ? (string)$data['action'] : '';
        $limit = isset($data['limit']) ? max(1, (int)$data['limit']) : 100;
        $entries = FilesystemSupport::readTailLines($path, $limit, function ($line) use ($filterAction) {
            $line = trim($line);
            if ($line === '') { return null; }
            $entry = json_decode($line, true);
            if (!is_array($entry)) { return null; }
            if ($filterAction !== '' && (!isset($entry['action']) || $entry['action'] !== $filterAction)) {
                return null;
            }
            return $entry;
        });
        return array('total' => count($entries), 'entries' => $entries);
    }
}
