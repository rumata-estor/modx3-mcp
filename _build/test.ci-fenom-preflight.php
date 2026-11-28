<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Реальный Fenom в одноразовом MODX, без загрузки pdoTools.
 * EN: Real Fenom compiler on disposable MODX, without booting pdoTools.
 */
if(PHP_SAPI!=='cli'||getenv('MODXMCP_DISPOSABLE_CI')!=='1'
    ||strpos((string)getenv('MODX_CONFIG_CORE'),'/home/runner/')!==0
    ||!is_file((string)getenv('MODX_CONFIG_CORE'))) {
    fwrite(STDERR,"Refusing Fenom integration outside disposable CI.\n");exit(3);
}
require_once getenv('MODX_CONFIG_CORE');
$is2=!is_file(MODX_CORE_PATH.'src/Revolution/modX.php');
if($is2){
    require_once MODX_CORE_PATH.'model/modx/modx.class.php';$modx=new modX();
}else{
    require_once MODX_CORE_PATH.'vendor/autoload.php';$modx=\MODX\Revolution\modX::getInstance();
}
$modx->initialize('mgr');
require_once MODX_CORE_PATH.'components/modxmcp/model/modxmcp.class.php';
require_once __DIR__.'/test.cas-mcp-helper.php';
$api=new modxMCP($modx);
$checks=0;
function fenomCheck($ok,$label){global $checks;if(!$ok)throw new RuntimeException('FENOM_PREFLIGHT_FAILED: '.$label);$checks++;}
function fenomRpc($action,array $data=array()){global $api;return disposableCasRpc($api,$action,$data);}
function checkSource($source){return fenomRpc('check_template',array('content'=>$source));}
$core=rtrim(MODX_CORE_PATH,'/\\').'/components/pdotools';
$src=$core.'/vendor/fenom/fenom/src';
$sentinel=$core.'/mcp_fenom_bootstrap_executed';
$compiler=$src.'/Fenom.php';
fenomCheck(is_file($compiler)&&!is_file($sentinel),'pinned library staged');
$state=(int)fenomRpc('get_site_state')['site_revision'];
$expectedFiles=array();
$iter=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($core,FilesystemIterator::SKIP_DOTS));
foreach($iter as $f)if($f->isFile())$expectedFiles[$f->getPathname()]=hash_file('sha256',$f->getPathname());
ksort($expectedFiles);
$cases=array(
    array('<div>{$name}</div>','syntax_ok'),
    array('{if $x}yes{else}no{/if}','syntax_ok'),
    array('{foreach $items as $item}{$item}{/foreach}','syntax_ok'),
    array('{"test" | chunk}','syntax_ok'),
    array('{"key" | getOption}','syntax_ok'),
    array('{$x | toPlaceholder : "mcp_sentinel"}','syntax_ok'),
    array('{$x | haspermission}','inconclusive'),
    array('{$_modx->runSnippet("MCP_SENTINEL_DO_NOT_EXECUTE")}','syntax_ok'),
    array('{include "chunk:never_read"}','syntax_ok'),
    array('{include "file:/never_read"}','syntax_ok'),
    array("line1\n{if \$x}\ntext",'syntax_error'),
    array('{if $x}{/foreach}','syntax_error'),
    array('{* broken comment','syntax_error'),
    array('{extends "file:/never_read"}','inconclusive'),
    array('{insert "file:/never_read"}','inconclusive'),
    array('{$.php.phpinfo()}','inconclusive'),
    array('{unknown_custom_tag}','inconclusive'),
    array('{$x | unknown_custom_modifier}','inconclusive'),
    array('{McpUnknownClass::sentinel()}','inconclusive'),
);
foreach($cases as $pair){
    $view=checkSource($pair[0]);
    fenomCheck(($view['status']??'')===$pair[1],'expected '.$pair[1].' for case '.($checks-1).', got '.($view['status']??'missing'));
    fenomCheck($view['rendered']===false&&$view['dependencies_checked']===false&&$view['site_extensions_checked']===false,'no runtime claims');
    if($pair[1]==='syntax_error')fenomCheck(is_int($view['line'])&&$view['line']>=1,'safe line diagnostic');
    fenomCheck(strpos(json_encode($view),'MCP_SENTINEL')===false,'no raw source echoed');
}
$blocked=false;
try{checkSource(str_repeat('x',65537));}catch(ModxMCPClientException $e){$blocked=strpos($e->getMessage(),'VALIDATION_ERROR:')===0;}
fenomCheck($blocked,'oversize refused');
$blocked=false;
try{checkSource(str_repeat('{',1025));}catch(ModxMCPClientException $e){$blocked=strpos($e->getMessage(),'VALIDATION_ERROR:')===0;}
fenomCheck($blocked,'delimiter budget refused');
$blocked=false;
try{fenomRpc('check_template',array('content'=>'{$x}','path'=>'/etc/passwd'));}catch(ModxMCPClientException $e){$blocked=strpos($e->getMessage(),'VALIDATION_ERROR:')===0;}
fenomCheck($blocked,'no path field');
$actualFiles=array();
$iter=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($core,FilesystemIterator::SKIP_DOTS));
foreach($iter as $f)if($f->isFile())$actualFiles[$f->getPathname()]=hash_file('sha256',$f->getPathname());
ksort($actualFiles);
fenomCheck($expectedFiles===$actualFiles&&!is_file($sentinel),'no bootstrap, template or vendor file changed');
fenomCheck((int)fenomRpc('get_site_state')['site_revision']===$state,'read-only revision unchanged');
// Tamper only the disposable fixture, never an installed real addon.
$original=file_get_contents($compiler);
try{
    file_put_contents($compiler,$original."\n// changed fixture\n");
    fenomCheck(checkSource('{$x}')['status']==='unavailable','unknown library version denied');
}finally{file_put_contents($compiler,$original);}
echo 'FENOM_PREFLIGHT_REAL_MODX_OK checks='.$checks.PHP_EOL;
