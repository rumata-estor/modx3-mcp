<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Реальный lifecycle связей двух неопубликованных товаров miniShop2.
 * EN: Real pinned miniShop2 product-link lifecycle, MCP CAS/readback/replay.
 */
if (PHP_SAPI !== 'cli' || getenv('MODXMCP_DISPOSABLE_CI') !== '1'
    || strpos((string)getenv('MODX_CONFIG_CORE'), '/home/runner/') !== 0
    || !is_file((string)getenv('MODX_CONFIG_CORE'))
    || strpos((string)getenv('RUNNER_TEMP'), '/home/runner/') !== 0) {
    fwrite(STDERR, "Refusing miniShop2 product-link E2E outside disposable CI.\n");
    exit(3);
}
require getenv('MODX_CONFIG_CORE');
$is2=!is_file(MODX_CORE_PATH.'src/Revolution/modX.php');
if($is2){ require MODX_CORE_PATH.'model/modx/modx.class.php';$modx=new modX(); }
else{ require MODX_CORE_PATH.'vendor/autoload.php';$modx=\MODX\Revolution\modX::getInstance(); }
$modx->initialize('mgr');
$modx->setOption('modxmcp.disabled_groups','');
$path=MODX_CORE_PATH.'components/minishop2/';
if(!is_file($path.'model/minishop2/msproduct.class.php')
    || !is_file($path.'processors/mgr/product/productlink/create.class.php')) {
    throw new RuntimeException('Pinned miniShop2 product classes unavailable');
}
$admin=$modx->getObject($is2?'modUser':'\MODX\Revolution\modUser',1);
if(!$admin || (int)$admin->get('sudo')!==1)
    throw new RuntimeException('Disposable manager sudo unavailable');
$modx->user=$admin;
$modx->user->addSessionContext('mgr');
if(!$modx->hasPermission('msproduct_save')||!$modx->hasPermission('mssetting_save'))
    throw new RuntimeException('Native miniShop2 permissions missing');
if(!$modx->addPackage('minishop2',$path.'model/'))
    throw new RuntimeException('Could not load pinned miniShop2 package');
