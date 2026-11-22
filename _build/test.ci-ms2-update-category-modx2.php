<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: MODX 2: реальное обновление собственной неопубликованной категории miniShop2.
 * EN: Scoped MODX 2 miniShop2 category update with complete native processor payload.
 */
if (PHP_SAPI !== 'cli' || getenv('MODXMCP_DISPOSABLE_CI') !== '1'
    || strpos((string)getenv('MODX_CONFIG_CORE'), '/home/runner/') !== 0
    || !is_file((string)getenv('MODX_CONFIG_CORE'))
    || strpos((string)getenv('RUNNER_TEMP'), '/home/runner/') !== 0) {
    fwrite(STDERR, "Refusing miniShop2 category E2E outside disposable CI.\n");
    exit(3);
}
require getenv('MODX_CONFIG_CORE');
$is2 = !is_file(MODX_CORE_PATH . 'src/Revolution/modX.php');
if ($is2) { require MODX_CORE_PATH . 'model/modx/modx.class.php'; $modx = new modX(); }
else { require MODX_CORE_PATH . 'vendor/autoload.php'; $modx = \MODX\Revolution\modX::getInstance(); }
if (!$is2) throw new RuntimeException('miniShop2 is supported on MODX 2 only');
$modx->initialize('mgr');
$modx->setOption('modxmcp.disabled_groups', '');
$path = MODX_CORE_PATH . 'components/minishop2/';
if (!is_file($path . 'model/minishop2/mscategory.class.php')) {
    throw new RuntimeException('Pinned miniShop2 msCategory model absent');
}
$admin = $modx->getObject($is2 ? 'modUser' : '\MODX\Revolution\modUser', 1);
if (!$admin || (int)$admin->get('sudo') !== 1) {
    throw new RuntimeException('Disposable MODX manager sudo not available');
}
$modx->user = $admin;
$modx->user->addSessionContext('mgr');
if (!$modx->hasPermission('mssetting_save')) {
    throw new RuntimeException('Native miniShop2 settings permission absent');
}
if (!$modx->addPackage('minishop2', $path . 'model/')) {
    throw new RuntimeException('Cannot load native miniShop2 model');
}
$manager = $modx->getManager();
foreach (array('msOption', 'msCategoryOption', 'msProductOption') as $cls) {
    if (!$manager->createObjectContainer($cls)) {
        throw new RuntimeException('Native option table unavailable: '.$cls);
    }
}
require MODX_CORE_PATH . 'components/modxmcp/model/modxmcp.class.php';
require __DIR__.'/test.cas-mcp-helper.php';
$api = new modxMCP($modx);
$checks = 0;
function catRpc($action,array $args=array()) {
    global $api;
    return disposableCasRpc($api,$action,$args);
}
function catCheck($ok,$msg) {
    global $checks;
    if(!$ok)throw new RuntimeException('MS2_CATEGORY_NATIVE_E2E_FAILED: '.$msg);
    $checks++;
}
function catRev() { $s=catRpc('get_site_state'); return (int)$s['site_revision']; }
function catGuard() { return array('observed_site_revision'=>catRev(), 'strict_site_revision'=>true,'read_set'=>array()); }
function catWrite($action,array $args) {
    $before=catRev();
    $args['_runtime_preconditions']=catGuard();
    $result=catRpc($action,$args);
    catCheck(catRev()===$before+1,$action.' CAS committed once');
    return array($result,$args);
}
function catStale($action,array $request) {
    $before=catRev();$denied=false;
    try { catRpc($action,$request); }
    catch(ModxMCPClientException $e) { $denied=strpos($e->getMessage(),'STALE_STATE')!==false; }
    catCheck($denied,$action.' stale replay denied');
    catCheck(catRev()===$before,$action.' replay did not change revision');
}
function catRead($id) { return catRpc('get_element',array('type'=>'resource','id'=>$id)); }
$mark=bin2hex(random_bytes(5));
$title='MCP_CI_MS2_Category_'.$mark;
$id=0;
try {
    list($created,$old)=catWrite('ms2_create_category',array(
        'pagetitle'=>$title,'alias'=>'mcp-ms2-category-'.$mark,
        'template'=>0,'parent'=>0,'context_key'=>'web','published'=>0,
        'hidemenu'=>1,'class_key'=>'msCategory',
    ));
    $id=(int)($created['id']??0);
    catCheck($id>0,'native msCategory created through MCP');
    $read=catRead($id);
    catCheck((int)($read['id']??0)===$id,'category independent MCP readback');
    catCheck(($read['pagetitle']??'')===$title,'created title matches');
    catCheck(($read['class_key']??'')==='msCategory','created actual msCategory');
    catCheck(empty($read['published']),'category unpublished');
    catStale('ms2_create_category',$old);
    echo "CAS_MS2_CREATE_CATEGORY_NATIVE_READBACK_STALE_OK\n";

    list($updated,$old)=catWrite('ms2_update_category',array(
        'id'=>$id,'pagetitle'=>$title.'_updated','published'=>0,
        'class_key'=>'msCategory', 'context_key'=>'web',
        'parent'=>0, 'alias'=>'mcp-ms2-category-'.$mark,
    ));
    $read=catRead($id);
    catCheck(($read['pagetitle']??'')===$title.'_updated','category updated through MCP');
    catCheck(empty($read['published']),'updated category unpublished');
    catStale('ms2_update_category',$old);
    catCheck((catRead($id)['pagetitle']??'')===$title.'_updated','stale category update had no effect');
    echo "CAS_MS2_UPDATE_CATEGORY_NATIVE_READBACK_STALE_OK\n";

} finally {
    // RU: Только собственная непубличная категория, в одноразовом MODX.
    // EN: Cleanup fixture via MCP; workflow destroys the ephemeral database.
    if($id>0) {
        try { catRpc('delete_element',array('type'=>'resource','id'=>$id,'dry_run'=>false)); }
        catch(Throwable $ignored) {}
    }
}
echo 'CAS_MS2_CATEGORY_NATIVE_E2E_OK checks='.$checks
    .' platform='.($is2?'modx2':'modx3').PHP_EOL;
