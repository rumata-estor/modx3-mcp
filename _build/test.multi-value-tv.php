<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Тест сериализации многозначных TV без установки MODX.
 * EN: Offline validation of multi-select TV array normalization.
 */
namespace ModxMcp\Tools {
    interface ToolInterface {
        public function name();public function group();public function isMutation();
        public function supports($context);public function execute($context,array $data);
    }
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/ResourceTvUpdateTool.php';
}
namespace {
class ModxMCPClientException extends \RuntimeException {}
class FakeMultiTV {
    private $type;
    public function __construct($type){$this->type=$type;}
    public function get($key){return $key==='type'?$this->type:null;}
}
function shouldReject($tv,$value) {
    try {\ModxMcp\Tools\ResourceTvUpdateTool::normalizeMultiValue($tv,$value);}
    catch(ModxMCPClientException $e){return;}
    throw new \RuntimeException('ARRAY_TV_INCORRECTLY_ALLOWED');
}
$multi=new FakeMultiTV('listbox-multiple');
$single=new FakeMultiTV('text');
if(\ModxMcp\Tools\ResourceTvUpdateTool::normalizeMultiValue(
    $multi,array('18','19'))!=='18||19')throw new \RuntimeException('TV_ARRAY_JOIN_FAILED');
if(\ModxMcp\Tools\ResourceTvUpdateTool::normalizeMultiValue(
    $multi,'18||19')!=='18||19')throw new \RuntimeException('TV_STRING_CHANGED');
shouldReject($single,array('18','19'));
shouldReject($multi,array(array('18')));
shouldReject($multi,array('18||19'));
shouldReject($multi,array(''));
shouldReject($multi,array(null));
echo 'MULTI_VALUE_TV_NORMALIZATION_OK checks=7'.PHP_EOL;
}
