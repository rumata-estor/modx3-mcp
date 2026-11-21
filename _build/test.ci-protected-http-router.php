<?php
/**
 * Disposable-only PHP built-in-server router.
 * Simulates TLS termination by marking the trusted local test request HTTPS.
 * It does NOT prove real transport TLS/CA validation.
 */
if (getenv('MODXMCP_DISPOSABLE_CI')!=='1'
    || strpos((string)getenv('MODX_CONFIG_CORE'),'/home/runner/')!==0
    || !isset($_SERVER['REMOTE_ADDR'])
    || $_SERVER['REMOTE_ADDR']!=='127.0.0.1') {
    http_response_code(403);exit;
}
if (parse_url((string)$_SERVER['REQUEST_URI'],PHP_URL_PATH)
    !== '/assets/components/modxmcp/api.php') {
    http_response_code(404);exit;
}
$_SERVER['HTTPS']='on';
require rtrim((string)$_SERVER['DOCUMENT_ROOT'],'/')
    .'/assets/components/modxmcp/api.php';
