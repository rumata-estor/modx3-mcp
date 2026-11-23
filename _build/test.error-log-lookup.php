<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Поиск одной ошибки в защищённом ограниченном журнале.
 * EN: ID-scoped, bounded log lookup and secret-redaction regression.
 */
class ModxMCPClientException extends Exception {}
$root = dirname(__DIR__);
require $root . '/core/components/modxmcp/src/Tools/ToolInterface.php';
require $root . '/core/components/modxmcp/src/Tools/FilesystemSupport.php';
require $root . '/core/components/modxmcp/src/Security/SecretRedactor.php';
require $root . '/core/components/modxmcp/src/Tools/ErrorLogReadTool.php';
class ErrorLookupFakeModx {
    private $core;
    public function __construct($core) { $this->core = $core; }
    public function getOption($key) { return $key === 'core_path' ? $this->core : null; }
}
class ErrorLookupFakeContext {
    private $modx;
    public function __construct($modx) { $this->modx = $modx; }
    public function modx() { return $this->modx; }
}
function assertLookup($condition, $message) {
    if (!$condition) throw new RuntimeException('ERROR_LOG_LOOKUP_FAIL ' . $message);
}
$base = sys_get_temp_dir() . '/modxmcp-error-log-' . bin2hex(random_bytes(6));
$folder = $base . '/cache/logs';
assertLookup(mkdir($base, 0700), 'root');
assertLookup(mkdir($base . '/cache', 0700), 'cache');
assertLookup(mkdir($folder, 0700), 'logs');
$log = $folder . '/error.log';
$id = 'modxmcp_0123456789abcdef01234567';
try {
    file_put_contents($log, "2026-11-23 [other] password=HIDDEN_SECRET\n"
        . "2026-11-23 [$id] code=CAS_REQUIRED action=update_element\n"
        . "2026-11-23 [$id] code=STALE_STATE action=update_element\n"
        . "2026-11-23 [other] unrelated\n");
    $tool = new \ModxMcp\Tools\ErrorLogReadTool();
    $ctx = new ErrorLookupFakeContext(new ErrorLookupFakeModx($base . '/'));
    assertLookup($tool->group() === 'ops', 'protected group changed');
    $result = $tool->execute($ctx, array('error_id' => $id, 'limit' => 10));
    assertLookup($result['total'] === 2, 'must only return matching incident');
    assertLookup($result['error_id'] === $id, 'identifier was not echoed');
    foreach ($result['lines'] as $line) {
        assertLookup(strpos($line, '[' . $id . ']') !== false, 'unrelated line leaked');
        assertLookup(strpos($line, 'HIDDEN_SECRET') === false, 'secret leaked');
    }
    $denied = false;
    try { $tool->execute($ctx, array('error_id' => 'malformed\nSECRET')); }
    catch (ModxMCPClientException $e) { $denied = true; }
    assertLookup($denied, 'invalid identifier permitted');
    echo "ERROR_LOG_LOOKUP_SCOPED_OK\n";
} finally {
    @unlink($log);
    @rmdir($folder);
    @rmdir($base . '/cache');
    @rmdir($base);
}