$manager=$modx->getManager();
foreach(array('msLink','msProductLink','msProductData','msVendor') as $class){
    if(!$manager->createObjectContainer($class))
        throw new RuntimeException('Disposable miniShop2 table unavailable '.$class);
}
require MODX_CORE_PATH.'components/modxmcp/model/modxmcp.class.php';
require __DIR__.'/test.cas-mcp-helper.php';
$api=new modxMCP($modx);
$checks=0;
function plRpc($action,array $args=array()){
    global $api;
    return disposableCasRpc($api,$action,$args);
}
function plCheck($ok,$label){
    global $checks;
    if(!$ok)throw new RuntimeException('MS2_PRODUCT_LINK_E2E_FAILED '.$label);
    ++$checks;
}
function plRev(){ $s=plRpc('get_site_state'); return (int)$s['site_revision']; }
function plGuard(){ return array('observed_site_revision'=>plRev(),'strict_site_revision'=>true,'read_set'=>array()); }
function plWrite($action,array $args){
    $before=plRev();
    $args['_runtime_preconditions']=plGuard();
    $out=plRpc($action,$args);
    plCheck(plRev()===$before+1,$action.' CAS revision once');
    return array($out,$args);
}
function plStale($action,array $request){
    $before=plRev();$denied=false;
    try { plRpc($action,$request); }
    catch(ModxMCPClientException $e){ $denied=strpos($e->getMessage(),'STALE_STATE')!==false; }
    plCheck($denied,$action.' rejects old CAS');
    plCheck(plRev()===$before,$action.' stale request no revision');
}
function plRows($link,$master,$slave){
    $data=plRpc('ms2_list_product_links',array('master'=>$master,'limit'=>0));
    return array_values(array_filter($data['results']??array(),
        function($r)use($link,$master,$slave){
            return is_array($r)
                &&(int)($r['link']??0)===$link
                &&(int)($r['master']??0)===$master
                &&(int)($r['slave']??0)===$slave;
        }));
}
$marker=bin2hex(random_bytes(5));
$linkId=0;$p1=0;$p2=0;$connected=false;
try {
    list($created,$old)=plWrite('ms2_create_link_type',array(
        'name'=>'MCP_disposable_relation_'.$marker,
        'type'=>'one_to_many','description'=>'test-owned link',
    ));
    $all=plRpc('ms2_list_link_types',array('query'=>'MCP_disposable_relation_'.$marker,'limit'=>0));
    foreach(($all['results']??array()) as $row){
        if(($row['name']??'')==='MCP_disposable_relation_'.$marker)$linkId=(int)$row['id'];
    }
    plCheck($linkId>0,'native link type created and independently readable');
    foreach(array('A','B') as $which){
        $resource=plRpc('create_element',array(
            'type'=>'resource','name'=>'MCP_CI_msProduct_'.$marker.'_'.$which,
            'class_key'=>'msProduct','alias'=>'mcp-msproduct-'.$marker.'-'.strtolower($which),
            'template'=>0,'parent'=>0,'published'=>0,'hidemenu'=>1,
            'content'=>'disposable product '.$which
        ));
        $id=(int)($resource['id']??0);
        plCheck($id>0,'resource ID returned for '.$which);
        $read=plRpc('get_element',array('type'=>'resource','id'=>$id));
        plCheck(($read['class_key']??'')==='msProduct','real product class '.$which);
        plCheck(empty($read['published']),'test product unpublished '.$which);
        if($which==='A')$p1=$id;else $p2=$id;
    }
    plCheck($p1!==$p2,'two independent test products');

    list($made,$old)=plWrite('ms2_create_product_link',array(
        'link'=>$linkId,'master'=>$p1,'slave'=>$p2,
    ));
    plCheck(count(plRows($linkId,$p1,$p2))===1,
        'created native product link visible via MCP');
    $connected=true;
    plStale('ms2_create_product_link',$old);
    plCheck(count(plRows($linkId,$p1,$p2))===1,
        'stale create did not duplicate product link');
    echo "CAS_MS2_CREATE_PRODUCT_LINK_NATIVE_READBACK_STALE_OK\n";

    list($removed,$old)=plWrite('ms2_delete_product_link',array(
        'link'=>$linkId,'master'=>$p1,'slave'=>$p2,
    ));
    plCheck(count(plRows($linkId,$p1,$p2))===0,
        'native product link deleted via MCP');
    $connected=false;
    plStale('ms2_delete_product_link',$old);
    plCheck(count(plRows($linkId,$p1,$p2))===0,
        'stale delete had no effect');
    echo "CAS_MS2_DELETE_PRODUCT_LINK_NATIVE_READBACK_STALE_OK\n";
} finally {
    // RU: Удаляем только фикстуры, созданные в disposable GitHub CI.
    // EN: Cleanup only test-owned fixtures via MCP in ephemeral MODX.
    if($connected && $linkId>0 && $p1>0 && $p2>0){
        try{plRpc('ms2_delete_product_link',array('link'=>$linkId,'master'=>$p1,'slave'=>$p2));}
        catch(Throwable $ignored){}
    }
    foreach(array($p2,$p1) as $id)if($id>0){
        try{plRpc('delete_element',array('type'=>'resource','id'=>$id,'dry_run'=>false));}
        catch(Throwable $ignored){}
    }
    if($linkId>0){
        try{plRpc('ms2_delete_link_type',array('id'=>$linkId));}
        catch(Throwable $ignored){}
    }
}
echo 'CAS_MS2_PRODUCT_LINK_NATIVE_E2E_OK checks='.$checks
    .' platform='.($is2?'modx2':'modx3').PHP_EOL;
