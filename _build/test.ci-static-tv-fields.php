<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Статические поля TV проверяются только на disposable MODX в CI.
 * EN: Exercise static TV metadata on real disposable MODX 2/3 via MCP.
 */
if (PHP_SAPI !== 'cli' || getenv('MODXMCP_DISPOSABLE_CI') !== '1'
    || strpos((string)getenv('MODX_CONFIG_CORE'), '/home/runner/') !== 0
    || !is_file((string)getenv('MODX_CONFIG_CORE'))) {
    fwrite(STDERR,"Refusing static TV integration outside disposable CI.\n");
    exit(3);
}
require_once getenv('MODX_CONFIG_CORE');
$is2 = !is_file(MODX_CORE_PATH . 'src/Revolution/modX.php');
if($is2){
    require_once MODX_CORE_PATH.'model/modx/modx.class.php';
    $modx=new modX();
}else{
    require_once MODX_CORE_PATH.'vendor/autoload.php';
    $modx=\MODX\Revolution\modX::getInstance();
}
$modx->initialize('mgr');
require_once MODX_CORE_PATH.'components/modxmcp/model/modxmcp.class.php';
require_once __DIR__.'/test.cas-mcp-helper.php';
$api=new modxMCP($modx);
$fixture=bin2hex(random_bytes(6));
$name='MCP_StaticTV_'.$fixture;
$id=0;
$checks=0;
function stvRpc($action,array $data=array()){
    global $api;
    return disposableCasRpc($api,$action,$data);
}
function stvCheck($condition,$label){
    global $checks;
    if(!$condition)throw new RuntimeException('STATIC_TV_LIVE_FAILED: '.$label);
    $checks++;
}
try {
    // RU: Невалидный путь должен отклоняться до штатного TV-процессора.
    // EN: Unsafe static_file path must fail before native TV processors.
    $denied=false;
    try {stvRpc('create_element',array(
        'type'=>'tv','name'=>$name.'_bad','field_type'=>'text',
        'default_text'=>'X','static'=>1,'static_file'=>'../../secret.txt'
    ));}catch(ModxMCPClientException $e){$denied=true;}
    stvCheck($denied,'unsafe static TV path rejected');

    // RU: Обычная TV не создаёт реальный файл; тестируем только метаданные.
    // EN: Static flag remains disabled: test metadata, not filesystem creation.
    $path='core/elements/tvs/mcp-static-'.$fixture.'.tpl';
    $created=stvRpc('create_element',array(
        'type'=>'tv','name'=>$name,'caption'=>$name,
        'field_type'=>'text','default_text'=>'DEFAULT_'.$fixture,
        'static'=>0,'static_file'=>$path
    ));
    $id=(int)($created['id']??0);
    stvCheck($id>0,'temporary TV created via guarded MCP');
    $read=stvRpc('get_element',array('type'=>'tv','id'=>$id));
    stvCheck(($read['name']??null)===$name,'TV readback name');
    stvCheck((string)($read['static_file']??'')===$path,
        'TV static_file persisted through processor');
    stvCheck((int)($read['static']??-1)===0,
        'TV remained nonstatic without unexpected file creation');
    stvCheck((string)($read['default_text']??'')==='DEFAULT_'.$fixture,
        'TV default text preserved');

    // RU: Изменение только default_text не должно терять путь.
    // EN: Partial TV update must preserve static metadata.
    stvRpc('update_element',array(
        'type'=>'tv','id'=>$id,'default_text'=>'UPDATED_'.$fixture,
    ));
    $updated=stvRpc('get_element',array('type'=>'tv','id'=>$id));
    stvCheck((string)($updated['default_text']??'')==='UPDATED_'.$fixture,
        'partial TV update persisted');
    stvCheck((string)($updated['static_file']??'')===$path,
        'TV static_file preserved during partial update');
    echo 'STATIC_TV_DISPOSABLE_ACCEPTANCE_OK platform='
        .($is2?'modx2':'modx3').' checks='.$checks.PHP_EOL;
} finally {
    if($id>0)stvRpc('delete_element',array('type'=>'tv','id'=>$id));
}
