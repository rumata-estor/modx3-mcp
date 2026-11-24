<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Воспроизведение отказа старого анализа, проверка обновления только в CI.
 * EN: Reproduce legacy dependency failure and verify a disposable upgrade only.
 */
$config=(string)getenv('MODX_CONFIG_CORE');
$temp=(string)getenv('RUNNER_TEMP');
if(PHP_SAPI!=='cli' || getenv('MODXMCP_DISPOSABLE_CI')!=='1'
    || strpos($temp,'/home/runner/')!==0 || !is_file($config)
    || realpath($config)!==realpath($temp.'/modx-site/config.core.php'))exit(3);
require $config;
$is2=!is_file(MODX_CORE_PATH.'src/Revolution/modX.php');
if($is2){require_once MODX_CORE_PATH.'model/modx/modx.class.php';$modx=new modX();}
else{require_once MODX_CORE_PATH.'vendor/autoload.php';$modx=\MODX\Revolution\modX::getInstance();}
$modx->initialize('mgr');
$ns=$is2?'':'MODX\\Revolution\\';
$chunk=$ns.'modChunk';$setting=$ns.'modSystemSetting';
require_once MODX_CORE_PATH.'components/modxmcp/model/modxmcp.class.php';
$api=new modxMCP($modx);$checks=0;
function chk($ok,$label){global $checks;if(!$ok)throw new RuntimeException('UPGRADE_PARTIAL_ANALYSIS_FAILED: '.$label);$checks++;}
function rpc($action,array $args=array()){global $api;return $api->processRequest($action,'',$args);}
function guard($id=null){$selectors=$id===null?array():array(array('role'=>'target','type'=>'chunk','id'=>$id));$s=rpc('get_site_state',array('read_set'=>$selectors));return array('observed_site_revision'=>$s['site_revision'],'strict_site_revision'=>true,'read_set'=>$s['read_set']);}
function cfg($key){global $modx,$setting;$row=$modx->getObject($setting,array('key'=>$key));chk((bool)$row,'setting exists');return $row;}
function denied($action,array $args,$code=null){$before=rpc('get_site_state')['site_revision'];$denied=false;try{rpc($action,$args);}catch(ModxMCPClientException $e){$denied=$code===null||strpos($e->getMessage(),$code)!==false;}chk($denied,'refusal '.$action);chk(rpc('get_site_state')['site_revision']===$before,'revision unchanged '.$action);}
$manifest=$temp.'/partial-upgrade.json';$mode=$argv[1]??'';
if($mode==='before'){
    // Use native MODX objects only to reproduce old Manager-owned metadata.
    // All product mutations go through guarded MCP.
    $enabled=cfg('modxmcp.enabled');$enabled->set('value','1');chk($enabled->save(),'enable disposable endpoint');$modx->getCacheManager()->refresh();
    $name='CI_PartialUpgrade_'.bin2hex(random_bytes(5));
    $created=rpc('create_element',array('type'=>'chunk','name'=>$name,'content'=>'ORIGINAL','_runtime_preconditions'=>guard()));
    $id=(int)$created['id'];chk($id>0,'created via MCP');
    $path=$temp.'/unavailable-static-legacy.tpl';$secret='PRIVATE_DISPOSABLE_SENTINEL';file_put_contents($path,$secret);
    $bad=$modx->newObject($chunk);$bad->fromArray(array('name'=>$name.'_legacy','snippet'=>'DO_NOT_USE_DB_FALLBACK','static'=>1,'static_file'=>$path),'',true,true);chk($bad->save(),'legacy metadata');
    denied('find_usages',array('name'=>$name));
    denied('dependency_graph',array('format'=>'summary'));
    denied('delete_element',array('type'=>'chunk','id'=>$id,'dry_run'=>true,'_runtime_preconditions'=>guard($id)));
    $state=rpc('get_site_state');
    file_put_contents($manifest,json_encode(array('id'=>$id,'name'=>$name,'bad'=>(int)$bad->get('id'),'revision'=>$state['site_revision'],'token_hash'=>hash('sha256',(string)cfg('modxmcp.api_token')->get('value'))),JSON_THROW_ON_ERROR));
    echo "BETA5_EXISTING_SITE_FAILURE_REPRODUCED checks=$checks\n";exit(0);
}
chk($mode==='after','mode');$saved=json_decode(file_get_contents($manifest),true,512,JSON_THROW_ON_ERROR);$id=$saved['id'];$name=$saved['name'];$bad=$modx->getObject($chunk,$saved['bad']);
chk((bool)$bad,'legacy object survived upgrade');
chk(hash('sha256',(string)cfg('modxmcp.api_token')->get('value'))===$saved['token_hash'],'token preserved');
chk((string)cfg('modxmcp.enabled')->get('value')==='1','enabled state preserved');
chk(rpc('get_site_state')['site_revision']===$saved['revision'],'CAS revision preserved');
$read=rpc('get_element',array('type'=>'chunk','id'=>$id));chk(($read['snippet']??$read['content']??null)==='ORIGINAL','target preserved');
$usage=rpc('find_usages',array('name'=>$name));chk($usage['analysis_complete']===false && $usage['unavailable_count']===1,'partial usage result');
chk($usage['unavailable_elements'][0]===array('type'=>'chunk','id'=>$saved['bad'],'code'=>'STATIC_ROOT_UNAVAILABLE') || $usage['unavailable_elements'][0]===array('type'=>'chunk','id'=>$saved['bad'],'code'=>'STATIC_PATH_OUTSIDE_ALLOWED_ROOT'),'safe exact diagnostic');
$graph=rpc('dependency_graph',array('format'=>'summary'));chk(!$graph['analysis_complete'] && !$graph['stats']['orphans_verified'] && empty($graph['orphans']),'graph cannot certify orphans');
$preview=rpc('delete_element',array('type'=>'chunk','id'=>$id,'dry_run'=>true,'_runtime_preconditions'=>guard($id)));chk(!$preview['analysis_complete'] && $preview['warning'],'preview explicitly incomplete');
denied('delete_element',array('type'=>'chunk','id'=>$id,'_runtime_preconditions'=>guard($id)),'VALIDATION_ERROR');
denied('replace_across',array('find'=>'ORIGINAL','replacement'=>'UNSAFE','types'=>array('chunk'),'_runtime_preconditions'=>guard()));
$read=rpc('get_element',array('type'=>'chunk','id'=>$id));chk(($read['snippet']??$read['content']??null)==='ORIGINAL','refusals preserved target');
chk(file_get_contents($temp.'/unavailable-static-legacy.tpl')==='PRIVATE_DISPOSABLE_SENTINEL','outside bytes unchanged');
// Simulate administrator remediation of this one disposable legacy object.
$bad->set('static',0);$bad->set('static_file','');chk($bad->save()&&$bad->remove(),'legacy fixture removed by Manager');
$usage=rpc('find_usages',array('name'=>$name));chk($usage['analysis_complete']===true,'coverage restored');
$before=rpc('get_site_state')['site_revision'];$delete=array('type'=>'chunk','id'=>$id,'_runtime_preconditions'=>guard($id));
$preview=rpc('delete_element',$delete+array('dry_run'=>true));chk($preview['analysis_complete']===true && !$preview['warning'],'safe preview after remediation');
rpc('delete_element',$delete);chk(!$modx->getObject($chunk,$id),'guarded delete confirmed');chk(rpc('get_site_state')['site_revision']===$before+1,'one revision for deletion');
@unlink($temp.'/unavailable-static-legacy.tpl');
echo "BETA5_UPGRADE_PARTIAL_ANALYSIS_OK checks=$checks\n";
