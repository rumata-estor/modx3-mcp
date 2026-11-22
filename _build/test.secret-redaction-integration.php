<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Защита секретов в диагностических данных и её автоматическая проверка.
 * EN: Diagnostic secret redaction and its automated verification.
 */
// Sandboxed integration test: real audit writer + both real log readers, fake MODX context.
class ModxMCPClientException extends Exception {}
require_once __DIR__ . '/../core/components/modxmcp/src/Security/SecretRedactor.php';
require_once __DIR__ . '/../core/components/modxmcp/src/Tools/ToolInterface.php';
require_once __DIR__ . '/../core/components/modxmcp/src/Tools/StateSupport.php';
require_once __DIR__ . '/../core/components/modxmcp/src/Tools/FilesystemSupport.php';
require_once __DIR__ . '/../core/components/modxmcp/src/Tools/AuditSupport.php';
require_once __DIR__ . '/../core/components/modxmcp/src/Tools/AuditLogReadTool.php';
require_once __DIR__ . '/../core/components/modxmcp/src/Tools/ErrorLogReadTool.php';
class RedactionFakeUser { public function get($key) { return 42; } }
class RedactionFakeModx {
    public $user;
    public $logged = array();
    private $root;
    public function __construct($root) { $this->root=$root; $this->user=new RedactionFakeUser(); }
    public function getOption($key, $unused=null, $default=null) {
        if ($key === 'core_path') return $this->root . '/';
        if ($key === 'modxmcp.audit_log') return true;
        return $default;
    }
    public function log($level,$message) { $this->logged[]=$message; }
}
class RedactionFakeContext {
    private $modx;
    public function __construct($modx) { $this->modx=$modx; }
    public function modx() { return $this->modx; }
}
function assertSafe($label, $data) {
    $text=json_encode($data, JSON_UNESCAPED_UNICODE);
    if (strpos($text, 'FAKE_SECRET_') !== false) throw new RuntimeException('LEAK: ' . $label);
}
function eraseTree($root) {
    if (!is_dir($root)) return;
    foreach (new FilesystemIterator($root) as $item) {
        if ($item->isDir() && !$item->isLink()) eraseTree($item->getPathname());
        else unlink($item->getPathname());
    }
    rmdir($root);
}
$root=sys_get_temp_dir().'/modxmcp-redaction-integration-'.bin2hex(random_bytes(8));
mkdir($root.'/cache/logs',0700,true);
$modx=new RedactionFakeModx($root);
$context=new RedactionFakeContext($modx);
try {
    \ModxMcp\Tools\AuditSupport::log($context,'update_element','snippet',array(
       'password'=>'FAKE_SECRET_PASS', 'metadata'=>array(
          'api_token'=>'FAKE_SECRET_TOKEN',
          'message'=>'Failure credentials=FAKE_SECRET_CREDENTIALS',
          'status'=>'failed'
       )
    ));
    $auditPath=$root.'/components/modxmcp/logs/audit.log';
    if (!file_exists($auditPath)) throw new RuntimeException('audit log absent');
    assertSafe('written audit file',file_get_contents($auditPath));
    assertSafe('MODX log output',$modx->logged);
    $audit=(new \ModxMcp\Tools\AuditLogReadTool())->execute($context,array());
    assertSafe('MCP read audit', $audit);
    if ($audit['entries'][0]['payload']['metadata']['status'] !== 'failed')
       throw new RuntimeException('normal audit value changed');
    // Historically unredacted logs must be sanitized on the read boundary.
    file_put_contents($auditPath, json_encode(array(
       'action'=>'legacy', 'payload'=>array('password'=>'FAKE_SECRET_OLD')
    ))."\n", FILE_APPEND);
    $audit=(new \ModxMcp\Tools\AuditLogReadTool())->execute($context,array());
    assertSafe('legacy MCP read audit', $audit);
    file_put_contents($root.'/cache/logs/error.log',
       "Connection error password=FAKE_SECRET_ERR\n".
       "Authorization: Bearer FAKE_SECRET_BEARER\n".
       "Status: failed\n");
    $errors=(new \ModxMcp\Tools\ErrorLogReadTool())->execute($context,array('limit'=>3));
    assertSafe('MCP read error', $errors);
    if (!in_array('Status: failed',$errors['lines'],true))
        throw new RuntimeException('diagnostic context modified');
    echo "SECRET_REDACTION_INTEGRATION_PHP_OK\n";
} finally { eraseTree($root); }
