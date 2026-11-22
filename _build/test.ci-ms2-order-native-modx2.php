<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Только одноразовый MODX2: штатный seed заказа, изменение через MCP.
 * EN: Disposable MODX2 native order fixture, real MCP update/readback/CAS.
 */
if (PHP_SAPI !== 'cli' || getenv('MODXMCP_DISPOSABLE_CI') !== '1'
    || strpos((string)getenv('MODX_CONFIG_CORE'), '/home/runner/') !== 0
    || !is_file((string)getenv('MODX_CONFIG_CORE'))
    || strpos((string)getenv('RUNNER_TEMP'), '/home/runner/') !== 0) {
    fwrite(STDERR,"Refusing miniShop2 order E2E outside disposable CI.\n");
    exit(3);
}
require getenv('MODX_CONFIG_CORE');
if (is_file(MODX_CORE_PATH.'src/Revolution/modX.php')) {
    throw new RuntimeException('miniShop2 order test is MODX 2 only');
}
require MODX_CORE_PATH.'model/modx/modx.class.php';
$modx=new modX();
$modx->initialize('mgr');
$modx->setOption('modxmcp.disabled_groups','');
$path=MODX_CORE_PATH.'components/minishop2/';
if (!is_file($path.'processors/mgr/orders/create.class.php')
    || !is_file($path.'processors/mgr/orders/update.class.php')) {
    throw new RuntimeException('Pinned miniShop2 native order processors missing');
}
$admin=$modx->getObject('modUser',1);
if(!$admin || (int)$admin->get('sudo')!==1) {
    throw new RuntimeException('Disposable admin unavailable');
}
$modx->user=$admin;
$modx->user->addSessionContext('mgr');
if(!$modx->hasPermission('msorder_save')
    || !$modx->hasPermission('msorder_view')) {
    throw new RuntimeException('Native miniShop2 order permission absent');
}
if(!$modx->addPackage('minishop2',$path.'model/')) {
    throw new RuntimeException('Could not load native miniShop2 package');
}
$manager=$modx->getManager();
foreach(array('msOrder','msOrderAddress','msOrderStatus','msOrderProduct','msOrderLog',
              'msDelivery','msPayment') as $class) {
    if(!$manager->createObjectContainer($class)) {
        throw new RuntimeException('Disposable order model unavailable: '.$class);
    }
}
$miniShop2=$modx->getService('miniShop2','miniShop2',$path.'model/minishop2/');
if(!$miniShop2 || !$miniShop2->initialize('mgr')) {
    throw new RuntimeException('Native miniShop2 order service failed to initialize');
}
require MODX_CORE_PATH.'components/modxmcp/model/modxmcp.class.php';
require __DIR__.'/test.cas-mcp-helper.php';
$api=new modxMCP($modx);
$checks=0;
function orderRpc($action,array $args=array()) {
    global $api;
    return disposableCasRpc($api,$action,$args);
}
function orderCheck($ok,$why) {
    global $checks;
    if(!$ok)throw new RuntimeException('MS2_ORDER_NATIVE_E2E_FAILED '.$why);
    ++$checks;
}
function orderRev() {
    $v=orderRpc('get_site_state');
    return (int)$v['site_revision'];
}
function orderGuard() {
    return array('observed_site_revision'=>orderRev(),'strict_site_revision'=>true,'read_set'=>array());
}
$id=0;
$fixture='MCP_disposable_order_'.bin2hex(random_bytes(5));
try {
    // RU: Только подготовка своей фикстуры через штатный miniShop2 processor.
    // EN: Native processor seeds an ephemeral order; all tested mutation is MCP.
    $seed=$modx->runProcessor('mgr/orders/create',array(
        'context'=>'web','order_comment'=>$fixture.'_before',
        'delivery'=>1,'payment'=>1,
    ),array('processors_path'=>$path.'processors/'));
    if(!$seed || $seed->isError()) {
        throw new RuntimeException('Native disposable order fixture creation unavailable');
    }
    $data=$seed->getObject();
    $id=(int)($data['id']??0);
    orderCheck($id>0,'native order fixture ID');
    $before=orderRpc('ms2_get_order',array('id'=>$id));
    orderCheck((int)($before['id']??0)===$id,'native order get via MCP');
    orderCheck(($before['order_comment']??'')===$fixture.'_before',
        'fixture has original comment through MCP');
    $status=(int)($before['status']??0);
    $delivery=(int)($before['delivery']??0);
    $payment=(int)($before['payment']??0);
    orderCheck($status>0,'native order has valid draft status');
    // RU: Не меняем status — этот E2E доказывает только обычное обновление.
    // EN: No status transition is claimed by this positive test.
    $start=orderRev();
    $request=array(
        'id'=>$id,'status'=>$status,
        'delivery'=>$delivery>0?$delivery:1,
        'payment'=>$payment>0?$payment:1,
        'order_comment'=>$fixture.'_after',
        '_runtime_preconditions'=>orderGuard(),
    );
    $result=orderRpc('ms2_update_order',$request);
    orderCheck(orderRev()===$start+1,'update advanced CAS once');
    $after=orderRpc('ms2_get_order',array('id'=>$id));
    orderCheck((int)($after['id']??0)===$id,'post-update independent MCP read');
    orderCheck(($after['order_comment']??'')===$fixture.'_after',
        'native order comment persisted through MCP');
    orderCheck((int)($after['status']??0)===$status,'order status unchanged');
    $stale=false;$revision=orderRev();
    try { orderRpc('ms2_update_order',$request); }
    catch(ModxMCPClientException $e) {
        $stale=strpos($e->getMessage(),'STALE_STATE')!==false;
    }
    orderCheck($stale,'stale native order update replay refused');
    orderCheck(orderRev()===$revision,'stale replay did not shift revision');
    $again=orderRpc('ms2_get_order',array('id'=>$id));
    orderCheck(($again['order_comment']??'')===$fixture.'_after',
        'stale replay did not overwrite order');
    echo "CAS_MS2_UPDATE_ORDER_MODX2_NATIVE_READBACK_STALE_OK\n";
} finally {
    // RU: Только одноразовый собственный заказ; удаление native processor.
    // EN: Test fixture cleanup through native miniShop2 processor in ephemeral DB.
    if($id>0) {
        try {
            $modx->runProcessor('mgr/orders/remove',array('id'=>$id),
                array('processors_path'=>$path.'processors/'));
        } catch(Throwable $ignored) {}
    }
}
echo 'CAS_MS2_ORDER_NATIVE_E2E_OK checks='.$checks.' platform=modx2'.PHP_EOL;
