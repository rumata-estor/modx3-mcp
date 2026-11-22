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
$bootstrapChunkId=0;
$generatedStaticPath=null;
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

    // RU: Чистая установка MODX не обязана иметь core/elements.
    // RU: Создаём каталог ТОЛЬКО через штатный make_static MCP на
    // RU: одноразовом чанке, без прямой записи в файлы тестового сайта.
    // EN: A fresh MODX install may lack core/elements. Bootstrap this
    // EN: directory through guarded make_static on a disposable chunk.
    $bootstrapChunk=stvRpc('create_element',array(
        'type'=>'chunk','name'=>'MCPStaticBootstrap_'.$fixture,
        'content'=>'DISPOSABLE_STATIC_BOOTSTRAP_'.$fixture,
    ));
    $bootstrapChunkId=(int)($bootstrapChunk['id']??0);
    stvCheck($bootstrapChunkId>0,'static directory bootstrap chunk created');
    stvRpc('make_static',array('type'=>'chunk','id'=>$bootstrapChunkId));
    stvCheck(is_dir(rtrim((string)$modx->getOption('core_path'),'/').'/elements'),
        'core/elements provisioned via guarded make_static');

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

    // RU: Файловая конверсия TV только через MCP; после создания проверяем
    // RU: байты файла и сохранение static-флагов через независимое чтение.
    // EN: Convert TV via guarded MCP and independently verify file bytes
    // EN: plus persistent TV static metadata on disposable MODX 2/3.
    $conversion=stvRpc('make_static',array('type'=>'tv','id'=>$id));
    $relative=(string)($conversion['static_file']??'');
    stvCheck(strpos($relative,'core/elements/tvs/')===0,
        'TV conversion output remains in approved static directory');
    $generatedStaticPath=rtrim((string)$modx->getOption('base_path'),'/').'/'.$relative;
    stvCheck(is_file($generatedStaticPath),
        'TV static conversion actually wrote a file');
    stvCheck(file_get_contents($generatedStaticPath)==='UPDATED_'.$fixture,
        'TV static conversion copied raw default text');
    $converted=stvRpc('get_element',array('type'=>'tv','id'=>$id));
    stvCheck((int)($converted['static']??0)===1
        && (string)($converted['static_file']??'')===$relative,
        'TV static metadata persisted independently');
    // RU: Просмотр и правка содержимого TV строками через MCP,
    // RU: затем независимое сравнение файла и default_text в MODX.
    // EN: View/edit static TV content through guarded MCP, then compare
    // EN: the static file and the independently read raw TV default.
    $view=stvRpc('view_element',array('type'=>'tv','id'=>$id));
    stvCheck(($view['numbered']??'')==="1\tUPDATED_".$fixture,
        'view_element returns the raw TV static file');
    stvRpc('edit_element_lines',array('type'=>'tv','id'=>$id,'edits'=>array(
        array('start_line'=>1,'end_line'=>1,
            'expect'=>'UPDATED_'.$fixture,'replacement'=>'EDITED_'.$fixture)
    )));
    stvCheck(file_get_contents($generatedStaticPath)==='EDITED_'.$fixture,
        'edit_element_lines changed TV static file');
    $afterEdit=stvRpc('get_element',array('type'=>'tv','id'=>$id));
    stvCheck((string)($afterEdit['default_text']??'')==='EDITED_'.$fixture,
        'TV raw default_text mirrored to MODX DB');
    $staleEditDenied=false;
    try {stvRpc('edit_element_lines',array('type'=>'tv','id'=>$id,
        'edits'=>array(array('start_line'=>1,'end_line'=>1,
            'expect'=>'UPDATED_'.$fixture,'replacement'=>'STALE_EDIT'))));}
    catch(ModxMCPClientException $e){$staleEditDenied=true;}
    stvCheck($staleEditDenied,'stale static TV line anchor denied');
    stvCheck(file_get_contents($generatedStaticPath)==='EDITED_'.$fixture,
        'stale edit left TV static bytes unchanged');

    $deniedOverwrite=false;
    try{stvRpc('make_static',array('type'=>'tv','id'=>$id));}
    catch(ModxMCPClientException $e){
        $deniedOverwrite=strpos($e->getMessage(),'already exists')!==false;
    }
    stvCheck($deniedOverwrite,'second TV conversion refused overwriting its file');
    stvCheck(file_get_contents($generatedStaticPath)==='EDITED_'.$fixture,
        'rejected overwrite preserved edited bytes');
    echo 'STATIC_TV_DISPOSABLE_ACCEPTANCE_OK platform='
        .($is2?'modx2':'modx3').' checks='.$checks.PHP_EOL;
} finally {
    if($id>0)stvRpc('delete_element',array('type'=>'tv','id'=>$id));
    if($generatedStaticPath!==null && is_file($generatedStaticPath)){
        // RU: Тест убирает только файл, созданный в этой disposable CI-сессии.
        // EN: Cleanup only the test's freshly generated disposable TV file.
        unlink($generatedStaticPath);
    }
    if($bootstrapChunkId>0)stvRpc('delete_element',array(
        'type'=>'chunk','id'=>$bootstrapChunkId
    ));
}
