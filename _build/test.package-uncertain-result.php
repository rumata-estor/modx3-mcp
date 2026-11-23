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
    // Isolation stub: package preflight uses the real package validators.
    class ProcessorMutationTool {
        public static function assertAclMutationFields($action,array $data){}
    }
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/ProcessorSupport.php';
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/PackageSupport.php';
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/PackageInstallTool.php';
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/PackageUninstallTool.php';
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/MutationPreflight.php';
}
namespace {
class ModxMCPClientException extends \RuntimeException {}
class PackageResponse {
    public static $throwStatusStage='';
    public static $successStage='';
    private $stage;
    public function __construct($stage){$this->stage=$stage;}
    public function isError(){
        if(self::$throwStatusStage===$this->stage)
            throw new \RuntimeException('SECRET_STATUS_EXCEPTION');
        return (self::$successStage!==$this->stage)
            && ($this->stage==='install'||$this->stage==='uninstall');
    }
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
    public static $throwAt='';
    private $signature;
    public function __construct($signature){$this->signature=$signature;}
    public function isError(){
        if(self::$throwAt==='isError')throw new \RuntimeException('SECRET_STATUS_EXCEPTION');
        return false;
    }
    public function getObject(){
        if(self::$throwAt==='getObject')throw new \RuntimeException('SECRET_BODY_EXCEPTION');
        return array('signature'=>$this->signature);
    }
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
    public $cacheThrows=false;
    public function getObject($class,$criteria){return false;}
    public function getCacheManager(){
        if($this->cacheThrows)throw new \RuntimeException('SECRET_CACHE_EXCEPTION');
        return null;
    }
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
// RU: Сбои чтения ответа ПОСЛЕ этапа скачивания/установки/удаления
// RU: классифицируются как неопределённый исход и не раскрывают секреты.
// EN: Post-stage response accessor faults must be uncertain and redacted.
foreach(array('isError','getObject') as $fault) {
    \PackageDownloadResponse::$throwAt=$fault;
    $ctx=new PackageContext();
    $denied=false;
    try{(new \ModxMcp\Tools\PackageInstallTool())->execute($ctx,
        array('package'=>'fixture','provider'=>1));}
    catch(ModxMCPClientException $e){
        $denied=strpos($e->getMessage(),'MUTATION_OUTCOME_UNCERTAIN:')===0
            && strpos($e->getMessage(),'SECRET_')===false;
    }
    if(!$denied || count($ctx->platform->calls)!==2)
        throw new \RuntimeException('DOWNLOAD_POST_STAGE_FAULT_LEAK_'.$fault);
}
\PackageDownloadResponse::$throwAt='';
foreach(array('install','uninstall') as $stage) {
    \PackageResponse::$throwStatusStage=$stage;
    $ctx=new PackageContext();
    $tool=$stage==='install'
        ?new \ModxMcp\Tools\PackageInstallTool()
        :new \ModxMcp\Tools\PackageUninstallTool();
    $data=$stage==='install'
        ?array('package'=>'fixture','provider'=>1)
        :array('signature'=>'fixture-1.0.0-pl');
    $denied=false;
    try{$tool->execute($ctx,$data);}
    catch(ModxMCPClientException $e){
        $denied=strpos($e->getMessage(),'MUTATION_OUTCOME_UNCERTAIN:')===0
            && strpos($e->getMessage(),'SECRET_')===false;
    }
    if(!$denied || count($ctx->platform->calls)!==($stage==='install'?3:1))
        throw new \RuntimeException('PACKAGE_POST_STAGE_FAULT_LEAK_'.$stage);
}
\PackageResponse::$throwStatusStage='';
// RU: Даже при ответе success сбой обновления кэша после изменения
// RU: должен давать UNCERTAIN, а не ложно подтверждённый успех.
// EN: Post-success cache exceptions must not suppress uncertain outcome.
foreach(array('install','uninstall') as $stage) {
    \PackageResponse::$successStage=$stage;
    $ctx=new PackageContext();
    $ctx->modx->cacheThrows=true;
    $tool=$stage==='install'
        ?new \ModxMcp\Tools\PackageInstallTool()
        :new \ModxMcp\Tools\PackageUninstallTool();
    $data=$stage==='install'
        ?array('package'=>'fixture','provider'=>1)
        :array('signature'=>'fixture-1.0.0-pl');
    $denied=false;
    try{$tool->execute($ctx,$data);}
    catch(ModxMCPClientException $e){
        $denied=strpos($e->getMessage(),'MUTATION_OUTCOME_UNCERTAIN:')===0
            && strpos($e->getMessage(),'SECRET_')===false;
    }
    if(!$denied)throw new \RuntimeException('PACKAGE_CACHE_FAULT_NOT_UNCERTAIN_'.$stage);
}
\PackageResponse::$successStage='';
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
// RU: Неверные типы не превращаются в иной provider/имя пакета и
// RU: не расходуют CAS/nonce — в обоих маршрутах отказ до процессора.
// EN: Bad types fail in preflight and direct invocation before processor I/O.
$invalidSelectors=array(
    array('install_package',array('package'=>array('fixture'))),
    array('install_package',array('package'=>(object)array('name'=>'fixture'))),
    array('install_package',array('package'=>true)),
    array('install_package',array('package'=>'')),
    array('install_package',array('package'=>'fixture','provider'=>array(1))),
    array('install_package',array('package'=>'fixture','provider'=>true)),
    array('install_package',array('package'=>'fixture','provider'=>0)),
    array('install_package',array('package'=>'fixture','provider'=>'1foo')),
    array('install_package',array('package'=>'fixture','provider'=>1.5)),
    array('install_package',array('package'=>'fixture','provider'=>'001')),
    array('install_package',array('package'=>'fixture','target_signature'=>array('foo'))),
    array('uninstall_package',array('signature'=>array('fixture'))),
    array('uninstall_package',array('signature'=>(object)array('x'=>1))),
    array('uninstall_package',array('signature'=>true)),
    array('uninstall_package',array('signature'=>'   ')),
);
$inputChecks=0;
foreach ($invalidSelectors as $case) {
    $action=$case[0];$data=$case[1];$ctx=new PackageContext();
    $tool=$action==='install_package'
        ?new \ModxMcp\Tools\PackageInstallTool()
        :new \ModxMcp\Tools\PackageUninstallTool();
    foreach(array('preflight','execute') as $route) {
        $rejected=false;
        try {
            if($route==='preflight')
                \ModxMcp\Tools\MutationPreflight::assertAllowed($ctx,$action,$data);
            else $tool->execute($ctx,$data);
        }catch(ModxMCPClientException $e){
            $rejected=strpos($e->getMessage(),'INVALID_INPUT:')===0
                && $e->getPrevious()===null;
        }
        if(!$rejected || count($ctx->platform->calls)!==0)
            throw new \RuntimeException('PACKAGE_INVALID_SELECTOR_DISPATCHED '.$action.' '.$route);
        $inputChecks++;
    }
}
foreach(array(
    array('package'=>'fixture','provider'=>1,'target_signature'=>'fixture-1.0.0-pl'),
    array('package'=>'fixture','provider'=>'13'),
    array('package'=>'fixture')
) as $data) {
    if(!\ModxMcp\Tools\PackageInstallTool::validateInput($data))
        throw new \RuntimeException('VALID_INSTALL_PAYLOAD_REJECTED');
    $inputChecks++;
}
if(!\ModxMcp\Tools\PackageUninstallTool::validateInput(
    array('signature'=>'fixture-1.0.0-pl')))
    throw new \RuntimeException('VALID_UNINSTALL_PAYLOAD_REJECTED');
$inputChecks++;
echo 'PACKAGE_INPUT_PREFLIGHT_FAIL_CLOSED_OK checks='.$inputChecks.PHP_EOL;
echo 'PACKAGE_MUTATION_UNCERTAIN_RESULT_OK checks=15'.PHP_EOL;
}
