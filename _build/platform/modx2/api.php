<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: HTTP-точка входа MODX MCP; не обходите проверки общего endpoint.
 * EN: MODX MCP HTTP entry point; do not bypass the shared endpoint checks.
 */
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/config.core.php';
require_once MODX_CORE_PATH . 'model/modx/modx.class.php';

$modx = new modX();
$modx->initialize('mgr');
if (method_exists($modx, 'getService')) {
    $modx->getService('error', 'error.modError');
}
$modxmcpVariant = 'modx2';
require MODX_CORE_PATH . 'components/modxmcp/endpoint/api.common.php';
