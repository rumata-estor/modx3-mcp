<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Безопасный отказ при отсутствии компилятора и неверных параметрах.
 * EN: Safe unavailable status and strict input contract without a compiler.
 */
class ModxMCPClientException extends RuntimeException {}
require __DIR__.'/../core/components/modxmcp/src/Autoloader.php';
\ModxMcp\Autoloader::register(__DIR__.'/../core/components/modxmcp/src');
$tool=new \ModxMcp\Tools\TemplateCheckTool();
$context=new class {
    public function modx(){return $this;}
    public function getOption($key){throw new RuntimeException('Mutable setting must not select compiler.');}
};
$checks=0;
function fenomUnitCheck($ok,$label){global $checks;if(!$ok)throw new RuntimeException($label);$checks++;}
fenomUnitCheck(!$tool->isMutation()&&$tool->supports($context),'read only supported route');
$result=$tool->execute($context,array('content'=>'{$value}'));
fenomUnitCheck($result['status']==='unavailable'&&$result['rendered']===false
    &&$result['dependencies_checked']===false,'missing compiler is not success');
foreach(array(array(),array('content'=>null),array('content'=>array()),
    array('content'=>str_repeat('x',65537)),array('content'=>str_repeat('{',1025)),
    array('content'=>"a\0b"),array('content'=>'{$x}','file'=>'anything')) as $args){
    $blocked=false;
    try{$tool->execute($context,$args);}catch(ModxMCPClientException $e){
        $blocked=strpos($e->getMessage(),'VALIDATION_ERROR:')===0;
    }
    fenomUnitCheck($blocked,'invalid input rejected');
}
echo 'FENOM_PREFLIGHT_CONTRACT_OK checks='.$checks.PHP_EOL;
