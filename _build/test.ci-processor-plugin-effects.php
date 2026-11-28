<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Одноразовый MODX-плагин меняет файл, затем процессор теряет ответ.
 * EN: Disposable MODX plugin changes a file before a processor loses its response.
 */
/**
 * Real MODX event/plugin execution with a temporary DB-backed modPlugin
 * and modPluginEvent; the processor dispatch is instrumented to fail AFTER
 * event invocation. This does not claim arbitrary third-party plugin rollback.
 */
if (PHP_SAPI!=='cli' || getenv('MODXMCP_DISPOSABLE_CI')!=='1'
    || strpos((string)getenv('MODX_CONFIG_CORE'),'/home/runner/')!==0
    || strpos((string)getenv('RUNNER_TEMP'),'/home/runner/')!==0
    || !is_file((string)getenv('MODX_CONFIG_CORE'))) {
    fwrite(STDERR,"Refusing plugin fault test outside disposable CI.\n");exit(3);
}
require_once getenv('MODX_CONFIG_CORE');
$is2=!is_file(MODX_CORE_PATH.'src/Revolution/modX.php');
if ($is2) {
    require_once MODX_CORE_PATH.'model/modx/modx.class.php';
    $modx=new modX();
    $pluginClass='modPlugin';
    $linkClass='modPluginEvent';
} else {
    require_once MODX_CORE_PATH.'vendor/autoload.php';
    $modx=\MODX\Revolution\modX::getInstance();
    $pluginClass='MODX\\Revolution\\modPlugin';
    $linkClass='MODX\\Revolution\\modPluginEvent';
}
$modx->initialize('mgr');
require_once MODX_CORE_PATH.'components/modxmcp/model/modxmcp.class.php';
$api=new modxMCP($modx);
$uid=bin2hex(random_bytes(6));
$pluginName='mcpfaultplugin'.$uid;
$file=getenv('RUNNER_TEMP').'/mcp-plugin-event-'.$uid.'.txt';
$plugin=null;
$link=null;
function pluginCheck($truth,$label){
    if(!$truth)throw new \RuntimeException('REAL_PLUGIN_EFFECT_FAIL '.$label);
}
class DisposablePluginFaultPlatform {
    public $count=0;
    public function runProcessor($modx,$processor,$props){
        $this->count++;
        pluginCheck($processor==='resource/getlist','unexpected processor path');
        // Deliberately exercise MODX core event dispatch with a real plugin.
        $modx->invokeEvent('OnDocFormSave',array('mode'=>'upd'));
        throw new \RuntimeException('token=FAKE_SECRET_PLUGIN_PROCESSOR');
    }
    public function processorTarget($name){return $name;}
}
class DisposablePluginFaultContext {
    private $modx,$platform;
    public function __construct($m,$p){$this->modx=$m;$this->platform=$p;}
    public function modx(){return $this->modx;}
    public function platform(){return $this->platform;}
}
try {
    // CI fixture: MODX API writes an isolated plugin and event association.
    $plugin=$modx->newObject($pluginClass);
    $plugin->set('name',$pluginName);
    $plugin->set('description','Disposable MCP test only');
    $script="file_put_contents(".var_export($file,true).",'side-effect-before-error');";
    $plugin->set('plugincode',$script);
    $plugin->set('disabled',0);
    pluginCheck($plugin->save(),'cannot save disposable plugin');
    $link=$modx->newObject($linkClass);
    $link->set('pluginid',$plugin->get('id'));
    $link->set('event','OnDocFormSave');
    $link->set('priority',0);
    pluginCheck($link->save(),'cannot link event');
    $manager=$modx->getCacheManager();
    if($manager)$manager->refresh();
    // CLI has already initialized mgr and its eventMap before our plugin
    // was created. Prime this disposable process from the saved association;
    // invokeEvent() still loads and runs the real persisted MODX plugin.
    $eventMap=new \ReflectionProperty($modx,'eventMap');
    $eventMap->setAccessible(true);
    $eventMap->setValue($modx,array(
        'OnDocFormSave'=>array((int)$plugin->get('id')=>'0:')
    ));
    $modx->setOption('modxmcp.allow_run_processor',true);
    $modx->setOption('modxmcp.processor_access_mode','allowlist');
    $modx->setOption('modxmcp.allowed_processors','resource/getlist');
    $modx->setOption('modxmcp.privileged_processors','');
    $platform=new DisposablePluginFaultPlatform();
    $context=new DisposablePluginFaultContext($modx,$platform);
    $uncertain=false;
    try {
        (new \ModxMcp\Tools\RunProcessorTool())->execute($context,array(
            'processor'=>'resource/getlist','properties'=>array('limit'=>1)
        ));
    }catch(\ModxMCPClientException $e) {
        $uncertain=strpos($e->getMessage(),'MUTATION_OUTCOME_UNCERTAIN:')===0
            && strpos($e->getMessage(),'FAKE_SECRET_')===false;
    }
    pluginCheck($uncertain,'MCP must not report success or reveal plugin secret');
    pluginCheck($platform->count===1,'dispatch count');
    pluginCheck(is_file($file),'event plugin did not create external file');
    pluginCheck(file_get_contents($file)==='side-effect-before-error',
        'unexpected external side effect');
    // Real database rollback plus a real MODX plugin external effect. A
    // misleading guard-looking exception must not describe the whole call as unapplied.
    $runtimeContext=(new \ModxMcp\Core\Runtime($modx,$api))->context();
    $state=\ModxMcp\Tools\StateSupport::class;
    unlink($file);
    $before=$state::revision($runtimeContext);
    $uncertain=false;
    try {
        $state::withMutationLock($runtimeContext,function()use($state,$runtimeContext,$modx){
            $state::withRevisionTransaction($runtimeContext,function()use($state,$runtimeContext,$modx){
                $state::markRevisionEffectsStarted($runtimeContext);
                $state::incrementRevision($runtimeContext);
                $modx->invokeEvent('OnDocFormSave',array('mode'=>'upd'));
                throw new \ModxMCPClientException('STALE_STATE: FAKE_SECRET_AFTER_EFFECT');
            });
        });
    } catch (\ModxMCPClientException $e) {
        $uncertain=strpos($e->getMessage(),'MUTATION_OUTCOME_UNCERTAIN:')===0
            && strpos($e->getMessage(),'FAKE_SECRET')===false;
    }
    pluginCheck($uncertain && is_file($file) && $state::revision($runtimeContext)===$before,
        'DB rollback with surviving external effect is not a pre-write denial');
    $partial=false;
    try {
        $state::withMutationLock($runtimeContext,function()use($state,$runtimeContext){
            $state::withRevisionTransaction($runtimeContext,function()use($state,$runtimeContext){
                $state::markRevisionEffectsStarted($runtimeContext);
                $state::incrementRevision($runtimeContext);
                $state::afterRevisionCommit($runtimeContext,function(){
                    throw new \RuntimeException('FAKE_SECRET_POST_COMMIT');
                });
            });
        });
    } catch (\ModxMCPClientException $e) {
        $partial=$e->getMessage()===\ModxMcp\Security\MutationOutcomeGuard::POST_COMMIT_MESSAGE;
    }
    pluginCheck($partial && $state::revision($runtimeContext)===$before+1,
        'confirmed commit with required follow-up failure is not full success');
    echo 'REAL_DB_AND_EXTERNAL_PARTIAL_OUTCOME_OK'.PHP_EOL;
    echo 'REAL_MODX_PLUGIN_EXTERNAL_EFFECT_MODX'.($is2?'2':'3').'_OK'.PHP_EOL;
} finally {
    $modx->setOption('modxmcp.allow_run_processor',false);
    $modx->setOption('modxmcp.processor_access_mode','disabled');
    if($link && $link->get('id'))$link->remove();
    if($plugin && $plugin->get('id'))$plugin->remove();
    if(is_file($file))unlink($file);
}
