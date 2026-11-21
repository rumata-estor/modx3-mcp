<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Некорректный path не должен создавать папку вне целевого каталога.
 * EN: An unexpected path parameter must never create a folder at root.
 */
namespace ModxMcp\Tools {
    interface ToolInterface {
        public function name();public function group();public function isMutation();
        public function supports($context);public function execute($context,array $data);
    }
    class MediaSourceMutationSupport {
        public static $calls=0;
        public static function resolveForFileOperation($context,$data) {
            self::$calls++;throw new \RuntimeException('RESOLVER_CALLED');
        }
    }
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/MediaFolderCreateTool.php';
}
namespace {
class ModxMCPClientException extends \RuntimeException {}
class MediaFolderFakeContext {
    public function modx(){return new \stdClass();}
}
$tool=new \ModxMcp\Tools\MediaFolderCreateTool();
try{
    $tool->execute(new MediaFolderFakeContext(),array(
        'source'=>1,'path'=>'assets/images','name'=>'products'
    ));
    throw new \RuntimeException('MEDIA_FOLDER_SILENT_PATH_IGNORED');
}catch(ModxMCPClientException $e){
    if(strpos($e->getMessage(),'use parent')===false)throw $e;
}
if(\ModxMcp\Tools\MediaSourceMutationSupport::$calls!==0)
    throw new \RuntimeException('MEDIA_FOLDER_SIDE_EFFECT_BEFORE_VALIDATION');
echo 'MEDIA_FOLDER_UNKNOWN_PATH_REJECTED_OK'.PHP_EOL;
}
