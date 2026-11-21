<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Неполная установка/удаление пакета не даёт ложного успеха или утечки.
 * EN: Incomplete package mutations must not claim success or leak secrets.
 */
namespace ModxMcp\Tools {
    interface ToolInterface {
        public function name();public function group();public function isMutation();
        public function supports($context);public function execute($context,array $data);
    }
    class AuditSupport {
        public static $count=0;
        public static function log($context,$name,$type,$data){self::$count++;}
    }
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/ProcessorSupport.php';
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/PackageSupport.php';
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/PackageInstallTool.php';
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/PackageUninstallTool.php';
}
namespace {
class ModxMCPClientException extends \RuntimeException {}
class PackageResponse {
    private $stage;
    public function __construct($stage){$this->stage=$stage;}
    public function isError(){return $this->stage==='install'||$this->stage==='uninstall';}
    public function getMessage(){return 'SECRET_TOKEN_SHOULD_NOT_LEAK';}
    public function getObject(){return array('signature'=>'fixture-1.0.0-pl');}
    public function getResponse(){
        if($this->stage==='list')return array('results'=>array(
            array('name'=>'fixture','location'=>'https://example.invalid/pkg',
                'signature'=>'fixture-1.0.0-pl')
        ));
        return array('success'=>false,'message'=>'SECRET_TOKEN_SHOULD_NOT_LEAK');
    }
}
class PackagePlatform {
    public $calls=array();
    public function className($name){return $name;}
    public function runProcessor($modx,$processor,$data){
        $this->calls[]=$processor;
        if(strpos($processor,'rest/getlist')!==false)return new PackageResponse('list');
        if(strpos($processor,'rest/download')!==false)return new PackageResponse('download');
        if($processor==='workspace/packages/install')return new PackageResponse('install');
        if($processor==='workspace/packages/uninstall')return new PackageResponse('uninstall');
        throw new \RuntimeException('UNEXPECTED_PROCESSOR: '.$processor);
    }
}
class PackageModx {
    public function getObject($class,$criteria){return false;}
}
class PackageContext {
    public $modx;public $platform;
    public function __construct(){$this->modx=new PackageModx();$this->platform=new PackagePlatform();}
    public function modx(){return $this->modx;}
    public function platform(){return $this->platform;}
}
foreach(array(
    array(new \ModxMcp\Tools\PackageInstallTool(),
        array('package'=>'fixture','provider'=>1),3),
    array(new \ModxMcp\Tools\PackageUninstallTool(),
        array('signature'=>'fixture-1.0.0-pl'),1)
) as $case){
    $ctx=new PackageContext();
    try{
        $case[0]->execute($ctx,$case[1]);
        throw new \RuntimeException('PACKAGE_UNCERTAIN_OPERATION_CLAIMED_SUCCESS');
    }catch(ModxMCPClientException $e){
        if(strpos($e->getMessage(),'MUTATION_OUTCOME_UNCERTAIN')!==0
            || strpos($e->getMessage(),'SECRET_TOKEN_SHOULD_NOT_LEAK')!==false)
            throw new \RuntimeException('PACKAGE_FAILED_TO_HIDE_UNCERTAINTY');
    }
    if(count($ctx->platform->calls)!==$case[2])
        throw new \RuntimeException('PACKAGE_WRONG_FAULT_STAGE');
}
if(\ModxMcp\Tools\AuditSupport::$count!==0)
    throw new \RuntimeException('PACKAGE_AUDITED_FAILED_MUTATION');
echo 'PACKAGE_MUTATION_UNCERTAIN_RESULT_OK checks=2'.PHP_EOL;
}
