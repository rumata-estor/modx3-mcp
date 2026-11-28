<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Совместимая точка входа, без монолитных обработчиков.
 * EN: Compatible entry point, no monolithic handlers.
 */
if (!class_exists('ModxMCPClientException')) {
    class ModxMCPClientException extends Exception {}
}
(function () {
    $src = dirname(__DIR__) . '/src';
    if (!is_file($src . '/Autoloader.php')) {
        $src = dirname(dirname(__DIR__)) . '/src';
    }
    require_once $src . '/Autoloader.php';
    \ModxMcp\Autoloader::register($src);
    require_once $src . '/Core/Connector.php';
    require_once $src . '/Registry/CapabilityService.php';
})();
class modxMCP {
    const VERSION = '1.2.7';
    const VARIANT = 'modx3';
    use \ModxMcp\Core\Connector;
    public function __construct(\MODX\Revolution\modX &$modx, array $config = array()) {
        $this->initializeConnector($modx, $config);
    }
}
