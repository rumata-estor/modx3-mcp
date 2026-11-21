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
echo 'CLIENTCONFIG_CACHE_INVALIDATION_OK checks=3'.PHP_EOL;
}
