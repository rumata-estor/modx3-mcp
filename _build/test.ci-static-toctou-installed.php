<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Проверка защищённой дескрипторной записи на одноразовом MODX 2/3.
 * EN: Descriptor-anchored static write validation on disposable real MODX 2/3.
 */
if (PHP_SAPI!=='cli' || getenv('MODXMCP_DISPOSABLE_CI')!=='1'
    || strpos((string)getenv('MODX_CONFIG_CORE'),'/home/runner/')!==0
    || strpos((string)getenv('RUNNER_TEMP'),'/home/runner/')!==0
    || !is_file((string)getenv('MODX_CONFIG_CORE'))) {
    fwrite(STDERR,"Refusing static TOCTOU test outside disposable CI.\n");
    exit(3);
}
require_once getenv('MODX_CONFIG_CORE');
$is2=!is_file(MODX_CORE_PATH.'src/Revolution/modX.php');
if($is2){
    require_once MODX_CORE_PATH.'model/modx/modx.class.php';
    $modx=new modX();
}else{
    require_once MODX_CORE_PATH.'vendor/autoload.php';
    $modx=\MODX\Revolution\modX::getInstance();
}
$modx->initialize('mgr');
require_once MODX_CORE_PATH.'components/modxmcp/model/modxmcp.class.php';
new modxMCP($modx);
class DisposableStaticContext {
    private $m;
    public function __construct($m){$this->m=$m;}
    public function modx(){return $this->m;}
}
function ciStaticCheck($cond,$msg) {
    if(!$cond) throw new \RuntimeException('CI_STATIC_TOCTOU_FAIL '.$msg);
}
$ctx=new DisposableStaticContext($modx);
$root=rtrim((string)$modx->getOption('core_path'),'/\\').'/elements';
$madeRoot=false;
if(!is_dir($root)){$madeRoot=@mkdir($root,0700,false);}
ciStaticCheck(is_dir($root),'static root unavailable');
$key='ci-toctou-'.bin2hex(random_bytes(6));
$dir=$root.'/'.$key;
$out=getenv('RUNNER_TEMP').'/ci-static-outside-'.$key;
ciStaticCheck(@mkdir($dir,0700),'fixture dir');
ciStaticCheck(@mkdir($out,0700),'outside dir');
$target=$dir.'/content.tpl';
$external=$out.'/secret.tpl';
file_put_contents($external,'EXTERNAL_SECRET_UNCHANGED');
try{
    $n=\ModxMcp\Tools\StaticFileIoSupport::write($ctx,$target,'FIRST',true);
    ciStaticCheck($n===5,'create');
    ciStaticCheck(\ModxMcp\Tools\StaticFileIoSupport::read($ctx,$target)==='FIRST','read');
    $n=\ModxMcp\Tools\StaticFileIoSupport::write($ctx,$target,'SECOND',false);
    ciStaticCheck($n===6,'update');
    ciStaticCheck(file_get_contents($target)==='SECOND','readback');
    $alias=$dir.'/alias.tpl';
    ciStaticCheck(link($external,$alias),'hard-link fixture');
    $denied=false;
    try{\ModxMcp\Tools\StaticFileIoSupport::write($ctx,$alias,'ATTACK');}
    catch(\ModxMCPClientException $e){$denied=true;}
    ciStaticCheck($denied,'hard-link denied');
    unlink($alias);
    $outsideLink=$root.'/'.$key.'-symlink';
    ciStaticCheck(symlink($out,$outsideLink),'symlink fixture');
    $denied=false;
    try{\ModxMcp\Tools\StaticFileIoSupport::write(
        $ctx,$outsideLink.'/secret.tpl','ATTACK');}
    catch(\ModxMCPClientException $e){$denied=true;}
    ciStaticCheck($denied,'parent symlink denied');
    unlink($outsideLink);
    ciStaticCheck(file_get_contents($external)==='EXTERNAL_SECRET_UNCHANGED',
        'external invariant');
    echo 'CI_STATIC_TOCTOU_INSTALLED_MODX'.($is2?'2':'3').'_OK'.PHP_EOL;
}finally{
    if(is_file($target))unlink($target);
    rmdir($dir);
    unlink($external);
    rmdir($out);
    if($madeRoot)rmdir($root);
}
