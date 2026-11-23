<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Медиа-CAS только через штатный MODX Media Source, без прямых файлов.
 * EN: Native-only media CAS: refuse before updateObject on mismatch.
 */
namespace ModxMcp\Tools {
    class ProcessorMutationTool {
        public static function assertAclMutationFields($action,array $data){}
    }
    interface ToolInterface {
        public function name(); public function group(); public function isMutation();
        public function supports($context); public function execute($context,array $data);
    }
    class MediaSourceMutationSupport {
        public static $source;
        public static function resolveForFileOperation($context,array $data){
            return self::$source;
        }
        public static function relativePath($path,$root=false){return $path;}
        public static function refresh($context){}
        public static function error($source,$fallback){return $fallback;}
    }
    class AuditSupport {
        public static function log($context,$name,$type,$data){}
    }
    class MediaTestSource {
        public $contents='ORIGINAL';
        public $updates=0;
        public $reads=0;
        public $changeOnRead=0;
        public function getObjectContents($path){
            $this->reads++;
            if($this->changeOnRead===$this->reads) $this->contents='EXTERNAL';
            return array('content'=>$this->contents,'path'=>$path);
        }
        public function updateObject($path,$content){
            $this->updates++;
            $this->contents=$content;
            return true;
        }
        public function get($field){return $field==='id'?17:null;}
    }
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/MediaFileUpdateTool.php';
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/MutationPreflight.php';
}
namespace {
class ModxMCPClientException extends \RuntimeException {}
class MockModx {}
class MockContext {
    public function modx(){return new MockModx();}
}
function mcheck($ok,$reason){if(!$ok)throw new \RuntimeException('P1_MEDIA_CAS_FAIL '.$reason);}
function mdeny($callback,$code){
    try {$callback();}
    catch(ModxMCPClientException $e){
        mcheck(strpos($e->getMessage(),$code.':')===0,'wrong error code '.$e->getMessage());
        return;
    }
    throw new \RuntimeException('P1_MEDIA_CAS_FAIL expected '.$code);
}
$src=new \ModxMcp\Tools\MediaTestSource();
\ModxMcp\Tools\MediaSourceMutationSupport::$source=$src;
$ctx=new MockContext();
$tool=new \ModxMcp\Tools\MediaFileUpdateTool();
$data=array('source'=>'17','path'=>'test.jpg','content'=>'UPDATED');
mdeny(function()use($tool,$ctx,$data){$tool->execute($ctx,$data);},'MANUAL_REVIEW_REQUIRED');
mcheck($src->updates===0,'no digest must not write');
$stale=$data+array('expected_sha256'=>hash('sha256','OLD'));
mdeny(function()use($tool,$ctx,$stale){$tool->execute($ctx,$stale);},'STALE_STATE');
mcheck($src->updates===0,'stale digest must not write');
$valid=$data+array('expected_sha256'=>hash('sha256','ORIGINAL'));
$src->changeOnRead=$src->reads+2;
mdeny(function()use($tool,$ctx,$valid){
    \ModxMcp\Tools\MediaFileUpdateTool::assertFresh(
        \ModxMcp\Tools\MediaSourceMutationSupport::$source,'test.jpg',$valid
    );
    $tool->execute($ctx,$valid);
},'STALE_STATE');
mcheck($src->updates===0,'external edit after preflight must not be overwritten');
$src->contents='ORIGINAL';$src->changeOnRead=0;
$done=$tool->execute($ctx,$valid);
mcheck($done['updated']===true && $src->contents==='UPDATED' && $src->updates===1,
    'current digest should permit standard native update');
// Preflight must reject missing digest before rev reservation, even direct HTTP.
mdeny(function()use($ctx,$data){
    \ModxMcp\Tools\MutationPreflight::assertAllowed($ctx,'update_media_file',$data);
},'MANUAL_REVIEW_REQUIRED');
echo 'P1_MEDIA_NATIVE_CAS_STOP_OK checks=5'.PHP_EOL;
}
