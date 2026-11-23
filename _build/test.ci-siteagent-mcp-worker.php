<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Одноразовый межрепозиторный тест Site Agent ↔ реальный MODX MCP.
 * EN: Disposable Site Agent ↔ actual MODX MCP native-dispatch interop.
 */
if (PHP_SAPI !== 'cli'
    || getenv('MODXMCP_DISPOSABLE_CI') !== '1'
    || strpos((string)getenv('MODX_CONFIG_CORE'), '/home/runner/') !== 0
    || !is_file((string)getenv('MODX_CONFIG_CORE'))
    || !isset($argv[1])) {
    fwrite(STDERR, "Refusing interop worker outside disposable GitHub CI.\n");
    exit(3);
}
$input = json_decode((string)file_get_contents('php://stdin'), true);
if (!is_array($input)) { exit(4); }
$mode = (string)$argv[1];
$name = isset($input['name']) ? (string)$input['name'] : '';
if ($mode !== 'site_state'
    && !preg_match('/^CI_AGENT_CROSS_[a-f0-9]{12}$/', $name)) {
    exit(5);
}
require getenv('MODX_CONFIG_CORE');
$is2 = !is_file(MODX_CORE_PATH . 'src/Revolution/modX.php');
if ($is2) {
    require_once MODX_CORE_PATH . 'model/modx/modx.class.php';
    $modx = new modX();
} else {
    require_once MODX_CORE_PATH . 'vendor/autoload.php';
    $modx = \MODX\Revolution\modX::getInstance();
}
$modx->initialize('mgr');
$modx->setOption('modxmcp.disabled_groups', '');
require_once MODX_CORE_PATH . 'components/modxmcp/model/modxmcp.class.php';
$api = new modxMCP($modx);
try {
    if ($mode === 'site_state') {
        $result = $api->processRequest('get_site_state','',array());
    } elseif ($mode === 'list') {
        $result = $api->processRequest('list_elements','',array(
            'type'=>'chunk','query'=>$name,'limit'=>100
        ));
    } elseif ($mode === 'create_lost') {
        if (!isset($input['content']) || !is_string($input['content'])) {
            exit(6);
        }
        $state = $api->processRequest('get_site_state','',array());
        $created = $api->processRequest('create_element','',array(
            'type'=>'chunk','name'=>$name,'content'=>$input['content'],
            '_runtime_preconditions'=>array(
                'observed_site_revision'=>(int)$state['site_revision'],
                'strict_site_revision'=>true,'read_set'=>array()
            )
        ));
        if (!is_array($created) || (int)($created['id']??0) < 1) {
            exit(7);
        }
        // The native dispatcher has already returned success and committed
        // the resource. Simulate a lost response at the transport boundary.
        // No response or real object ID is sent to Site Agent.
        exit(86);
    } elseif ($mode === 'read') {
        $id = isset($input['id']) ? (int)$input['id'] : 0;
        if ($id < 1) { exit(8); }
        $result = $api->processRequest('get_element','',array(
            'type'=>'chunk','id'=>$id
        ));
        if (($result['name']??'') !== $name) { exit(9); }
    } elseif ($mode === 'cleanup') {
        $id = isset($input['id']) ? (int)$input['id'] : 0;
        if ($id < 1) { exit(10); }
        $before = $api->processRequest('get_element','',array(
            'type'=>'chunk','id'=>$id
        ));
        if (($before['name']??'') !== $name) { exit(11); }
        $state = $api->processRequest('get_site_state','',array());
        $result = $api->processRequest('delete_element','',array(
            'type'=>'chunk','id'=>$id,'dry_run'=>false,
            '_runtime_preconditions'=>array(
                'observed_site_revision'=>(int)$state['site_revision'],
                'strict_site_revision'=>true,'read_set'=>array()
            )
        ));
    } else {
        exit(12);
    }
    echo json_encode($result, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
} catch (Throwable $error) {
    // Never expose native processor output or secrets to cross-repo logs.
    fwrite(STDERR, "CI MCP worker call failed.\n");
    exit(13);
}
