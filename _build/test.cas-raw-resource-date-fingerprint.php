<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Отпечаток CAS зависит от сырых дат, а не форматирования Manager.
 * EN: CAS fingerprints use raw MODX dates, not Manager presentation formats.
 */
namespace ModxMcp\Tools {
    class ElementGetTool {
        public static $formattedDate='22/11/2026 06:41';
        public function execute($context,array $args){
            return array(
                'id'=>21,'pagetitle'=>'Disposable',
                'content'=>'ABC','published'=>1,
                'pub_date'=>'','unpub_date'=>'',
                'publishedon'=>self::$formattedDate,
            );
        }
    }
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/StateSupport.php';
}
namespace {
class ModxMCPClientException extends \RuntimeException {}
class CasRawDateModel {
    public $date='2026-11-22 06:41:00';
    public function get($field){
        if($field==='publishedon')return $this->date;
        return '';
    }
}
class CasRawDateModx {
    public $resource;
    public function __construct(){$this->resource=new CasRawDateModel();}
    public function getObject($class,$id){
        return $class==='modResource' && (int)$id===21?$this->resource:null;
    }
}
class CasRawDatePlatform {
    public function className($logicalName){
        return $logicalName==='resource'?'modResource':null;
    }
}
class CasRawDateContext {
    public $m;public $p;
    public function __construct(){
        $this->m=new CasRawDateModx();
        $this->p=new CasRawDatePlatform();
    }
    public function modx(){return $this->m;}
    public function platform(){return $this->p;}
}
$ctx=new CasRawDateContext();
$item=array('role'=>'target','type'=>'resource','id'=>21);
$fingerprintA=\ModxMcp\Tools\StateSupport::fingerprintReadItem($ctx,$item);
\ModxMcp\Tools\ElementGetTool::$formattedDate='2026-11-22 6:41 AM';
$fingerprintB=\ModxMcp\Tools\StateSupport::fingerprintReadItem($ctx,$item);
if(!hash_equals($fingerprintA,$fingerprintB))
    throw new \RuntimeException('MANAGER_DATE_FORMAT_CHANGED_FINGERPRINT');
$ctx->modx()->resource->date='2026-11-22 06:41:40';
$fingerprintC=\ModxMcp\Tools\StateSupport::fingerprintReadItem($ctx,$item);
if(hash_equals($fingerprintB,$fingerprintC))
    throw new \RuntimeException('SUBMINUTE_DATE_CHANGE_NOT_DETECTED');
$ctx->modx()->resource=null;
try{
    \ModxMcp\Tools\StateSupport::fingerprintReadItem($ctx,$item);
    throw new \RuntimeException('MISSING_RAW_RESOURCE_DID_NOT_FAIL_CLOSED');
}catch(ModxMCPClientException $e){
    if(strpos($e->getMessage(),'STALE_STATE')!==0)throw $e;
}
echo 'CAS_RAW_RESOURCE_DATE_FINGERPRINT_OK checks=3'.PHP_EOL;
}
