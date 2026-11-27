<?php
// Offline contract regression; no site, database, credentials or filesystem writes.
class ModxMCPClientException extends RuntimeException {}
require __DIR__.'/../core/components/modxmcp/src/Tools/ElementMutationSupport.php';
use ModxMcp\Tools\ElementMutationSupport as Support;
class Row {
    private $fields;
    function __construct($fields) {$this->fields=$fields;}
    function get($key){return $this->fields[$key]??null;}
}
class Model {
    public $duplicate=false;
    function getObject($class,$key) {
        if ($class==='context') return in_array($key,['web','eng'],true)?new Row([]):null;
        if ($class==='content_type') return $key===3?new Row([]):null;
        if ($class==='resource' && $key===84) return new Row(['id'=>84,'context_key'=>'eng','deleted'=>0]);
        if ($class==='resource' && $key===4) return new Row(['id'=>4,'context_key'=>'web','deleted'=>0]);
        if ($class==='resource' && is_array($key) && $this->duplicate) return new Row(['id'=>87]);
        return null;
    }
}
class Context {
    public $model;
    function __construct(){$this->model=new Model();}
    function modx(){return $this->model;}
    function platform(){return $this;}
    function className($name){return $name;}
}
$c=new Context();$checks=0;
function check($ok,$message){global $checks;if(!$ok)throw new RuntimeException($message);$checks++;}
function denied($fn,$label){try{$fn();}catch(ModxMCPClientException $e){check(true,$label);return;}throw new RuntimeException('Expected refusal: '.$label);}
$p=['type'=>'resource','name'=>'llms.txt','context_key'=>'eng','parent'=>84,'template'=>0,'published'=>0,'hidemenu'=>1,'searchable'=>0,'cacheable'=>0,'richtext'=>0,'uri_override'=>1,'uri'=>'llms.txt','content_type'=>3];
$prepared=Support::prepare($c,'resource',$p,'create_element');
check(!isset($prepared['id']),'create must not resolve existing resource by name');
check($prepared['pagetitle']==='llms.txt','title preserved');
Support::validateResourceFields($c,$prepared,$prepared);
$filtered=Support::filterData('resource',$prepared);
foreach(['searchable','cacheable','richtext','uri_override','uri','content_type','context_key','parent'] as $k)check($filtered[$k]===$p[$k],$k.' preserved');
foreach([['parent'=>4],['context_key'=>'unknown'],['context_key'=>'mgr'],['content_type'=>99],['searchable'=>2],['uri_override'=>0],['uri'=>'../llms.txt'],['uri'=>'/llms.txt'],['uri'=>'https://example.test/x'],['uri'=>'a//b'],['uri'=>'a%2fb'],['uri'=>"a\\b"],['uri'=>'a?b']] as $bad){
 $x=array_replace($prepared,$bad);denied(function()use($c,$x){Support::validateResourceFields($c,$x,$x);},json_encode($bad));
}
denied(function()use($c,$p){Support::prepare($c,'resource',$p+['id'=>81],'create_element');},'cannot supply existing ID');
$c->model->duplicate=true;
denied(function()use($c,$prepared){Support::validateResourceFields($c,$prepared,$prepared);},'URI collision in destination');
$updated=$prepared+['id'=>87];Support::validateResourceFields($c,['uri'=>'llms.txt'],$updated);
check(true,'own URI allowed on update');
echo "RESOURCE_CONTEXT_FIELDS_OK checks=$checks\n";
