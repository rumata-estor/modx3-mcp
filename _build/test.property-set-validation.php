<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Регрессии формы Property Set, отказ до записи и сохранение данных.
 * EN: Property-set input regressions, denial before writes and data preservation.
 */
class ModxMCPClientException extends Exception {}
require __DIR__.'/../core/components/modxmcp/src/Autoloader.php';
\ModxMcp\Autoloader::register(__DIR__.'/../core/components/modxmcp/src');
use ModxMcp\Tools\PropertySetValidation;
use ModxMcp\Tools\MutationPreflight;
use ModxMcp\Tools\PropertySetCreateTool;
use ModxMcp\Tools\PropertySetUpdateTool;
class PSRow {
    public $fields = array('id'=>1, 'name'=>'existing','properties'=>array('old'=>array('name'=>'old','value'=>'keep')));
    public $writes=0;
    public function set($key,$value){$this->writes++;$this->fields[$key]=$value;}
    public function get($key){return $this->fields[$key];}
    public function save(){return true;}
}
class PSModx {
    public $row; public $lookups=0;
    public function __construct(){$this->row=new PSRow();}
    public function getObject($class,$id){$this->lookups++;return $this->row;}
    public function newObject($class){$this->lookups++;return $this->row;}
    public function getCacheManager(){return null;}
    public function getOption($key,$options=null,$default=null){return $key==='modxmcp.audit_log'?false:$default;}
}
class PSContext {
    public $site; public $adapter;
    public function __construct($adapter){$this->site=new PSModx();$this->adapter=$adapter;}
    public function modx(){return $this->site;}
    public function platform(){return $this->adapter;}
}
function psAssert($ok,$message){if(!$ok)throw new RuntimeException($message);}
$bad=array(
    'text',null,false,12,array(array('value'=>'list')),
    array(''=>array('value'=>1)),array('  '=>array('value'=>1)),array('x'=>true),
    array('x'=>array('type'=>'textfield')),array('x'=>array('value'=>null)),
    array('x'=>array('value'=>array(1))),array('x'=>array('value'=>1,'unknown'=>1)),
    array('x'=>array('name'=>'other','value'=>1)),array('x'=>array('value'=>1,'type'=>'')),
    array('x'=>array('value'=>1,'type'=>null)),array('x'=>array('value'=>1,'desc'=>2)),
    array('x'=>array('value'=>1,'lexicon'=>false)),array('x'=>array('value'=>1,'options'=>'bad')),
    array('x'=>array('value'=>1,'options'=>array('a'=>array('value'=>1)))),
    array('x'=>array('value'=>1,'options'=>array(array('text'=>'A')))),
    array('x'=>array('value'=>1,'options'=>array(array('value'=>array(1))))),
    array('x'=>array('value'=>1,'options'=>array(array('value'=>1,'text'=>false)))),
    array('x'=>array('value'=>INF))
);
$valid=array('параметр'=>array('value'=>'строка','desc'=>'описание'),
    'limit'=>array('type'=>'numberfield','value'=>0),
    'visible'=>array('type'=>'combo-boolean','value'=>0),
    'custom'=>array('type'=>'custom-xtype','value'=>false),
    'mode'=>array('type'=>'list','value'=>'0','options'=>array(array('text'=>'Zero','value'=>'0'))));
$checks=0;
foreach(array(new \ModxMcp\Platform\Modx2Platform(),new \ModxMcp\Platform\Modx3Platform()) as $platform){
    $context=new PSContext($platform);
    $before=$context->site->row->fields;
    foreach(array(new PropertySetCreateTool(),new PropertySetUpdateTool()) as $tool){
        foreach($bad as $value){
            $data=array('id'=>1,'name'=>'changed','properties'=>$value);
            foreach(array('preflight','execute') as $phase){
                try{
                    if($phase==='preflight')MutationPreflight::assertAllowed($context,$tool->name(),$data);
                    else $tool->execute($context,$data);
                    throw new RuntimeException('Invalid payload accepted');
                }catch(ModxMCPClientException $e){
                    psAssert(strpos($e->getMessage(),'VALIDATION_ERROR:')===0,'Unexpected refusal');
                }
                psAssert($context->site->row->fields===$before && $context->site->row->writes===0 && $context->site->lookups===0,'Invalid data touched row');
                $checks++;
            }
        }
    }
    $create=new PropertySetCreateTool();
    $update=new PropertySetUpdateTool();
    $create->execute($context,array('name'=>'new','properties'=>$valid));
    $stored=$context->site->row->fields['properties'];
    psAssert($stored['параметр']['name']==='параметр' && $stored['параметр']['type']==='textfield' && $stored['visible']['value']===false && $stored['limit']['value']===0 && $stored['mode']['options'][0]['value']==='0','Normalization lost data');$checks++;
    $update->execute($context,array('id'=>1,'description'=>'metadata'));
    psAssert($stored===$context->site->row->fields['properties'],'Omitted properties changed');$checks++;
    $readback=$stored;
    $readback['mode']['desc_trans']='Translated';
    $readback['mode']['options'][0]['name']='Translated label';
    $update->execute($context,array('id'=>1,'properties'=>$readback));
    psAssert($stored===$context->site->row->fields['properties'],'Readback roundtrip changed properties');$checks++;
    $update->execute($context,array('id'=>1,'properties'=>array('replacement'=>array('value'=>''))));
    psAssert(array_keys($context->site->row->fields['properties'])===array('replacement'),'Replacement not exact');$checks++;
    $update->execute($context,array('id'=>1,'properties'=>array()));
    psAssert($context->site->row->fields['properties']===array(),'Explicit clear failed');$checks++;
}
echo 'PROPERTY_SET_VALIDATION_OK checks='.$checks.PHP_EOL;
