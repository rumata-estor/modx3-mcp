<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Проверяет маскирование секретов на одноразовой установке настоящего MODX.
 * EN: Verifies secret redaction on a disposable real MODX installation.
 */
if (PHP_SAPI !== 'cli' || getenv('MODXMCP_DISPOSABLE_CI') !== '1'
    || strpos((string)getenv('MODX_CONFIG_CORE'), '/home/runner/') !== 0
    || strpos((string)getenv('RUNNER_TEMP'), '/home/runner/') !== 0
    || !is_file((string)getenv('MODX_CONFIG_CORE'))) {
    fwrite(STDERR, "Refusing redaction integration outside disposable GitHub CI.\n");
    exit(3);
}
require_once getenv('MODX_CONFIG_CORE');
$is2 = !is_file(MODX_CORE_PATH . 'src/Revolution/modX.php');
if ($is2) {
    require_once MODX_CORE_PATH . 'model/modx/modx.class.php';
    $modx = new modX();
} else {
    require_once MODX_CORE_PATH . 'vendor/autoload.php';
    $modx = \MODX\Revolution\modX::getInstance();
}
$modx->initialize('mgr');
require_once MODX_CORE_PATH . 'components/modxmcp/model/modxmcp.class.php';
$module = new modxMCP($modx);
require_once __DIR__ . '/../core/components/modxmcp/src/Tools/StateSupport.php';
require_once __DIR__ . '/../core/components/modxmcp/src/Tools/AuditSupport.php';
require_once __DIR__ . '/../core/components/modxmcp/src/Tools/AuditLogReadTool.php';
require_once __DIR__ . '/../core/components/modxmcp/src/Tools/ErrorLogReadTool.php';
$runtimeContext = new \ModxMcp\Core\Context($modx, null);
$marker = 'ci_redaction_' . bin2hex(random_bytes(5));
$secret = 'FAKE_CI_SECRET_' . bin2hex(random_bytes(6));
$logDir = rtrim((string)$modx->getOption('core_path'), '/\\') . '/components/modxmcp/logs';
$auditFile = $logDir . '/audit.log';
$priorAuditSize = is_file($auditFile) ? filesize($auditFile) : 0;
$modx->setOption('modxmcp.audit_log', true);
$modx->setOption('modxmcp.disabled_groups', '');
\ModxMcp\Tools\AuditSupport::log($runtimeContext, $marker, 'redaction_fixture', array(
    'password' => $secret,
    'details' => array('api_token' => $secret, 'message' => 'password=' . $secret),
    'state' => 'expected'
));
if (!is_file($auditFile)) throw new RuntimeException('Audit file not written');
$written = file_get_contents($auditFile, false, null, $priorAuditSize);
if (strpos($written, $secret) !== false) throw new RuntimeException('Audit file contains secret');
$read = (new \ModxMcp\Tools\AuditLogReadTool())->execute($runtimeContext, array('action' => $marker, 'limit' => 5));
if (empty($read['entries'])) throw new RuntimeException('Audit MCP read returned no entries');
$serialized = json_encode($read);
if (strpos($serialized, $secret) !== false || strpos($serialized, '[REDACTED]') === false)
    throw new RuntimeException('Audit MCP read exposed secret');
if ($read['entries'][0]['payload']['state'] !== 'expected')
    throw new RuntimeException('Audit MCP read changed nonsecret state');
$modx->setLogLevel(0);
$modx->setLogTarget('FILE');
$modx->log(3, 'CI REDACTION ' . $marker . ' password=' . $secret);
$errors = (new \ModxMcp\Tools\ErrorLogReadTool())->execute($runtimeContext, array('limit' => 25));
if (strpos(json_encode($errors), $secret) !== false)
    throw new RuntimeException('Error MCP read exposed secret');
$found = false;
foreach ($errors['lines'] as $line) {
    if (strpos($line, $marker) !== false) {
        $found = true;
        if (strpos($line, '[REDACTED]') === false)
            throw new RuntimeException('Missing redaction in error log MCP response');
    }
}
if (!$found) throw new RuntimeException('Error MCP log probe not found');
echo 'SECRET_REDACTION_REAL_MODX_' . ($is2 ? '2' : '3') . '_OK' . PHP_EOL;
