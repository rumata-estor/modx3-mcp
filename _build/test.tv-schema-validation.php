<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Проверка полей TV и связей до записи, для MODX 2 и 3.
 * EN: Validate TV fields and relationships before writes, for MODX 2 and 3.
 */
class ModxMCPClientException extends Exception {}
require __DIR__.'/../core/components/modxmcp/src/Autoloader.php';
\ModxMcp\Autoloader::register(__DIR__.'/../core/components/modxmcp/src');
use ModxMcp\Tools\MutationPreflight;
use ModxMcp\Tools\ElementMutationSupport;
class TvSchemaModx {
    public function getObject($class,$id){return $id===1||$id===2?new stdClass():null;}
}
class TvSchemaContext {
    private $platform;
    public function __construct($p){$this->platform=$p;}
    public function modx(){return new TvSchemaModx();}
    public function platform(){return $this->platform;}
}
$bad=array(
    array('caption'=>array()),array('description'=>false),array('elements'=>array('a')),
    array('default_text'=>null),array('display'=>2),array('field_type'=>''),
    array('rank'=>-1),array('rank'=>1.5),array('rank'=>2147483648),array('rank'=>false),
    array('templates'=>null),array('templates'=>'1'),array('templates'=>array(0)),
    array('templates'=>array(1,1)),array('templates'=>array(1,'1')),
    array('templates'=>array(1.2)),array('templates'=>array('a'=>1)),
    array('templates'=>array(999)),array('input_properties'=>'invalid')
);
$checks=0;
foreach(array(new \ModxMcp\Platform\Modx2Platform(),new \ModxMcp\Platform\Modx3Platform()) as $p){
    $context=new TvSchemaContext($p);
    foreach(array('create_element','update_element') as $action){
        foreach($bad as $data){
            $data['type']='tv';
            foreach(array('preflight','prepare') as $phase){
                try{
                    if($phase==='preflight')MutationPreflight::assertAllowed($context,$action,$data);
                    else ElementMutationSupport::prepare($context,'tv',$data,$action);
                    throw new RuntimeException('Malformed TV accepted');
                }catch(ModxMCPClientException $e){
                    if(strpos($e->getMessage(),'VALIDATION_ERROR:')!==0)throw $e;
                }
                $checks++;
            }
        }
        $data=array('type'=>'tv','field_type'=>'checkbox','description'=>'Описание','rank'=>'7','elements'=>'Да==1||Нет==0','default_text'=>'0','display'=>'default','templates'=>array('1',2));
        MutationPreflight::assertAllowed($context,$action,$data);
        $out=ElementMutationSupport::prepare($context,'tv',$data,$action);
        if($out['type']!=='checkbox'||$out['rank']!==7||$out['templates']!==array(1,2)||$out['elements']!==$data['elements'])throw new RuntimeException('Valid fields changed');
        $checks++;
        foreach(array(array(),array('templates'=>array())) as $partial){
            $out=ElementMutationSupport::validateTvFields($context,$partial);
            if($out!==$partial)throw new RuntimeException('Omitted/empty templates changed');
            $checks++;
        }
    }
}
echo 'TV_SCHEMA_VALIDATION_OK checks='.$checks.PHP_EOL;
