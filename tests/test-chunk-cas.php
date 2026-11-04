<?php
/* Isolated SQL and mock-statement tests only; no database or MODX runtime. */
require_once __DIR__ . '/../core/components/modxmcp/endpoint/chunk-cas.php';
function mustRejectCas($fn, $scenario) {
    try { $fn(); } catch (Exception $e) { return; }
    throw new RuntimeException('CAS unexpectedly allowed: ' . $scenario);
}
$sql = ModxMcpChunkCas::statement('modx_site_content');
if (strpos($sql, 'BINARY `snippet` = BINARY :before') === false ||
    strpos($sql, '`static` = 0') === false ||
    strpos($sql, '`id` = :id') === false) {
    throw new RuntimeException('missing atomic CAS or static chunk boundary');
}
foreach (array('', 'site.content', 'modx`chunk', 'chunks WHERE 1=1',
               'foo;DROP TABLE x', '1invalid') as $table) {
    mustRejectCas(function() use ($table) { ModxMcpChunkCas::statement($table); },
        'untrusted table name');
}
class MockChunkCasStatement {
    public $values;
    public $changes;
    public $ok;
    public function __construct($changes, $ok) { $this->changes=$changes; $this->ok=$ok; }
    public function execute($values) { $this->values=$values; return $this->ok; }
    public function rowCount() { return $this->changes; }
}
class MockChunkCasConnection {
    public $sql;
    public $statement;
    public function __construct($changes, $ok = true) {
        $this->statement=new MockChunkCasStatement($changes, $ok);
    }
    public function prepare($sql) { $this->sql=$sql; return $this->statement; }
}
$db = new MockChunkCasConnection(1);
ModxMcpChunkCas::compareAndSwap($db, 'modx_site_content', 7, "old\n", "NEW\n");
if ($db->sql !== $sql || $db->statement->values !== array(
        ':after'=>"NEW\n", ':id'=>7, ':before'=>"old\n")) {
    throw new RuntimeException('CAS changed exact bytes or parameter binding');
}
foreach (array(0, 2, -1) as $changes) {
    $db=new MockChunkCasConnection($changes);
    mustRejectCas(function() use ($db) {
        ModxMcpChunkCas::compareAndSwap($db,'modx_site_content',7,'before','after');
    }, 'incorrect affected-row count');
}
$db = new MockChunkCasConnection(1, false);
mustRejectCas(function() use ($db) {
    ModxMcpChunkCas::compareAndSwap($db,'modx_site_content',7,'before','after');
}, 'failed execute');
foreach (array(0, -1, '7', true) as $id) {
    mustRejectCas(function() use ($id) {
        ModxMcpChunkCas::compareAndSwap(new MockChunkCasConnection(1),
            'modx_site_content',$id,'old','new');
    }, 'invalid id');
}
mustRejectCas(function() {
    ModxMcpChunkCas::compareAndSwap(new MockChunkCasConnection(1),
        'modx_site_content',7,'same','same');
}, 'ambiguous no-op');
echo "MODX_CHUNK_CAS_PRIMITIVE_OK\n";
