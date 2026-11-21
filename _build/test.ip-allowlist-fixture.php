<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Проверка «ip-allowlist-fixture»; тестовый код не запускается на production.
 * EN: «ip-allowlist-fixture» regression test; not executed in production.
 */
/** Offline MCP IP allowlist regression; never connects to MODX or DB. */
if (PHP_SAPI !== 'cli' || $argc !== 4) { exit(2); }
class IpPolicyFakeModx {
    private $options;
    public function __construct($options) { $this->options = $options; }
    public function setLogLevel($level) {}
    public function getOption($key, $unused = null, $default = null) {
        return array_key_exists($key, $this->options) ? $this->options[$key] : $default;
    }
}
$modx = new IpPolicyFakeModx(array(
    'modxmcp.require_https' => true,
    'modxmcp.trust_proxy_https' => false,
    'modxmcp.allowed_ips' => $argv[1],
    'modxmcp.enabled' => true,
    'modxmcp.api_token' => 'nonempty-test-token',
));
$modxmcpVariant = 'test';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REMOTE_ADDR'] = $argv[2];
$_SERVER['SERVER_PORT'] = '443';
$_SERVER['HTTPS'] = 'on';
$_SERVER['HTTP_X_MCP_TOKEN'] = $argv[3];
require __DIR__ . '/../core/components/modxmcp/endpoint/api.common.php';
