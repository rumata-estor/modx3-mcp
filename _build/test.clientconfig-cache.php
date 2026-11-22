<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Проверка адресной инвалидации кеша ClientConfig.
 * EN: Targeted ClientConfig cache invalidation and failure isolation.
 */
namespace ModxMcp\Tools {
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/ClientConfigSupport.php';
}
namespace {
class ModxMCPClientException extends \RuntimeException {}
class FakeClientConfigCache {
    public $values=array('system_settings:clientconfig'=>array('global'=>array('fixture'=>'old')));
    public $calls=array();public $fail=false;
    private function key($name,$options){
        return $options['cache_key'].':'.$name;
    }
    public function delete($name,$options){
        $this->calls[]=$options['cache_key'].':'.$name;
        if(!$this->fail)unset($this->values[$this->key($name,$options)]);
        return !$this->fail;
    }
    public function get($name,$options){
        $k=$this->key($name,$options);
        return array_key_exists($k,$this->values)?$this->values[$k]:null;
    }
}
class FakeClientConfigModx {
    public $cache;public $events=array();public $clear;
    public function __construct($clear){$this->cache=new FakeClientConfigCache();$this->clear=$clear;}
    public function getCacheManager(){return $this->cache;}
    public function getOption($key,$unused=null,$default=null){
        return $key==='clientconfig.clear_cache'?$this->clear:$default;
    }
    public function invokeEvent($event){$this->events[]=$event;}
}
class FakeClientConfigContext {
    private $modx;
    public function __construct($clear){$this->modx=new FakeClientConfigModx($clear);}
    public function modx(){return $this->modx;}
}
foreach(array(false,true) as $clear){
    $ctx=new FakeClientConfigContext($clear);
    \ModxMcp\Tools\ClientConfigSupport::refresh($ctx);
    $calls=$ctx->modx()->cache->calls;
    if($calls!==($clear?array('system_settings:clientconfig','resource:')
        :array('system_settings:clientconfig')))
        throw new \RuntimeException('CLIENTCONFIG_CACHE_TARGETING_FAILED');
    if($ctx->modx()->events!==array('ClientConfig_ConfigChange'))
        throw new \RuntimeException('CLIENTCONFIG_EVENT_MISSING');
}
$ctx=new FakeClientConfigContext(false);
$ctx->modx()->cache->fail=true;
try{
    \ModxMcp\Tools\ClientConfigSupport::refresh($ctx);
    throw new \RuntimeException('CLIENTCONFIG_CACHE_FAILURE_NOT_DETECTED');
}catch(ModxMCPClientException $e){
    if(strpos($e->getMessage(),'MUTATION_OUTCOME_UNCERTAIN')!==0)throw $e;
}
if($ctx->modx()->events!==array())
    throw new \RuntimeException('CLIENTCONFIG_EVENT_BEFORE_INVALIDATION');
// RU: Моделируем повторное чтение через тот же ключ, что ClientConfig::getSettings.
// EN: Verify a second read reloads the dedicated settings cache after mutation.
$ctx=new FakeClientConfigContext(false);
$cache=$ctx->modx()->cache;
$cache->values['system_settings:clientconfig']=array(
    'global'=>array('ci_setting'=>'old'),
    'contexts'=>array('web'=>array('ci_setting'=>'web_old'))
);
$stored=array('global'=>'new','web'=>'web_old');
$read=function($contextAware)use($cache,&$stored) {
    $options=array('cache_key'=>'system_settings');
    $values=$cache->get('clientconfig',$options);
    if(!is_array($values)){
        $values=array('global'=>array('ci_setting'=>$stored['global']),
            'contexts'=>array('web'=>array('ci_setting'=>$stored['web'])));
        $cache->values['system_settings:clientconfig']=$values;
    }
    return $contextAware
        ? array_merge($values['global'],$values['contexts']['web'])
        : $values['global'];
};
$stored['global']='new';
if($read(false)['ci_setting']!=='old')throw new \RuntimeException('CLIENTCONFIG_STALE_CACHE_FIXTURE_INVALID');
\ModxMcp\Tools\ClientConfigSupport::refresh($ctx);
if($read(false)['ci_setting']!=='new')throw new \RuntimeException('CLIENTCONFIG_GLOBAL_RELOAD_FAILED');
// RU: При активной настройке context_aware значение web выше глобального.
// EN: A web context override intentionally takes priority over global value.
if($read(true)['ci_setting']!=='web_old')throw new \RuntimeException('CLIENTCONFIG_CONTEXT_PRECEDENCE_FAILED');
$stored['web']='web_new';
\ModxMcp\Tools\ClientConfigSupport::refresh($ctx);
if($read(true)['ci_setting']!=='web_new')throw new \RuntimeException('CLIENTCONFIG_CONTEXT_RELOAD_FAILED');
echo 'CLIENTCONFIG_CACHE_INVALIDATION_OK checks=7'.PHP_EOL;
}
