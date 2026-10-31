<?php
/* In-memory regression for category lifecycle on MODX 2 and 3 adapters. */
$src = dirname(__DIR__) . '/core/components/modxmcp/src';
require_once $src . '/Autoloader.php';
\ModxMcp\Autoloader::register($src);
class ModxMCPClientException extends Exception {}

class CatTestResponse {
    private $object;
    public function __construct($object) { $this->object = $object; }
    public function isError() { return false; }
    public function getObject() { return $this->object; }
    public function getMessage() { return ''; }
    public function hasFieldErrors() { return false; }
}
class CatTestObj {
    public $data;
    public function __construct($data) { $this->data = $data; }
    public function get($name) { return isset($this->data[$name]) ? $this->data[$name] : null; }
    public function set($name, $value) { $this->data[$name] = $value; }
    public function save() { return true; }
}
class CatTestCache { public function refresh() {} }
class CatTestLexicon { public function load() {} }
class CatTestModx {
    public $store = array();
    public $lexicon;
    public $user = null;
    private $cache;
    public function __construct() { $this->lexicon = new CatTestLexicon(); $this->cache = new CatTestCache(); }
    public function getObject($class, $id) {
        return isset($this->store[$class][(int)$id]) ? $this->store[$class][(int)$id] : null;
    }
    public function getCount($class, $criteria = array()) {
        $n = 0;
        foreach (isset($this->store[$class]) ? $this->store[$class] : array() as $obj) {
            $ok = true;
            foreach ($criteria as $key => $value) {
                if ((string)$obj->get($key) !== (string)$value) { $ok = false; break; }
            }
            if ($ok) { $n++; }
        }
        return $n;
    }
    public function getCacheManager() { return $this->cache; }
    public function getOption($name, $options = null, $default = null) {
        return $name === 'modxmcp.audit_log' ? false : $default;
    }
}
class CatTestPlatform {
    private $prefix;
    public function __construct($prefix) { $this->prefix = $prefix; }
    public function className($type) { return $this->prefix . $type; }
    public function runProcessor($modx, $processor, array $data = array()) {
        $class = $this->className('category');
        if ($processor === 'element/category/create') {
            $id = count(isset($modx->store[$class]) ? $modx->store[$class] : array()) + 1;
            while (isset($modx->store[$class][$id])) { $id++; }
            $data['id'] = $id;
            $modx->store[$class][$id] = new CatTestObj($data);
            return new CatTestResponse($data);
        }
        if ($processor === 'element/category/update') {
            $id = (int)$data['id'];
            if (!isset($modx->store[$class][$id])) { throw new Exception('Category missing'); }
            foreach ($data as $field => $value) { $modx->store[$class][$id]->set($field, $value); }
            return new CatTestResponse($data);
        }
        if ($processor === 'element/category/remove') {
            unset($modx->store[$class][(int)$data['id']]);
            return new CatTestResponse($data);
        }
        throw new Exception('Unexpected processor: ' . $processor);
    }
}
class CatTestContext {
    private $modx;
    private $platform;
    public function __construct($prefix) { $this->modx = new CatTestModx(); $this->platform = new CatTestPlatform($prefix); }
    public function modx() { return $this->modx; }
    public function platform() { return $this->platform; }
}
function catAssert($test, $message) { if (!$test) { throw new Exception($message); } }
function catReject($callback, $message) {
    try { $callback(); } catch (ModxMCPClientException $e) { return; }
    throw new Exception('Expected rejection: ' . $message);
}

use ModxMcp\Tools\CategorySupport as C;
foreach (array('modx2:' => 'modx2:', 'modx3:' => 'modx3:') as $platform => $prefix) {
    $c = new CatTestContext($prefix);
    $parent = C::create($c, array('name' => 'Parent', 'parent' => 0));
    $parentId = $parent['id'];
    $child = C::create($c, array('name' => 'Child', 'parent' => $parentId));
    $childId = $child['id'];
    catAssert($parentId > 0 && $childId > 0, 'Category ids');
    catAssert(C::get($c, array('id' => $parentId))['usages']['child_categories'] === 1, 'Children counted');
    catReject(function () use ($c, $parentId, $childId) {
        C::update($c, array('id' => $parentId, 'parent' => $childId));
    }, 'Cycle');
    catReject(function () use ($c, $parentId) {
        C::delete($c, array('id' => $parentId, 'dry_run' => false));
    }, 'Nonempty parent');
    catReject(function () use ($c, $childId) {
        C::delete($c, array('id' => $childId));
    }, 'Explicit dry_run');
    $updated = C::update($c, array('id' => $childId, 'name' => 'Renamed', 'parent' => 0, 'rank' => 2));
    catAssert($updated['name'] === 'Renamed' && $updated['parent'] === 0, 'Move and rename');
    $snippetClass = $c->platform()->className('snippet');
    $c->modx()->store[$snippetClass][8] = new CatTestObj(array('id' => 8, 'name' => 'keepCode', 'snippet' => 'return 42;', 'category' => 0));
    C::assignElement($c, array('type' => 'snippet', 'id' => 8, 'category_id' => $childId));
    catAssert($c->modx()->store[$snippetClass][8]->get('snippet') === 'return 42;', 'Source code intact');
    catAssert(C::get($c, array('id' => $childId))['usages']['snippet'] === 1, 'Assigned element counted');
    catReject(function () use ($c, $childId) {
        C::delete($c, array('id' => $childId, 'dry_run' => false));
    }, 'Assigned element blocks deletion');
    catReject(function () use ($c) {
        C::validateElementCategory($c, 'resource', 0);
    }, 'Resource is not a modCategory element');
    C::assignElement($c, array('type' => 'snippet', 'id' => 8, 'category_id' => 0));
    catAssert(C::delete($c, array('id' => $childId, 'dry_run' => true))['can_delete'], 'Dry-run empty');
    catAssert(C::delete($c, array('id' => $childId, 'dry_run' => false))['deleted'], 'Delete empty category');
    catAssert(C::delete($c, array('id' => $parentId, 'dry_run' => false))['deleted'], 'Delete previous parent');
    echo 'CATEGORY_MANAGEMENT_OK ' . $platform . PHP_EOL;
}
