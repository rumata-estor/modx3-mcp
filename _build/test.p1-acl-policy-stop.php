<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Fingerprint политики/шаблона и безопасный отказ без SQL.
 * EN: Native policy fingerprint and early refusal, no SQL fixture.
 */
namespace ModxMcp\Tools {
    class ProcessorMutationTool {
        public static function assertAclMutationFields($action,array $data){}
    }
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/StateSupport.php';
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/MutationPreflight.php';
}
namespace {
class ModxMCPClientException extends \RuntimeException {}
class P1Permission {
    public $value='view';
    public function toArray(){return array('name'=>'read','value'=>$this->value);}
}
class P1Policy {
    public $name='Policy';public $description='Initial';
    public function toArray(){
        return array('id'=>7,'name'=>$this->name,'description'=>$this->description,
            'template'=>15,'data'=>'{"view":true}');
    }
}
class P1Template {
    public $permission;
    public $description='Initial';
    public $permsAvailable=true;
    public function __construct(){$this->permission=new P1Permission();}
    public function toArray(){return array('id'=>15,'name'=>'Template',
        'description'=>$this->description,'template_group'=>1);}
    public function getMany($relation){
        if($relation!=='Permissions'||!$this->permsAvailable)return false;
        return array($this->permission);
    }
}
class P1Modx {
    public $policy;public $template;
    public function __construct(){$this->policy=new P1Policy();$this->template=new P1Template();}
    public function getObject($class,$id){
        return $class==='Policy'&&$id===7 ? $this->policy
            : ($class==='Template'&&$id===15 ? $this->template : null);
    }
}
class P1Platform {
    public function className($type){
        return $type==='access_policy'?'Policy':($type==='access_policy_template'?'Template':null);
    }
}
class P1Context {
    public $m;public $p;
    public function __construct(){$this->m=new P1Modx();$this->p=new P1Platform();}
    public function modx(){return $this->m;}
    public function platform(){return $this->p;}
}
function pcheck($cond,$label){if(!$cond)throw new \RuntimeException('P1_ACL_POLICY_FAIL '.$label);}
function pdeny($cb,$code){
    try {$cb();}
    catch(ModxMCPClientException $e){
        pcheck(strpos($e->getMessage(),$code.':')===0,$e->getMessage());
        return;
    }
    throw new \RuntimeException('P1_ACL_POLICY_FAIL expected '.$code);
}
$ctx=new P1Context();
$base=array('observed_site_revision'=>8,'strict_site_revision'=>true,'read_set'=>array());
$policySelector=array('role'=>'target','type'=>'access_policy','id'=>7);
$templateSelector=array('role'=>'target','type'=>'access_policy_template','id'=>15);
$policyHash=\ModxMcp\Tools\StateSupport::fingerprintReadItem($ctx,$policySelector);
$templateHash=\ModxMcp\Tools\StateSupport::fingerprintReadItem($ctx,$templateSelector);
pcheck(strlen($policyHash)===64 && strlen($templateHash)===64,'fingerprint lengths');
$ctx->modx()->policy->description='Outside Manager edit';
pcheck(\ModxMcp\Tools\StateSupport::fingerprintReadItem($ctx,$policySelector)!==$policyHash,
    'out of band policy edit detected');
$ctx->modx()->template->permission->value='edit';
pcheck(\ModxMcp\Tools\StateSupport::fingerprintReadItem($ctx,$templateSelector)!==$templateHash,
    'out of band policy template permission edit detected');
pdeny(function()use($ctx,$base){
    \ModxMcp\Tools\MutationPreflight::assertAllowed(
        $ctx,'update_access_policy',array('id'=>7),$base
    );
},'CAS_REQUIRED');
$valid=$base;
$valid['read_set']=array($policySelector+array(
    'fingerprint'=>\ModxMcp\Tools\StateSupport::fingerprintReadItem($ctx,$policySelector)));
\ModxMcp\Tools\MutationPreflight::assertAllowed(
    $ctx,'update_access_policy',array('id'=>7),$valid);
pdeny(function()use($ctx,$valid){
    \ModxMcp\Tools\MutationPreflight::assertAllowed(
        $ctx,'delete_access_policy',array('id'=>8),$valid);
},'CAS_REQUIRED');
$ctx->modx()->template->permsAvailable=false;
pdeny(function()use($ctx,$templateSelector){
    \ModxMcp\Tools\StateSupport::fingerprintReadItem($ctx,$templateSelector);
},'MANUAL_REVIEW_REQUIRED');
echo 'P1_ACL_POLICY_NATIVE_STOP_OK checks=7'.PHP_EOL;
}
