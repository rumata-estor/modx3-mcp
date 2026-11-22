<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Независимое MCP-чтение лексикона в новом disposable PHP-процессе.
 * EN: Disposable lexicon readback in an isolated fresh PHP process.
 */
if (PHP_SAPI!=='cli' || getenv('MODXMCP_DISPOSABLE_CI')!=='1'
    || strpos((string)getenv('MODX_CONFIG_CORE'),'/home/runner/')!==0
    || !is_file((string)getenv('MODX_CONFIG_CORE'))
    || !isset($argv[1],$argv[2])
    || !preg_match('/^mcpdisposablelex[a-f0-9]{10}$/D',$argv[1])
    || !preg_match('/^mcp_cas_lex_[a-f0-9]{10}$/D',$argv[2])){
    fwrite(STDERR,"Refusing non-disposable lexicon readback.\n");exit(3);
}
require getenv('MODX_CONFIG_CORE');
$is2=!is_file(MODX_CORE_PATH.'src/Revolution/modX.php');
if($is2){
    require MODX_CORE_PATH.'model/modx/modx.class.php';
    $modx=new modX();
}else{
    require MODX_CORE_PATH.'vendor/autoload.php';
    $modx=\MODX\Revolution\modX::getInstance();
}
$modx->initialize('mgr');
$modx->setOption('modxmcp.disabled_groups','');
require MODX_CORE_PATH.'components/modxmcp/model/modxmcp.class.php';
$api=new modxMCP($modx);
$list=$api->processRequest('list_lexicon_entries','',array(
    'namespace'=>$argv[1],'topic'=>'default','language'=>'en',
    'search'=>$argv[2],'limit'=>100));
if(!isset($list['results']) || !is_array($list['results'])){
    fwrite(STDERR,"Lexicon MCP list readback unavailable.\n");exit(4);
}
$matched=array();
foreach($list['results'] as $entry){
    if(is_array($entry) && ($entry['name']??'')===$argv[2]){
        $matched[]=$entry;
    }
}
echo json_encode($matched,JSON_UNESCAPED_SLASHES).PHP_EOL;
