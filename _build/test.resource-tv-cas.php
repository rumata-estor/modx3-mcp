<?php
// Isolated cross-platform contract test for ResourceTvUpdateTool.
// Uses platform-neutral MODX doubles; live MODX 2/3 testing is still required.
namespace { class ModxMCPClientException extends \RuntimeException {} }

namespace ModxMcp\Tools {
  interface ToolInterface {
    public function name();
    public function group();
    public function isMutation();
    public function supports($context);
    public function execute($context, array $data);
  }
  class AuditSupport {
    public static $entries = array();
    public static function log($context, $name, $type, $info) {
      self::$entries[] = array($name, $type, $info);
    }
  }
  class ResourceTvListTool {
    public function execute($context, array $args) {
      $resource = $context->modx()->resource;
      return array('resource_id' => $args['resource_id'], 'tvs' => $resource->values);
    }
  }
  class FakeResource {
    public $values = array('primary' => '111', 'secondary' => '222');
    public $failName = null;
    public function get($key) { return $key === 'template' ? 5 : null; }
    // getTVValue in MODX core calls renderOutput() returning formatted/rendered HTML
    public function getTVValue($name) { return isset($this->values[$name]) ? '<b>' . $this->values[$name] . '</b>' : null; }
    public function getTemplateVars() {
      $list = array();
      foreach ($this->values as $name => $val) {
        $id = ($name === 'primary') ? 1 : 2;
        $tv = new FakeTV($id, null, $name);
        $tv->loadedValue = (string)$val;
        $list[] = $tv;
      }
      return $list;
    }
    public function setTVValue($name, $value) {
      if ($name === $this->failName) { return false; }
      $this->values[$name] = $value;
      return true;
    }
  }
  class FakeTV {
    private $id;
    private $modx;
    private $name;
    public $loadedValue = null;
    public function __construct($id, $modx = null, $name = '') {
      $this->id = $id;
      $this->modx = $modx;
      $this->name = $name;
    }
    public function get($key) {
      if ($key === 'id') return $this->id;
      if ($key === 'name') return $this->name;
      if ($key === 'caption') return $this->name;
      if ($key === 'type') return 'text';
      if ($key === 'value') return $this->loadedValue;
      return null;
    }
    public function getValue($resourceId = 0) {
      return $this->modx && isset($this->modx->resource->values[$this->name])
        ? $this->modx->resource->values[$this->name] : $this->loadedValue;
    }
    public function setValue($resourceId, $value) {
      if ($this->modx) {
        if ($this->name === $this->modx->resource->failName) { return false; }
        $this->modx->resource->values[$this->name] = $value;
      }
      return true;
    }
    public function save() {
      if ($this->modx && $this->name === $this->modx->resource->failName) {
        return false;
      }
      return true;
    }
  }
  class FakeLink {}
  class FakePlatform {
    public function className($name) { return $name; }
  }
  class FakeCache {
    public $refreshes=0;
    public function refresh() {$this->refreshes++;}
  }
  class FakeModx {
    public $resource;
    public $cacheManager;
    public $linked = array('primary'=>1,'secondary'=>2);
    public $assigned = array(1,2);
    private $saved;
    public $rollbackCount=0;
    public $commitCount=0;
    public function __construct() {
      $this->resource=new FakeResource();
      $this->cacheManager=new FakeCache();
    }
    public function getObject($type, $id) {
      if ($type==='resource') return $id===1 ? $this->resource : null;
      if ($type==='tv') return isset($this->linked[$id['name']])
        ? new FakeTV($this->linked[$id['name']], $this, $id['name']) : null;
      if ($type==='template_var_template') return
        $id['templateid']===5 && in_array($id['tmplvarid'], $this->assigned, true)
          ? new FakeLink() : null;
      return null;
    }
    public function beginTransaction() { $this->saved=$this->resource->values; }
    public function commit() { $this->commitCount++; $this->saved=null; }
    public function rollback() {
      $this->rollbackCount++;
      $this->resource->values=$this->saved;
    }
  }
  class FakeContext {
    private $modx;
    private $platform;
    public function __construct($modx) { $this->modx=$modx; $this->platform=new FakePlatform(); }
    public function modx() { return $this->modx; }
    public function platform() { return $this->platform; }
  }
}
namespace {
  require_once __DIR__ . '/../core/components/modxmcp/src/Tools/ResourceTvUpdateTool.php';
  $tool=new \ModxMcp\Tools\ResourceTvUpdateTool();
  $modx=new \ModxMcp\Tools\FakeModx();
  $ctx=new \ModxMcp\Tools\FakeContext($modx);
  function demand($yes, $message) {
    if (!$yes) throw new \RuntimeException($message);
  }
  function mustFail($callback, $contains) {
    try { $callback(); }
    catch (\ModxMCPClientException $ex) {
      demand(strpos($ex->getMessage(), $contains)!==false, $ex->getMessage());
      return;
    }
    throw new \RuntimeException('Expected error: ' . $contains);
  }
  $guard=array('observed_site_revision'=>5,'strict_site_revision'=>true,
     'expected_tvs'=>array('primary'=>'111','secondary'=>'222'));
  $result=$tool->execute($ctx,array('resource_id'=>1,
    'tvs'=>array('primary'=>'333','secondary'=>'444'),
    '_runtime_preconditions'=>$guard));
  demand($result['tvs']['primary']==='333' && $result['tvs']['secondary']==='444',
     'TV updates failed');
  demand($modx->commitCount===1 && $modx->cacheManager->refreshes===1,
     'transaction/cache did not commit');
  $prior=$modx->resource->values;
  mustFail(function()use($tool,$ctx,$guard){
    $tool->execute($ctx,array('resource_id'=>1,'tvs'=>array('primary'=>'777','secondary'=>'888'),
      '_runtime_preconditions'=>$guard));
  },'STALE_STATE');
  demand($modx->resource->values===$prior,'Stale value changed site');
  mustFail(function()use($tool,$ctx){
    $tool->execute($ctx,array('resource_id'=>1,'tvs'=>array('absent'=>'777')));
  },'Unknown TV');
  $modx->linked['unassigned']=3;
  mustFail(function()use($tool,$ctx){
    $tool->execute($ctx,array('resource_id'=>1,'tvs'=>array('unassigned'=>'777')));
  },'not assigned');
  $modx->resource->failName='secondary';
  mustFail(function()use($tool,$ctx){
    $tool->execute($ctx,array('resource_id'=>1,
      'tvs'=>array('primary'=>'777','secondary'=>'888')));
  },'Failed to write TV');
  demand($modx->rollbackCount===1 && $modx->resource->values===$prior,
     'Partial write was not rolled back');
  mustFail(function()use($tool,$ctx){
    $tool->execute($ctx,array('resource_id'=>1,'tvs'=>array('primary'=>777)));
  },'string values');
  echo "RESOURCE_TV_PHP_CONTRACT_OK\n";
}
