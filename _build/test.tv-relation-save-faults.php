<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Отрицательные тесты сохранения связей TV без записи в реальные сайты.
 * EN: TV relation persistence fault injection, no live CMS or database.
 */
class ModxMCPClientException extends \RuntimeException {}
require_once __DIR__.'/../core/components/modxmcp/src/Security/MutationOutcomeGuard.php';
require_once __DIR__.'/../core/components/modxmcp/src/Tools/ElementMutationSupport.php';
class TvRelationFakeObject {
    public $kind;
    public $fails;
    public function __construct($kind,$fails){$this->kind=$kind;$this->fails=$fails;}
    public function fromArray($data,$prefix='',$new=false,$raw=false){}
    public function set($name,$value){}
    public function save(){return $this->kind!==$this->fails;}
}
class TvRelationFakeModx {
    public $fails;
    public function __construct($fails){$this->fails=$fails;}
    public function getObject($class,$key) {
        if($class==='tv'&&$this->fails==='missing_tv')return null;
        if($class==='tv')return new TvRelationFakeObject('tv',$this->fails);
        return null;
    }
    public function newObject($class){return new TvRelationFakeObject($class,$this->fails);}
    public function removeCollection($class,$criteria){return $this->fails!=='remove';}
}
class TvRelationFakePlatform {
    public function className($kind){return $kind;}
}
class TvRelationFakeContext {
    private $modx;
    public function __construct($fails){$this->modx=new TvRelationFakeModx($fails);}
    public function modx(){return $this->modx;}
    public function platform(){return new TvRelationFakePlatform();}
}
$cases=array(
    array('missing_tv',array()),
    array('remove',array('templates'=>array(1))),
    array('template_var_template',array('templates'=>array(1))),
    array('tv',array('input_properties'=>array('a'=>'b'))),
    array('media_source_element',array('media_source'=>1)),
);
$checks=0;
foreach($cases as $case){
    $caught=null;
    try {
        \ModxMcp\Tools\ElementMutationSupport::handleTvRelations(
            new TvRelationFakeContext($case[0]),123,$case[1]
        );
    } catch (\Throwable $e) {$caught=$e;}
    if(!($caught instanceof ModxMCPClientException)
        || strpos($caught->getMessage(),'MUTATION_OUTCOME_UNCERTAIN:')!==0
        || $caught->getPrevious()!==null) {
        throw new \RuntimeException('TV_RELATION_SAVE_FAILURE_NOT_SAFE '.$case[0]);
    }
    $checks++;
}
\ModxMcp\Tools\ElementMutationSupport::handleTvRelations(
    new TvRelationFakeContext('ok'),123,array(
        'templates'=>array(1),'input_properties'=>array('a'=>'b'),'media_source'=>1
    )
);
$checks++;
echo 'TV_RELATIONS_FAIL_CLOSED_OK checks='.$checks.PHP_EOL;
