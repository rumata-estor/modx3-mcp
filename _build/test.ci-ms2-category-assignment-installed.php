<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Прямое disposable E2E категорий и назначения свойств miniShop2.
 * EN: Native pinned miniShop2 category and option assignment MCP CAS E2E.
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
    ));
    $read=catRead($id);
    catCheck(($read['pagetitle']??'')===$title.'_updated','category updated through MCP');
    catCheck(empty($read['published']),'updated category unpublished');
    catStale('ms2_update_category',$old);
    catCheck((catRead($id)['pagetitle']??'')===$title.'_updated','stale category update had no effect');
    echo "CAS_MS2_UPDATE_CATEGORY_NATIVE_READBACK_STALE_OK\n";

    $key='mcpdisposablecategoryoption'.$mark;
    list($createdOption,$optionOld)=catWrite('ms2_create_option',array(
        'key'=>$key,'caption'=>'Disposable category option',
        'type'=>'textfield','category'=>0,
    ));
    $listing=catRpc('ms2_list_options',array('query'=>$key,'limit'=>100));
    $match=array_values(array_filter($listing['results']??array(),function($r)use($key){
        return is_array($r)&&($r['key']??'')===$key;
    }));
    catCheck(count($match)===1,'test option created and independently visible');
    $optionId=(int)$match[0]['id'];
    catCheck($optionId>0,'option ID positive');

    list($assigned,$old)=catWrite('ms2_assign_option_to_category',array(
        'option_id'=>$optionId,'category_id'=>$id,
    ));
    $byCategory=catRpc('ms2_list_options',array('category'=>$id,'query'=>$key,'limit'=>100));
    $selected=array_values(array_filter($byCategory['results']??array(),function($r)use($key){
        return is_array($r)&&($r['key']??'')===$key;
    }));
    catCheck(count($selected)===1,'assigned option visible in category via MCP');
    catStale('ms2_assign_option_to_category',$old);
    $again=catRpc('ms2_list_options',array('category'=>$id,'query'=>$key,'limit'=>100));
    $still=array_filter($again['results']??array(),function($r)use($key){return ($r['key']??'')===$key;});
    catCheck(count($still)===1,'stale assignment added no duplicate');
    echo "CAS_MS2_ASSIGN_OPTION_CATEGORY_NATIVE_READBACK_STALE_OK\n";
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
