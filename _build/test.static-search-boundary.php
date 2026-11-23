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
    public $getContentCalls=0;
    public function __construct($file){$this->file=$file;}
    public function get($name){
        switch($name){
            case 'static':return 1;
            case 'static_file':return $this->file;
            case 'name':return 'static-fixture';
            case 'id':return 77;
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
                return array($this->chunk);
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
    echo 'STATIC_SEARCH_BOUNDARY_OK checks=4'.PHP_EOL;
}finally{
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
