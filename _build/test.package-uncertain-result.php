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
class PackageSearchResponse {
    private $items;
    public function __construct($items){$this->items=$items;}
    public function isError(){return false;}
    public function getResponse(){return array('results'=>$this->items);}
}
class PackageDownloadResponse {
    private $signature;
    public function __construct($signature){$this->signature=$signature;}
    public function isError(){return false;}
    public function getObject(){return array('signature'=>$this->signature);}
}
class PackagePlatform {
    public $calls=array();
    public $throwStage='';
    public $noResponseStage='';
    public $providerResults=null;
    public $downloadSignature='fixture-1.0.0-pl';
    public function className($name){return $name;}
    public function runProcessor($modx,$processor,$data){
        $this->calls[]=$processor;
        if($this->throwStage!=='' && $processor===$this->throwStage)
            throw new \RuntimeException('SECRET_TOKEN_FROM_PACKAGE_EXCEPTION');
        if($this->noResponseStage!=='' && $processor===$this->noResponseStage)
            return false;
        if(strpos($processor,'rest/getlist')!==false) {
            if($this->providerResults!==null)return new PackageSearchResponse($this->providerResults);
            return new PackageResponse('list');
        }
        if(strpos($processor,'rest/download')!==false)
            return new PackageDownloadResponse($this->downloadSignature);
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
// RU: Исключение после скачивания / во время установки или удаления не
// RU: должно выглядеть как чистый отказ или раскрывать секреты процессора.
// EN: Exceptions during transport download/install/uninstall may occur
// EN: after external effects and must never expose raw error credentials.
foreach(array(
    array('workspace/packages/rest/download',
        new \ModxMcp\Tools\PackageInstallTool(),array('package'=>'fixture','provider'=>1),2),
    array('workspace/packages/install',
        new \ModxMcp\Tools\PackageInstallTool(),array('package'=>'fixture','provider'=>1),3),
    array('workspace/packages/uninstall',
        new \ModxMcp\Tools\PackageUninstallTool(),array('signature'=>'fixture-1.0.0-pl'),1),
) as $case){
    $ctx=new PackageContext();
    $ctx->platform->throwStage=$case[0];
    $uncertain=false;
    try{$case[1]->execute($ctx,$case[2]);}
    catch(ModxMCPClientException $e){
        $uncertain=strpos($e->getMessage(),'MUTATION_OUTCOME_UNCERTAIN:')===0
            && strpos($e->getMessage(),'SECRET_TOKEN')===false;
    }
    if(!$uncertain || count($ctx->platform->calls)!==$case[3])
        throw new \RuntimeException('PACKAGE_THROW_NOT_UNCERTAIN_OR_REDACTED');
}
// RU: Поиск провайдера может вернуть похожее имя/чужую подпись.
// EN: Fuzzy or misleading provider results must not select another package.
foreach(array(
    array('fuzzy',array(array('name'=>'other','signature'=>'other-1.0.0-pl',
        'location'=>'https://example.invalid/other')), ''),
    array('wrong-target',array(array('name'=>'other',
        'signature'=>'fixture-1.0.0-pl',
        'location'=>'https://example.invalid/other')), 'fixture-1.0.0-pl'),
) as $case){
    $ctx=new PackageContext();
    $ctx->platform->providerResults=$case[1];
    $denied=false;
    try{(new \ModxMcp\Tools\PackageInstallTool())->execute($ctx,
        array('package'=>'fixture','provider'=>1,'target_signature'=>$case[2]));}
    catch(ModxMCPClientException $e){$denied=true;}
    if(!$denied || $ctx->platform->calls!==array('workspace/packages/rest/getlist'))
        throw new \RuntimeException('WRONG_PACKAGE_PROVIDER_RESULT_DISPATCHED_'.$case[0]);
}
// RU: Подмена идентификатора уже скачанного архива — состояние неопределено,
// RU: но установщик ни при каких обстоятельствах не вызывается.
// EN: Downloaded signature substitution prevents install and needs readback.
$ctx=new PackageContext();
$ctx->platform->downloadSignature='other-2.0.0-pl';
$uncertain=false;
try{(new \ModxMcp\Tools\PackageInstallTool())->execute($ctx,
    array('package'=>'fixture','provider'=>1));}
catch(ModxMCPClientException $e){
    $uncertain=strpos($e->getMessage(),'MUTATION_OUTCOME_UNCERTAIN:')===0;
}
if(!$uncertain || $ctx->platform->calls!==array(
    'workspace/packages/rest/getlist','workspace/packages/rest/download'))
    throw new \RuntimeException('PACKAGE_DOWNLOAD_IDENTITY_NOT_VERIFIED');
$ctx=new PackageContext();
$ctx->platform->noResponseStage='workspace/packages/rest/download';
$uncertainDownload=false;
try {(new \ModxMcp\Tools\PackageInstallTool())->execute(
    $ctx,array('package'=>'fixture','provider'=>1)
);}catch(ModxMCPClientException $e){
    $uncertainDownload=strpos($e->getMessage(),'MUTATION_OUTCOME_UNCERTAIN:')===0;
}
if(!$uncertainDownload || count($ctx->platform->calls)!==2)
    throw new \RuntimeException('PACKAGE_EMPTY_DOWNLOAD_RESPONSE_NOT_UNCERTAIN');
if(\ModxMcp\Tools\AuditSupport::$count!==0)
    throw new \RuntimeException('PACKAGE_AUDITED_FAILED_MUTATION');
echo 'PACKAGE_MUTATION_UNCERTAIN_RESULT_OK checks=9'.PHP_EOL;
}
