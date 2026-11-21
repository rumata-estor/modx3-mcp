<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Проверка «proxy-https-fixture»; тестовый код не запускается на production.
 * EN: «proxy-https-fixture» regression test; not executed in production.
 */
/** Offline HTTPS proxy trust regression: no MODX or database. */
if (PHP_SAPI !== 'cli' || $argc !== 5) { exit(2); }
class ProxyPolicyFakeModx {
    private $options;
    public function __construct($options) { $this->options = $options; }
    public function setLogLevel($level) {}
    public function getOption($key, $unused = null, $default = null) {
        return array_key_exists($key, $this->options) ? $this->options[$key] : $default;
    }
}
$modx = new ProxyPolicyFakeModx(array(
    'modxmcp.require_https' => 1,
    'modxmcp.trust_proxy_https' => (int)$argv[1],
    'modxmcp.trusted_proxy_ips' => $argv[2],
    'modxmcp.core_path' => '/nonexistent/',
    'core_path' => '/nonexistent/',
    'modxmcp.enabled' => false,
));
$modxmcpVariant = 'test';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REMOTE_ADDR'] = $argv[3];
$_SERVER['SERVER_PORT'] = '80';
$_SERVER['HTTPS'] = 'off';
$_SERVER['HTTP_X_FORWARDED_PROTO'] = $argv[4];
require __DIR__ . '/../core/components/modxmcp/endpoint/api.common.php';
