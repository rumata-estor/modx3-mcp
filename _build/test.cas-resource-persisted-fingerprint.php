<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Снимок CAS учитывает все сохранённые столбцы modResource.
 * EN: Target CAS fingerprints cover every persisted base modResource column.
 */
namespace ModxMcp\Tools {
    class ElementGetTool {
        public static $presentation = 'Manager presentation';
        public function execute($context, array $args) {
            if ($args['id'] !== 42) throw new \RuntimeException('Unexpected resource');
            return array(
                'id' => 42, 'pagetitle' => self::$presentation,
                'publishedon' => 'formatted without seconds',
            );
        }
    }
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/StateSupport.php';
}
namespace {
class ModxMCPClientException extends \RuntimeException {}
class PersistedResourceStatement {
    private $db;
    private $id;
    public function __construct($db){$this->db=$db;}
    public function execute($args){$this->id=(int)$args[0];return true;}
    public function fetch($mode=null) {
        return $this->id===42 ? $this->db->row : false;
    }
}
class PersistedResourceDb {
    public $row;
    public function __construct() {
        $this->row=array(
            'id'=>'42','pagetitle'=>'True persisted title',
            'content'=>'Content','published'=>'1',
            'pub_date'=>null,'publishedon'=>'2026-11-23 10:00:25',
            'privateweb'=>'0','privatemgr'=>'0','custom_persisted_column'=>'initial',
        );
    }
    public function getTableName($class) {
        if($class!=='modResource')throw new \RuntimeException('Wrong resource class');
        return 'modx_site_content';
    }
    public function prepare($sql) {
        if(strpos($sql,'SELECT * FROM modx_site_content WHERE ')!==0)
            throw new \RuntimeException('Wrong persisted resource query: '.$sql);
        return new PersistedResourceStatement($this);
    }
}
class PersistedResourcePlatform {
    public function className($type){return $type==='resource'?'modResource':null;}
}
class PersistedResourceContext {
    public $db;
    public $p;
    public function __construct(){
        $this->db=new PersistedResourceDb();
        $this->p=new PersistedResourcePlatform();
    }
    public function modx(){return $this->db;}
    public function platform(){return $this->p;}
}
function assertChanged($a,$b,$label){
    if(hash_equals($a,$b))throw new \RuntimeException('RESOURCE_CAS_DID_NOT_TRACK_'.$label);
}
function assertSameHash($a,$b,$label){
    if(!hash_equals($a,$b))throw new \RuntimeException('RESOURCE_CAS_FALSE_CHANGE_'.$label);
}
$ctx=new PersistedResourceContext();
$db=$ctx->db;
$target=array('role'=>'target','type'=>'resource','id'=>42);
$base=\ModxMcp\Tools\StateSupport::fingerprintReadItem($ctx,$target);
if(!preg_match('/^[0-9a-f]{64}$/D',$base))
    throw new \RuntimeException('RESOURCE_CAS_BAD_DIGEST');
\ModxMcp\Tools\ElementGetTool::$presentation='Another Manager date/text format';
assertSameHash($base,\ModxMcp\Tools\StateSupport::fingerprintReadItem($ctx,$target),
    'manager_presentation');

$db->row['privateweb']='1';
$web=\ModxMcp\Tools\StateSupport::fingerprintReadItem($ctx,$target);
assertChanged($base,$web,'privateweb');
$db->row['privatemgr']='1';
$mgr=\ModxMcp\Tools\StateSupport::fingerprintReadItem($ctx,$target);
assertChanged($web,$mgr,'privatemgr');

$db->row['custom_persisted_column']='manager_edit';
$custom=\ModxMcp\Tools\StateSupport::fingerprintReadItem($ctx,$target);
assertChanged($mgr,$custom,'other_persisted_field');
$db->row['content']="\xFF"; // Non-UTF8 bytes must still be fingerprintable.
$binary=\ModxMcp\Tools\StateSupport::fingerprintReadItem($ctx,$target);
assertChanged($custom,$binary,'non_utf8_content');

$dependency=array('role'=>'dependency','type'=>'resource','id'=>42,
                  'fields'=>array('privateweb'));
$selected=\ModxMcp\Tools\StateSupport::fingerprintReadItem($ctx,$dependency);
$db->row['privatemgr']='0';
assertSameHash($selected,
    \ModxMcp\Tools\StateSupport::fingerprintReadItem($ctx,$dependency),
    'scoped_unrelated_field');
$db->row['privateweb']='0';
assertChanged($selected,
    \ModxMcp\Tools\StateSupport::fingerprintReadItem($ctx,$dependency),
    'scoped_privateweb');

$db->row=false;
try {
    \ModxMcp\Tools\StateSupport::fingerprintReadItem($ctx,$target);
    throw new \RuntimeException('RESOURCE_CAS_MISSING_ROW_ACCEPTED');
} catch (ModxMCPClientException $e) {
    if(strpos($e->getMessage(),'STALE_STATE')!==0)throw $e;
}
echo 'RESOURCE_PERSISTED_FINGERPRINT_OK checks=8'.PHP_EOL;
}