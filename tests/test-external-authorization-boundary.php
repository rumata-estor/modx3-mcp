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
// Positive pilot scope + fail-closed negative shape tests on both models.
modxMCP::assertExternalAuthorizedWriteShape(
    'update_element', 'chunk', array('id'=>7, 'content'=>'new')
);
$invalidShapes = array(
    array('delete_element','chunk',array('id'=>7,'content'=>'new')),
    array('create_element','chunk',array('id'=>7,'content'=>'new')),
    array('update_element','snippet',array('id'=>7,'content'=>'new')),
    array('update_element','chunk',array('id'=>0,'content'=>'new')),
    array('update_element','chunk',array('id'=>'7','content'=>'new')),
    array('update_element','chunk',array('id'=>true,'content'=>'new')),
    array('update_element','chunk',array('id'=>7,'content'=>null)),
    array('update_element','chunk',array('id'=>7,'content'=>'new','name'=>'evil')),
    array('update_element','chunk',array('id'=>7,'content'=>'new','_runtime_preconditions'=>array())),
    array('update_element','chunk',array('id'=>7)),
);
foreach ($invalidShapes as $params) {
    try {
        modxMCP::assertExternalAuthorizedWriteShape($params[0],$params[1],$params[2]);
        throw new RuntimeException('Unclassified protected mutation shape accepted');
    } catch (ModxMCPClientException $expected) { /* deny */ }
}

// The real dispatcher must reject a write BEFORE any service-user lookup,
// modular registry route or legacy fallback is reached.
$reflection = new ReflectionClass('modxMCP');
$dispatcher = $reflection->newInstanceWithoutConstructor();
$dispatcher->modx = new class {
    public function getOption($name, $options = null, $default = null) {
        if ($name === 'modxmcp.external_authorization_required') {
            return true;
        }
        throw new RuntimeException('Service-user or runtime path reached: ' . $name);
    }
};
try {
    $dispatcher->processRequest('update_element', 'chunk', array('id' => 1));
    fwrite(STDERR, "Dispatcher accepted a protected write\\n");
    exit(1);
} catch (ModxMCPClientException $e) {
    if (strpos($e->getMessage(), 'Protected Site Agent mode') === false) {
        throw $e;
    }
}

echo "EXTERNAL_GATE_FAIL_CLOSED_OK\n";
