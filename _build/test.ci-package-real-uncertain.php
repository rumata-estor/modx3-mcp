<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Настоящий одноразовый MODX transport: установка/удаление с потерей ответа.
 * EN: Actual disposable MODX transport: install/uninstall followed by lost response.
 */
/**
 * Only GitHub disposable MODX, only newly built fixture transport package.
 * No network provider, no third-party package or production site.
 * Native modTransportPackage::install/uninstall runs before simulated transport
 * failure, proving real DB state after an MCP uncertain outcome.
 */
if (PHP_SAPI !== 'cli' || getenv('MODXMCP_DISPOSABLE_CI') !== '1'
    || strpos((string)getenv('MODX_CONFIG_CORE'),'/home/runner/') !== 0
    || strpos((string)getenv('RUNNER_TEMP'),'/home/runner/') !== 0
    || !is_file((string)getenv('MODX_CONFIG_CORE'))) {
    fwrite(STDERR,"Refusing real package fault test outside disposable CI.\n");
    exit(3);
}
require_once getenv('MODX_CONFIG_CORE');
$is2=!is_file(MODX_CORE_PATH.'src/Revolution/modX.php');
if ($is2) {
    require_once MODX_CORE_PATH.'model/modx/modx.class.php';
    $modx=new modX();
    $modx->loadClass('transport.modPackageBuilder','',false,true);
    $builderClass='modPackageBuilder';
    $packageClass='transport.modTransportPackage';
    $settingClass='modSystemSetting';
    $vehicleClass='xPDOTransport';
} else {
    require_once MODX_CORE_PATH.'vendor/autoload.php';
    $modx=\MODX\Revolution\modX::getInstance();
    $builderClass='MODX\\Revolution\\Transport\\modPackageBuilder';
    $packageClass='MODX\\Revolution\\Transport\\modTransportPackage';
    $settingClass='MODX\\Revolution\\modSystemSetting';
    $vehicleClass='xPDO\\Transport\\xPDOTransport';
}
$modx->initialize('mgr');
require_once MODX_CORE_PATH.'components/modxmcp/model/modxmcp.class.php';
new modxMCP($modx);
$token=bin2hex(random_bytes(6));
$name='mcpfault'.$token;
$signature=$name.'-1.0-pl';
$settingKey='mcpfault.'.$token;
$packagesDir=rtrim(MODX_CORE_PATH,'/\\').'/packages/';
$archive=$packagesDir.$signature.'.transport.zip';
$staging=$packagesDir.$signature;
$package=null;
$setting=null;
function transportCheck($truth,$message) {
    if (!$truth) throw new \RuntimeException('REAL_PACKAGE_FAULT_FAIL '.$message);
}
function removeDisposableStaging($staging,$packagesDir) {
    if(!is_dir($staging)) return;
    transportCheck(realpath(dirname($staging))===realpath($packagesDir),
        'staging must be immediate child of packages directory');
    $entries=new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($staging,\FilesystemIterator::SKIP_DOTS),
        \RecursiveIteratorIterator::CHILD_FIRST);
    foreach($entries as $entry) {
        $path=$entry->getPathname();
        if($entry->isDir() && !$entry->isLink()) rmdir($path);
        else unlink($path);
    }
    rmdir($staging);
}
function transportUncertain($invoke,$label) {
    try {call_user_func($invoke);}
    catch(\ModxMCPClientException $e) {
        transportCheck(strpos($e->getMessage(),'MUTATION_OUTCOME_UNCERTAIN:')===0,
            $label.' response must be uncertain');
        transportCheck(strpos($e->getMessage(),'FAKE_SECRET_')===false,
            $label.' raw exception leaked');
        return;
    }
    throw new \RuntimeException('REAL_PACKAGE_FAULT_FALSE_SUCCESS '.$label);
}
class NativePackageResponse {
    private $values;
    public function __construct($values){$this->values=$values;}
    public function isError(){return false;}
    public function getResponse(){return $this->values;}
    public function getObject(){return $this->values;}
}
class NativePackageFaultPlatform {
    public $package;
    public $packageClass;
    public $name;
    public $signature;
    public $installs=0;
    public $uninstalls=0;
    public function __construct($p,$cls,$name,$sig) {
        $this->package=$p;$this->packageClass=$cls;
        $this->name=$name;$this->signature=$sig;
    }
    public function className($key){return $key==='transport_package'?$this->packageClass:$key;}
    public function runProcessor($modx,$processor,$data){
        if($processor==='workspace/packages/rest/getlist'){
            return new NativePackageResponse(array('results'=>array(array(
                'name'=>$this->name,'signature'=>$this->signature,
                'location'=>'https://example.invalid/local-ci-transport'
            ))));
        }
        if($processor==='workspace/packages/rest/download'){
            return new NativePackageResponse(array('signature'=>$this->signature));
        }
        if($processor==='workspace/packages/install'){
            $this->installs++;
            $ok=$this->package->install();
            transportCheck($ok,'native MODX package installation failed');
            throw new \RuntimeException('FAKE_SECRET_LOST_INSTALL_RESPONSE');
        }
        if($processor==='workspace/packages/uninstall'){
            $this->uninstalls++;
            $ok=$this->package->uninstall();
            transportCheck($ok,'native MODX package removal failed');
            throw new \RuntimeException('FAKE_SECRET_LOST_UNINSTALL_RESPONSE');
        }
        throw new \RuntimeException('Unexpected package stage '.$processor);
    }
}
class NativePackageFaultContext {
    private $m,$p;
    public function __construct($m,$p){$this->m=$m;$this->p=$p;}
    public function modx(){return $this->m;}
    public function platform(){return $this->p;}
}
try {
    $builder=new $builderClass($modx);
    transportCheck($builder->createPackage($name,'1.0','pl')!==false,'createPackage');
    $setting=$modx->newObject($settingClass);
    $setting->fromArray(array(
        'key'=>$settingKey,'value'=>'fixture-installed',
        'xtype'=>'textfield','namespace'=>'core','area'=>'system'
    ),'',true,true);
    $attrs=array(
        constant($vehicleClass.'::UNIQUE_KEY')=>'key',
        constant($vehicleClass.'::PRESERVE_KEYS')=>true,
        constant($vehicleClass.'::UPDATE_OBJECT')=>false
    );
    $vehicle=$builder->createVehicle($setting,$attrs);
    transportCheck($vehicle!==false,'createVehicle');
    $builder->putVehicle($vehicle);
    $builder->setPackageAttributes(array('license'=>'MIT','readme'=>'CI test only'));
    transportCheck($builder->pack()!==false,'pack');
    transportCheck(is_file($archive),'real package ZIP not built');
    // Remove builder's unpacked tree so the native installer can extract ZIP.
    removeDisposableStaging($staging,$packagesDir);
    // Real MODX transport objects, same construction as installed MCP release.
    $package=$modx->newObject($packageClass);
    $package->set('signature',$signature);
    $package->set('package_name',$name);
    $package->set('state',1);
    $package->set('created',date('Y-m-d H:i:s'));
    $package->set('workspace',1);
    $package->set('version_major',1);
    $package->set('version_minor',0);
    $package->set('version_patch',0);
    $package->set('release','pl');
    $package->set('release_index',0);
    transportCheck($package->save(),'real package record save');
    $platform=new NativePackageFaultPlatform($package,$packageClass,$name,$signature);
    $context=new NativePackageFaultContext($modx,$platform);
    transportUncertain(function() use($context,$name,$signature) {
        (new \ModxMcp\Tools\PackageInstallTool())->execute($context,array(
            'package'=>$name,'provider'=>1,'target_signature'=>$signature));
    },'install');
    $persisted=$modx->getObject($settingClass,array('key'=>$settingKey));
    transportCheck($persisted && $persisted->get('value')==='fixture-installed',
        'native install side effect missing after lost response');
    transportCheck($platform->installs===1,'unexpected install count');
    transportUncertain(function()use($context,$signature){
        (new \ModxMcp\Tools\PackageUninstallTool())->execute($context,array(
            'signature'=>$signature));
    },'uninstall');
    $after=$modx->getObject($settingClass,array('key'=>$settingKey),false);
    transportCheck($platform->uninstalls===1,'unexpected uninstall count');
    transportCheck(!$package->get('installed'),
        'package still marked installed after successful native uninstall');
    // A transport may preserve system settings even when uninstall succeeds.
    // Do not treat MODX uninstall() success as proof of clean rollback.
    if($after) echo 'NATIVE_PACKAGE_UNINSTALL_RESIDUAL_SETTING_OBSERVED'.PHP_EOL;
    echo 'NATIVE_PACKAGE_INSTALL_UNINSTALL_UNCERTAIN_MODX'.($is2?'2':'3').'_OK'.PHP_EOL;
} finally {
    // Cleanup of the uniquely named throwaway package, never modxmcp itself.
    if($package) {
        if($package->get('installed')) $package->uninstall();
        $package->remove();
    }
    $still=$modx->getObject($settingClass,array('key'=>$settingKey));
    if($still) $still->remove();
    if(is_file($archive)) unlink($archive);
    removeDisposableStaging($staging,$packagesDir);
}
