<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Поиск по статическим элементам не может читать файл вне core/elements.
 * EN: Static search must reject external symlinks and avoid getContent().
 */
namespace ModxMcp\Tools {
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/FilesystemSupport.php';
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/StaticFileWriteSupport.php';
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/ElementContentSupport.php';
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/SearchSupport.php';
}
namespace {
class ModxMCPClientException extends \RuntimeException {}
class SearchFakeQuery {
    public $conditions=array();
    public function where($value){$this->conditions[]=$value;return $this;}
    public function limit($value){return $this;}
}
class SearchFakeChunk {
    public $file;
    public $id;
    public $getContentCalls=0;
    public function __construct($file,$id=77){$this->file=$file;$this->id=$id;}
    public function get($name){
        switch($name){
            case 'static':return 1;
            case 'static_file':return $this->file;
            case 'name':return 'static-fixture';
            case 'id':return $this->id;
            case 'snippet':return 'DB_FALLBACK';
        }
        return null;
    }
    public function getContent(){
        $this->getContentCalls++;
        throw new \RuntimeException('UNSAFE_GETCONTENT_INVOKED');
    }
}
class SearchFakeModx {
    public $root;public $chunk;
    public function __construct($root,$chunk){$this->root=$root;$this->chunk=$chunk;}
    public function getOption($name){
        if($name==='core_path')return $this->root.'/core/';
        if($name==='base_path')return $this->root.'/';
        if($name==='assets_path')return $this->root.'/assets/';
        return null;
    }
    public function newQuery($class){return new SearchFakeQuery();}
    public function getCollection($class,$query){
        if($class!=='FakeChunk')return array();
        foreach($query->conditions as $condition){
            if(is_array($condition) && ($condition['static']??null)===1){
                return is_array($this->chunk)?$this->chunk:array($this->chunk);
            }
        }
        return array();
    }
}
class SearchFakePlatform {
    public function className($name){return $name==='chunk'?'FakeChunk':$name;}
}
class SearchFakeContext {
    public $m;public $p;
    public function __construct($root,$chunk){
        $this->m=new SearchFakeModx($root,$chunk);
        $this->p=new SearchFakePlatform();
    }
    public function modx(){return $this->m;}
    public function platform(){return $this->p;}
}
function searchAssert($ok,$message){
    if(!$ok)throw new \RuntimeException('STATIC_SEARCH_BOUNDARY_FAILED: '.$message);
}
$root=sys_get_temp_dir().'/modxmcp-search-boundary-'.bin2hex(random_bytes(5));
mkdir($root.'/core/elements/chunks',0700,true);
mkdir($root.'/outside',0700,true);
file_put_contents($root.'/core/elements/chunks/safe.tpl','SAFE_SEARCH_NEEDLE');
file_put_contents($root.'/outside/secret.txt','PRIVATE_SEARCH_NEEDLE');
symlink($root.'/outside',$root.'/core/elements/chunks/escape');
$chunk=new SearchFakeChunk('core/elements/chunks/safe.tpl');
$ctx=new SearchFakeContext($root,$chunk);
try{
    $safe=\ModxMcp\Tools\SearchSupport::search($ctx,array(
        'query'=>'SAFE_SEARCH_NEEDLE','types'=>array('chunk'),
        'case_sensitive'=>true,
    ));
    searchAssert(($safe['count']??0)===1,'valid static chunk not found');
    searchAssert($chunk->getContentCalls===0,'getContent called on safe static chunk');
    $chunk->file='core/elements/chunks/escape/secret.txt';
    $denied=false;
    try{\ModxMcp\Tools\SearchSupport::search($ctx,array(
        'query'=>'PRIVATE_SEARCH_NEEDLE','types'=>array('chunk'),
        'case_sensitive'=>true,
    ));}catch(ModxMCPClientException $e){$denied=true;}
    searchAssert($denied,'external symlink was read by static search');
    searchAssert($chunk->getContentCalls===0,'unsafe getContent invoked after path failure');
    $hard=$root.'/core/elements/chunks/hard.tpl';
    link($root.'/outside/secret.txt',$hard);
    $ctx->m->chunk=array($chunk,
        new SearchFakeChunk('core/elements/chunks/hard.tpl',78),
        new SearchFakeChunk('core/elements/chunks/missing.tpl',79),
        new SearchFakeChunk('assets/external.tpl',80),
        new SearchFakeChunk('core/elements/chunks/safe.tpl',81));
    $partial=\ModxMcp\Tools\SearchSupport::search($ctx,array(
        'query'=>'SAFE_SEARCH_NEEDLE','types'=>array('chunk'),'case_sensitive'=>true),true);
    searchAssert($partial['analysis_complete']===false && $partial['unavailable_count']===4,'coverage unavailable');
    searchAssert($partial['count']===1 && $partial['results'][0]['id']===81,'later safe hit retained');
    searchAssert($partial['unavailable_elements'][0]===array('type'=>'chunk','id'=>77,'code'=>'STATIC_PATH_OUTSIDE_ALLOWED_ROOT'),'bounded safe diagnostic');
    searchAssert(strpos(json_encode($partial),'PRIVATE_SEARCH_NEEDLE')===false && strpos(json_encode($partial),$root)===false,'no secret or path leak');
    $missing=\ModxMcp\Tools\SearchSupport::search($ctx,array('query'=>'DB_FALLBACK','types'=>array('chunk')),true);
    searchAssert($missing['count']===0 && !$missing['analysis_complete'],'no false hit from stale DB fallback');
    $limited=\ModxMcp\Tools\SearchSupport::search(new SearchFakeContext($root,new SearchFakeChunk('core/elements/chunks/safe.tpl')),array('query'=>'SAFE_SEARCH_NEEDLE','types'=>array('chunk'),'limit'=>1),true);
    searchAssert($limited['limit_reached'] && !$limited['analysis_complete'],'filled limit not proof of completeness');
    unlink($hard);
    echo 'STATIC_SEARCH_BOUNDARY_OK checks=10'.PHP_EOL;
}finally{
    @unlink($root.'/core/elements/chunks/hard.tpl');
    unlink($root.'/core/elements/chunks/escape');
    unlink($root.'/core/elements/chunks/safe.tpl');
    unlink($root.'/outside/secret.txt');
    rmdir($root.'/core/elements/chunks');
    rmdir($root.'/core/elements');
    rmdir($root.'/core');
    rmdir($root.'/outside');
    rmdir($root);
}
}
