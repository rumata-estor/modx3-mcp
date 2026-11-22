<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Только loopback HTTP имитатор /verify, без внешнего каталога пакетов.
 * EN: Loopback-only /verify response with no external package catalog.
 */
if (getenv('MODXMCP_DISPOSABLE_CI')!=='1'
    || !in_array($_SERVER['REMOTE_ADDR']??'',array('127.0.0.1','::1'),true)) {
    http_response_code(403);exit;
}
if (parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH)!=='/verify') {
    http_response_code(404);exit;
}
header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?><response><verified>1</verified></response>';
