<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Изолированная проверка файловой замены на реальных MODX 2 и 3.
 * EN: Portable file replacement against disposable actual MODX 2 and 3.
 */
if (PHP_SAPI!=='cli' || getenv('MODXMCP_DISPOSABLE_CI')!=='1'
    || strpos((string)getenv('MODX_CONFIG_CORE'),'/home/runner/')!==0
    || strpos((string)getenv('RUNNER_TEMP'),'/home/runner/')!==0
    || !is_file((string)getenv('MODX_CONFIG_CORE'))) {
    fwrite(STDERR,"Refusing static portability test outside disposable CI.\n");
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
class DisposablePortableContext {
    private $m;
    public function __construct($m){$this->m=$m;}
    public function modx(){return $this->m;}
}
function portableCIAssert($ok,$label){
    if(!$ok)throw new \RuntimeException('CI_PORTABLE_STATIC_FAILURE '.$label);
}
$ctx=new DisposablePortableContext($modx);
$root=rtrim((string)$modx->getOption('core_path'),'/\\').'/elements';
$createdRoot=false;
if(!is_dir($root))$createdRoot=@mkdir($root,0700,false);
portableCIAssert(is_dir($root),'static root');
$dir=$root.'/mcp-ci-'.bin2hex(random_bytes(5));
portableCIAssert(@mkdir($dir,0700),'test directory');
$file=$dir.'/test.tpl';
$external=getenv('RUNNER_TEMP').'/mcp-ci-private-'.bin2hex(random_bytes(4));
file_put_contents($external,'EXTERNAL_UNCHANGED');
try {
    $n=\ModxMcp\Tools\StaticFileWriteSupport::replace($ctx,$file,'OLD');
    portableCIAssert($n===3 && file_get_contents($file)==='OLD','create');
    chmod($file,0640);
    $n=\ModxMcp\Tools\StaticFileWriteSupport::replace($ctx,$file,'NEW');
    portableCIAssert($n===3 && file_get_contents($file)==='NEW','replace');
    portableCIAssert((fileperms($file)&0777)===0640,'file mode');
    $blocked=false;
    try{\ModxMcp\Tools\StaticFileWriteSupport::replace($ctx,$file,'X',true);}
    catch(\ModxMCPClientException $e){$blocked=true;}
    portableCIAssert($blocked && file_get_contents($file)==='NEW','exclusive failure');
    unlink($file);
    link($external,$file);
    $blocked=false;
    try{\ModxMcp\Tools\StaticFileWriteSupport::replace($ctx,$file,'X');}
    catch(\ModxMCPClientException $e){$blocked=true;}
    portableCIAssert($blocked && file_get_contents($external)==='EXTERNAL_UNCHANGED',
        'hardlink refused and secret unchanged');
    unlink($file);
    symlink($external,$file);
    $blocked=false;
    try{\ModxMcp\Tools\StaticFileWriteSupport::replace($ctx,$file,'X');}
    catch(\ModxMCPClientException $e){$blocked=true;}
    portableCIAssert($blocked && file_get_contents($external)==='EXTERNAL_UNCHANGED',
        'symlink refused and secret unchanged');
    unlink($file);
    portableCIAssert(count(glob($dir.'/.modxmcp-*.tmp'))===0,'cleanup');
    echo 'CI_PORTABLE_STATIC_REAL_MODX'.($is2?'2':'3').'_OK'.PHP_EOL;
} finally {
    if(is_file($file)||is_link($file))unlink($file);
    foreach(glob($dir.'/.modxmcp-*.tmp') as $tmp)unlink($tmp);
    rmdir($dir);
    unlink($external);
    if($createdRoot)rmdir($root);
}
