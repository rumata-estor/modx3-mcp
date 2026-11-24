<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Полный цикл транспортного пакета Beta 5 на одноразовом MODX.
 * EN: Full Beta 5 transport lifecycle on disposable MODX only.
 */
$config=(string)getenv('MODX_CONFIG_CORE');
$temp=(string)getenv('RUNNER_TEMP');
if(PHP_SAPI!=='cli' || getenv('MODXMCP_DISPOSABLE_CI')!=='1'
    || strpos($config,'/home/runner/')!==0 || !is_file($config)
    || strpos($temp,'/home/runner/')!==0
    || realpath($config)!==realpath($temp.'/modx-site/config.core.php')){
    fwrite(STDERR,"Refusing package lifecycle test outside disposable CI.\n");exit(3);
}
require $config;
$is2=!is_file(MODX_CORE_PATH.'src/Revolution/modX.php');
if($is2){require_once MODX_CORE_PATH.'model/modx/modx.class.php';$modx=new modX();}
else{require_once MODX_CORE_PATH.'vendor/autoload.php';$modx=\MODX\Revolution\modX::getInstance();}
$modx->initialize('mgr');
$ns=$is2?'':'MODX\\Revolution\\';
$doc=$ns.'modDocument';$res=$ns.'modResource';$chunk=$ns.'modChunk';
$setting=$ns.'modSystemSetting';$menu=$ns.'modMenu';$namespace=$ns.'modNamespace';
$package=$is2?'transport.modTransportPackage':$ns.'Transport\\modTransportPackage';
$prefix=$is2?'modx2mcp':'modx3mcp';
$beta=$prefix.'-1.2.8-beta5';$old=$prefix.'-1.2.7-pl';
$manifest=$temp.'/beta5-lifecycle-fixture.json';$mode=$argv[1]??'';
function lcCheck($ok,$label){if(!$ok)throw new RuntimeException('BETA5_LIFECYCLE_FAILED: '.$label);}
function lcRead($modx,$class,$criteria,array $fields){
    $obj=$modx->getObject($class,$criteria);
    lcCheck((bool)$obj,'missing unrelated MODX object: '.$class);
    $out=array();foreach($fields as $f)$out[$f]=$obj->get($f);
    return $out;
}
function lcObject($modx,$class,array $data){
    $obj=$modx->newObject($class);lcCheck((bool)$obj,'cannot construct fixture '.$class);
    $obj->fromArray($data,'',true,true);lcCheck($obj->save(),'fixture save failed '.$class);
    return $obj;
}
function lcTokenHash($modx,$setting){
    $o=$modx->getObject($setting,array('key'=>'modxmcp.api_token'));
    lcCheck($o && preg_match('/^[a-f0-9]{64}$/D',(string)$o->get('value')),'valid generated token');
    return hash('sha256',(string)$o->get('value'));
}
function lcGone($modx,$setting,$menu,$namespace,$package,$beta){
    lcCheck((int)$modx->getCount($setting,array('key:LIKE'=>'modxmcp.%'))===0,'MCP setting keys removed');
    lcCheck(!$modx->getObject($namespace,array('name'=>'modxmcp')),'namespace removed');
    foreach(array('modxmcp','modxmcp_graph') as $text)
        lcCheck(!$modx->getObject($menu,array('text'=>$text)),'menu removed');
    lcCheck(!file_exists(MODX_CORE_PATH.'components/modxmcp'),'component core removed');
    lcCheck(!file_exists(MODX_ASSETS_PATH.'components/modxmcp'),'component assets removed');
    lcCheck(!$modx->getObject($package,array('signature'=>$beta)),'Beta package record removed');
}
function lcGuard($api,$target=null){
    $selectors=$target===null?array():array($target);
    $s=$api->processRequest('get_site_state','',array('read_set'=>$selectors));
    lcCheck(!empty($s['cas_required']) && is_int($s['site_revision']) &&
        count($s['read_set'])===count($selectors),'native CAS read-set');
    if($target!==null)lcCheck(preg_match('/^[a-f0-9]{64}$/D',$s['read_set'][0]['fingerprint']),'full target hash');
    return array('observed_site_revision'=>$s['site_revision'],'strict_site_revision'=>true,'read_set'=>$s['read_set']);
}
function lcRpc($api,$action,array $data=array()){return $api->processRequest($action,'',$data);}
function lcDenied($api,$action,array $data,$code){
    $before=lcRpc($api,'get_site_state')['site_revision'];$denied=false;
    try{lcRpc($api,$action,$data);}catch(ModxMCPClientException $e){$denied=strpos($e->getMessage(),$code)!==false;}
    lcCheck($denied,'expected '.$code);
    lcCheck(lcRpc($api,'get_site_state')['site_revision']===$before,'refusal consumed no revision');
}
if($mode==='seed'){
    $tag=bin2hex(random_bytes(5));
    $r=lcObject($modx,$doc,array('pagetitle'=>'Lifecycle fixture '.$tag,'alias'=>'lifecycle-'.$tag,
        'context_key'=>'web','parent'=>0,'template'=>0,'published'=>0,'hidemenu'=>1,
        'content'=>'User resource '.$tag,'description'=>'Unrelated user data'));
    $c=lcObject($modx,$chunk,array('name'=>'Lifecycle_User_'.$tag,'snippet'=>'User chunk '.$tag));
    $k='lifecycle_user_'.$tag;
    lcObject($modx,$setting,array('key'=>$k,'value'=>'User setting '.$tag,'namespace'=>'core','xtype'=>'textfield'));
    $entries=array(
        array($res,(int)$modx->getOption('site_start',null,1),array('id','pagetitle','content','published','deleted','template')),
        array($res,(int)$r->get('id'),array('id','pagetitle','alias','content','published','deleted','template','hidemenu','description')),
        array($chunk,(int)$c->get('id'),array('id','name','snippet')),
        array($setting,array('key'=>$k),array('key','value','namespace'))
    );
    $snap=array();foreach($entries as $e)$snap[]=array('class'=>$e[0],'criteria'=>$e[1],'fields'=>$e[2],
        'state'=>lcRead($modx,$e[0],$e[1],$e[2]));
    file_put_contents($manifest,json_encode(array('objects'=>$snap,'tag'=>$tag),JSON_THROW_ON_ERROR));
    echo "BETA5_UNRELATED_SITE_DATA_SEEDED\n";exit(0);
}
$saved=json_decode(file_get_contents($manifest),true,512,JSON_THROW_ON_ERROR);
foreach($saved['objects'] as $e)lcCheck(lcRead($modx,$e['class'],$e['criteria'],$e['fields'])===$e['state'],
    'unrelated data changed: '.$e['class']);
