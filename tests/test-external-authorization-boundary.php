<?php
/* Offline boundary regression for MODX 2/3 shared model. No CMS bootstrap. */
$variant = isset($argv[1]) ? $argv[1] : 'modx3';
if (!in_array($variant, array('modx2', 'modx3'), true)) { exit(2); }
$path = $variant === 'modx2'
    ? '/../core/components/modxmcp/legacy/modx2/modxmcp.class.php'
    : '/../core/components/modxmcp/model/modxmcp.class.php';
require_once __DIR__ . $path;

$reads = array('list_elements', 'get_element', 'list_categories', 'get_site_state', 'read_media_source_file');
$blocked = array(
    'create_element', 'update_element', 'delete_element',
    'update_resource_tvs', 'run_processor', 'install_package',
    'regenerate_token', 'update_system_setting', 'create_media_file',
    'clear_cache', 'flush_permissions', 'made_up_read_action',
    'list_elements_and_delete', 'delete_category'
);
foreach ($reads as $action) {
    modxMCP::assertExternalAuthorizationBoundary($action);
}
foreach ($blocked as $action) {
    $denied = false;
    try {
        modxMCP::assertExternalAuthorizationBoundary($action);
    } catch (ModxMCPClientException $e) {
        $denied = true;
    }
    if (!$denied) {
        fwrite(STDERR, 'Unauthorized action accepted: ' . $action . PHP_EOL);
        exit(1);
    }
}
echo "EXTERNAL_GATE_FAIL_CLOSED_OK\n";
