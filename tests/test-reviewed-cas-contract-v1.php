<?php
/** Source contract for the ordinary MODX CAS writepoint, no DB or HTTP. */
require_once __DIR__.'/../core/components/modxmcp/endpoint/reviewed-cas-processor-v1.php';
$parse=new ReflectionMethod('ModxMcpReviewedCasV1','parsed');
$parse->setAccessible(true);
$before=new ReflectionMethod('ModxMcpReviewedCasV1','validateBefore');
$before->setAccessible(true);
$after=new ReflectionMethod('ModxMcpReviewedCasV1','checkAfter');
$after->setAccessible(true);
$encode=function($action,$type,array $data) {
    return json_encode(array('action'=>$action,'type'=>$type,'data'=>$data),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES |
        JSON_UNESCAPED_LINE_TERMINATORS);
};
$deny=function($fn,$label) {
    try { $fn(); } catch (RuntimeException $e) { return; }
    throw new RuntimeException('CAS accepted unsafe request: '.$label);
};
$create=$encode('create_element','resource',array(
  'pagetitle'=>'Услуги','content'=>'<main>Услуги</main>',
  'published'=>0,'template'=>3,'_cas_expected'=>array('absent'=>true)));
$result=$parse->invoke(null,$create);
if ($result[0]!=='create_element' || $result[1]!=='resource' ||
    $result[3]!==array('absent'=>true)) { throw new RuntimeException('Create contract'); }
$createTv=$encode('create_element','tv',array(
    'name'=>'subtitle','type'=>'text','templates'=>array(3),
    '_cas_expected'=>array('absent'=>true)));
$parse->invoke(null,$createTv);
$createTemplate=$encode('create_element','template',array(
    'templatename'=>'Landing','content'=>'[[*content]]',
    '_cas_expected'=>array('absent'=>true)));
$parse->invoke(null,$createTemplate);
$edit=$encode('update_element','resource',array('id'=>57,'content'=>'Новое',
    '_cas_expected'=>array('fields'=>array('content_sha256'=>hash('sha256','Старое')))));
$r=$parse->invoke(null,$edit);
if ($r[3]['fields']['content_sha256']!==hash('sha256','Старое')) {
    throw new RuntimeException('Signed content baseline missing');
}
$before->invoke(null,array('id'=>57,'content'=>'Старое'),$r[3]['fields']);
$deny(function() use ($before,$r) {
    $before->invoke(null,array('id'=>57,'content'=>'Повреждено'),$r[3]['fields']);
},'stale resource');
$after->invoke(null,array('id'=>57,'content'=>'Новое'),array('content'=>'Новое'));
$deny(function() use ($after) {
    $after->invoke(null,array('id'=>57,'content'=>'Не то'),array('content'=>'Новое'));
},'unverified postcondition');
$tv=$encode('update_resource_tvs','resource',array(
   'resource_id'=>57,'tvs'=>array('subtitle'=>'New'),
   '_cas_expected'=>array('tvs'=>array('subtitle'=>'Old'))));
$parse->invoke(null,$tv);
$deny(function() use ($parse,$encode) {
    $parse->invoke(null,$encode('create_element','resource',array(
       'pagetitle'=>'Surprise','content'=>'X','_cas_expected'=>array('absent'=>true))));
},'create without explicit publication');
$deny(function() use ($parse,$encode) {
    $parse->invoke(null,$encode('update_element','resource',array(
       'id'=>57,'content'=>'New','_cas_expected'=>array('fields'=>array('alias'=>'old')))));
},'unprotected update field');
$deny(function() use ($parse,$encode) {
    $parse->invoke(null,$encode('update_element','resource',array(
       'id'=>57,'content'=>'New')));
},'update without baseline');
$deny(function() use ($parse,$encode) {
    $parse->invoke(null,$encode('create_element','plugin',array(
       'name'=>'RCE','content'=>'<?php ... ?>','_cas_expected'=>array('absent'=>true))));
},'PHP plugin');
$deny(function() use ($parse,$encode) {
    $parse->invoke(null,$encode('create_element','snippet',array(
       'name'=>'PHP','snippet'=>'return 1;','_cas_expected'=>array('absent'=>true))));
},'unisolated executable snippet');
$deny(function() use ($parse,$create) {
    $parse->invoke(null,str_replace('"published":0','"published":1',$create).' ');
},'noncanonical JSON');
$deny(function() use ($parse) {
    $parse->invoke(null,
      '{"action":"update_element","type":"resource","data":{"id":57,"id":58,'.
      '"content":"X","_cas_expected":{"fields":{"content":"old"}}}}');
},'duplicate JSON key');
$deny(function() use ($parse,$encode) {
    $parse->invoke(null,$encode('update_resource_tvs','resource',array(
       'resource_id'=>57,'tvs'=>array('subtitle'=>'new'),
       '_cas_expected'=>array('tvs'=>array('another'=>'old')))));
},'extra TV name');

