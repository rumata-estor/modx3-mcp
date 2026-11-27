<?php
// Real MODX 2/3 regression. Refuse to run on a registered or production site.
if (PHP_SAPI !== 'cli' || getenv('MODXMCP_DISPOSABLE_CI') !== '1'
    || strpos((string)getenv('MODX_CONFIG_CORE'), '/home/runner/') !== 0
    || !is_file((string)getenv('MODX_CONFIG_CORE'))) {
    fwrite(STDERR,"Refusing resource context test outside disposable CI.\n"); exit(3);
}
require_once getenv('MODX_CONFIG_CORE');
$is2=!is_file(MODX_CORE_PATH.'src/Revolution/modX.php');
if($is2){require_once MODX_CORE_PATH.'model/modx/modx.class.php';$modx=new modX();}
else{require_once MODX_CORE_PATH.'vendor/autoload.php';$modx=\MODX\Revolution\modX::getInstance();}
$modx->initialize('mgr');
require_once MODX_CORE_PATH.'components/modxmcp/model/modxmcp.class.php';
require_once __DIR__.'/test.cas-mcp-helper.php';
$api=new modxMCP($modx); $checks=0;
function rcRpc($action,array $data=[]){global $api,$modx;$modx->setOption('modxmcp.disabled_groups','');return disposableCasRpc($api,$action,$data);}
function rcCheck($condition,$label){global $checks;if(!$condition)throw new RuntimeException('RESOURCE_CONTEXT_FAILED: '.$label);$checks++;}
function rcRead($id){return rcRpc('get_element',['type'=>'resource','id'=>$id]);}
function rcFields($row,$expected){foreach($expected as $k=>$v){rcCheck(array_key_exists($k,$row)&&(is_int($v)?(int)$row[$k]===$v:$row[$k]===$v),'readback '.$k);}}
$fixture=bin2hex(random_bytes(5));$ctx='mcpmulti'.$fixture;$title='MCP Context '.$fixture;
$ids=[];$createdContext=false;
try{
    rcCheck(!empty(rcRpc('get_site_state')['resource_context_fields_v1']),'server contract advertised');
    $overview=rcRpc('project_overview',['sections'=>['content_types']]);$textId=0;
    foreach($overview['content_types'] as $ct){if($ct['mime']==='text/plain'){$textId=(int)$ct['id'];break;}}
    rcCheck($textId>0,'plain text content type exists');
    rcRpc('create_context',['key'=>$ctx,'name'=>'Temporary multilingual context']);$createdContext=true;
    $common=['type'=>'resource','template'=>0,'published'=>0,'hidemenu'=>1,'content'=>'SOURCE '.$fixture];
    $src=rcRpc('create_element',$common+['name'=>$title,'context_key'=>'web','parent'=>0,'alias'=>'mcp-source-'.$fixture]);
    $sourceId=(int)$src['id'];$ids[]=$sourceId;rcCheck($sourceId>0,'source resource created');
    $sourceBefore=rcRead($sourceId);
    $parent=rcRpc('create_element',$common+['name'=>'Technical '.$fixture,'context_key'=>$ctx,'parent'=>0,'alias'=>'mcp-parent-'.$fixture,'isfolder'=>1]);
    $parentId=(int)$parent['id'];$ids[]=$parentId;rcCheck($parentId>0,'target parent created');
    $uri='mcp-llms-'.$fixture.'.txt';
    $payload=array_replace($common,['name'=>$title,'context_key'=>$ctx,'parent'=>$parentId,
        'alias'=>'mcp-llms-'.$fixture,'content'=>'EN '.$fixture,'content_type'=>$textId,
        'uri_override'=>1,'uri'=>$uri,'searchable'=>0,'cacheable'=>0,'richtext'=>0]);
    $created=rcRpc('create_element',$payload);
    $targetId=(int)$created['id'];$ids[]=$targetId;
    rcCheck($targetId>0&&$targetId!==$sourceId,'duplicate title created as new ID in target context');
    $expected=['context_key'=>$ctx,'parent'=>$parentId,'pagetitle'=>$title,'template'=>0,
        'published'=>0,'hidemenu'=>1,'content'=>'EN '.$fixture,'content_type'=>$textId,
        'uri_override'=>1,'uri'=>$uri,'searchable'=>0,'cacheable'=>0,'richtext'=>0];
    rcFields(rcRead($targetId),$expected);
    rcRpc('update_element',['type'=>'resource','id'=>$targetId,'content'=>'UPDATED '.$fixture]);
    $expected['content']='UPDATED '.$fixture;rcFields(rcRead($targetId),$expected);
    foreach([array_replace($payload,['name'=>'collision '.$fixture,'alias'=>'collision-'.$fixture]),
             array_replace($payload,['name'=>'bad parent '.$fixture,'parent'=>$sourceId,'uri'=>'bad-'.$uri]),
             array_replace($payload,['name'=>'bad URI '.$fixture,'uri'=>'../bad.txt'])] as $bad){
        $denied=false;try{rcRpc('create_element',$bad);}catch(ModxMCPClientException $e){$denied=true;}
        rcCheck($denied,'invalid target or duplicate URI rejected');
    }
    rcFields(rcRead($sourceId),['context_key'=>'web','content'=>$sourceBefore['content'],'uri'=>$sourceBefore['uri'],'pagetitle'=>$title]);
    echo 'RESOURCE_CONTEXT_LIVE_OK checks='.$checks.' platform='.($is2?'modx2':'modx3').PHP_EOL;
} finally {
    foreach(array_reverse($ids) as $id){if($id>0)rcRpc('delete_element',['type'=>'resource','id'=>$id,'dry_run'=>false]);}
    // The entire database is disposable. No destructive recycle-bin purge.
    // Context deletion may be refused while its trashed resources exist.
}