if($mode==='old-record'){
    $p=$modx->getObject($package,array('signature'=>$old));
    $installed=$p?(string)$p->get('installed'):'';
    echo !$p?'absent':(($installed!=='' && $installed!=='0000-00-00 00:00:00' && $installed!=='0')?'installed':'uninstalled');
    echo PHP_EOL;exit(0);
}
if($mode==='reconcile-old-record'){
    lcGone($modx,$setting,$menu,$namespace,$package,$beta);
    $p=$modx->getObject($package,array('signature'=>$old));
    if(!$p){echo "BETA5_OLD_RECORD_ALREADY_ABSENT\n";exit(0);}
    // Disposable fixture only: the component and its settings are already absent.
    lcCheck($p->remove(),'old transport record removed through MODX API');
    lcGone($modx,$setting,$menu,$namespace,$package,$beta);
    echo "BETA5_OLD_RECORD_REMOVED_WITH_ABSENT_COMPONENT\n";exit(0);
}
if(in_array($mode,array('uninstalled','clean','final'),true)){
    lcGone($modx,$setting,$menu,$namespace,$package,$beta);
    if($mode!=='uninstalled')lcCheck(!$modx->getObject($package,array('signature'=>$old)),'previous package record removed');
    echo 'BETA5_'.strtoupper($mode).'_USER_DATA_PRESERVED_OK'.PHP_EOL;exit(0);
}
lcCheck(in_array($mode,array('installed','reinstalled'),true),'valid phase');
lcCheck((bool)$modx->getObject($package,array('signature'=>$beta)),'exact Beta package record');
require_once MODX_CORE_PATH.'components/modxmcp/model/modxmcp.class.php';
lcCheck(modxMCP::VERSION==='1.2.8','installed model version');
$api=new modxMCP($modx);
if($mode==='installed'){
    $saved['token_sha256']=lcTokenHash($modx,$setting);
    file_put_contents($manifest,json_encode($saved,JSON_THROW_ON_ERROR));
    echo "BETA5_UPGRADED_USER_DATA_PRESERVED_OK\n";exit(0);
}
lcCheck(!hash_equals($saved['token_sha256'],lcTokenHash($modx,$setting)),'fresh install generates a new token');
foreach(array('modxmcp.enabled','modxmcp.external_authorization_required') as $key){
    $o=$modx->getObject($setting,array('key'=>$key));
    lcCheck($o && (string)$o->get('value')==='0','fresh default '.$key);
}
echo "BETA5_FRESH_TOKEN_AND_DISABLED_DEFAULT_OK\n";
$name='MCP_Lifecycle_'.$saved['tag'];
$data=array('type'=>'chunk','name'=>$name,'content'=>'A');
lcDenied($api,'create_element',$data,'CAS_REQUIRED');
$guard=lcGuard($api);
$created=lcRpc($api,'create_element',$data+array('_runtime_preconditions'=>$guard+array('target_absent'=>array('type'=>'chunk','name'=>$name))));
$id=(int)($created['id']??0);lcCheck($id>0,'create returns ID');
$sel=array('role'=>'target','type'=>'chunk','id'=>$id);
$get=function()use($api,$id){return lcRpc($api,'get_element',array('type'=>'chunk','id'=>$id));};
$content=function($r){return (string)($r['snippet']??($r['content']??''));};
lcCheck($content($get())==='A','create readback');
$g=lcGuard($api,$sel);
$change=array('type'=>'chunk','id'=>$id,'content'=>'B','_runtime_preconditions'=>$g);
lcRpc($api,'update_element',$change);
lcCheck($content($get())==='B','update readback');
lcDenied($api,'update_element',$change,'STALE_STATE');
lcCheck($content($get())==='B','stale replay preserves data');
$managerGuard=lcGuard($api,$sel);
$row=$modx->getObject($chunk,$id);$row->set('snippet','MANAGER_EDIT');lcCheck($row->save(),'simulated Manager save');
lcCheck(lcRpc($api,'get_site_state')['site_revision']===$managerGuard['observed_site_revision'],'Manager edit independent of site revision');
lcDenied($api,'update_element',array('type'=>'chunk','id'=>$id,'content'=>'STALE','_runtime_preconditions'=>$managerGuard),'STALE_STATE');
lcCheck($content($get())==='MANAGER_EDIT','complete fingerprint preserves Manager data');
$delete=array('type'=>'chunk','id'=>$id,'_runtime_preconditions'=>lcGuard($api,$sel));
$before=lcRpc($api,'get_site_state')['site_revision'];
$preview=lcRpc($api,'delete_element',$delete+array('dry_run'=>true));
lcCheck(!empty($preview['dry_run']) && (int)$preview['would_delete']['id']===$id,'delete preview target');
lcCheck(lcRpc($api,'get_site_state')['site_revision']===$before,'preview consumed no revision');
lcRpc($api,'delete_element',$delete);
lcCheck((int)$modx->getCount($chunk,array('id'=>$id))===0,'isolated test object removed');
echo "BETA5_FRESH_CAS_CRUD_STALE_MANAGER_CONFLICT_DELETE_OK\n";