// New test-only resource lifecycle contract: searchable and exact soft delete.
$searchableCreate=$encode('create_element','resource',array(
    'pagetitle'=>'Тест Site Agent — contract','content'=>'safe test',
    'published'=>false,'hidemenu'=>true,'searchable'=>false,
    'parent'=>0,'context_key'=>'web','isfolder'=>false,
    '_cas_expected'=>array('absent'=>true)));
$searchableParsed=$parse->invoke(null,$searchableCreate);
if ($searchableParsed[2]['searchable']!==false) {
    throw new RuntimeException('Searchable false was discarded');
}
$deny(function() use ($parse,$encode) {
    $parse->invoke(null,$encode('create_element','resource',array(
        'pagetitle'=>'Тест Site Agent — invalid','content'=>'',
        'published'=>0,'searchable'=>'false',
        '_cas_expected'=>array('absent'=>true))));
},'searchable string');
$deletionBaseline=array(
    'pagetitle'=>'Тест Site Agent — contract','parent'=>0,
    'context_key'=>'web','published'=>false,'deleted'=>false,
    'isfolder'=>false,'searchable'=>false,'hidemenu'=>true);
$deleteBody=$encode('delete_element','resource',array(
    'id'=>101,'_cas_expected'=>array('fields'=>$deletionBaseline)));
$deleteParsed=$parse->invoke(null,$deleteBody);
if ($deleteParsed[0]!=='delete_element' ||
    $deleteParsed[1]!=='resource' ||
    $deleteParsed[3]['fields']!==$deletionBaseline) {
    throw new RuntimeException('Exact soft deletion contract failed');
}
$deny(function() use ($parse,$encode,$deletionBaseline) {
    $bad=$deletionBaseline; $bad['published']=true;
    $parse->invoke(null,$encode('delete_element','resource',array(
        'id'=>101,'_cas_expected'=>array('fields'=>$bad))));
},'published resource deletion');
$deny(function() use ($parse,$encode,$deletionBaseline) {
    $bad=$deletionBaseline; $bad['pagetitle']='Обычная страница';
    $parse->invoke(null,$encode('delete_element','resource',array(
        'id'=>101,'_cas_expected'=>array('fields'=>$bad))));
},'non-test resource deletion');
$deny(function() use ($parse,$encode,$deletionBaseline) {
    $bad=$deletionBaseline; unset($bad['hidemenu']);
    $parse->invoke(null,$encode('delete_element','resource',array(
        'id'=>101,'_cas_expected'=>array('fields'=>$bad))));
},'missing deletion baseline');
$deny(function() use ($parse,$encode,$deletionBaseline) {
    $parse->invoke(null,$encode('delete_element','resource',array(
        'id'=>101,'force'=>true,
        '_cas_expected'=>array('fields'=>$deletionBaseline))));
},'unexpected destructive option');
echo "REVIEWED_RESOURCE_LIFECYCLE_CONTRACT_PASS\\n";

echo "REVIEWED_MODX_CAS_SHAPE_AND_STALE_STATE_PASS\n";
