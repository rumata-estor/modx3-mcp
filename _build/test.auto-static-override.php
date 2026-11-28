<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Отключение автоматической конвертации только на один вызов.
 * EN: Per-request automatic static conversion override.
 */
class ModxMCPClientException extends Exception {}
require __DIR__.'/../core/components/modxmcp/src/Autoloader.php';
\ModxMcp\Autoloader::register(__DIR__.'/../core/components/modxmcp/src');
use ModxMcp\Tools\ElementMutationSupport;
use ModxMcp\Tools\MutationPreflight;
class AutoStaticContext {
    public $enabled;
    public function modx(){return $this;}
    public function getOption($key,$options=null,$default=null){return $key==='modxmcp.auto_static'?$this->enabled:$default;}
}
$c=new AutoStaticContext();$checks=0;
foreach(array(false,true) as $enabled){
    $c->enabled=$enabled;
    foreach(array('chunk','snippet','template','plugin') as $type){
        foreach(array(array(),array('skip_auto_static'=>false),array('skip_auto_static'=>true)) as $data){
            $expect=$enabled && empty($data['skip_auto_static']);
            if(ElementMutationSupport::shouldAutoStatic($c,$type,$data)!==$expect||$c->enabled!==$enabled)
                throw new RuntimeException('Unexpected conversion decision');
            if(isset(ElementMutationSupport::filterData($type,$data)['skip_auto_static']))
                throw new RuntimeException('Override leaked into native processor');
            $checks++;
        }
    }
}
foreach(array('create_element','update_element') as $action){
    foreach(array(array('type'=>'tv','skip_auto_static'=>true),array('type'=>'resource','skip_auto_static'=>false),
        array('type'=>'chunk','skip_auto_static'=>'true'),array('type'=>'plugin','skip_auto_static'=>1),
        array('type'=>'template','skip_auto_static'=>null)) as $data){
        try {MutationPreflight::assertAllowed($c,$action,$data);throw new RuntimeException('Invalid override accepted');}
        catch(ModxMCPClientException $e){if(strpos($e->getMessage(),'VALIDATION_ERROR:')!==0)throw $e;}
        $checks++;
    }
}
echo 'AUTO_STATIC_OVERRIDE_OK checks='.$checks.PHP_EOL;
