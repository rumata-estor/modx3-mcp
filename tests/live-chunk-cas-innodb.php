<?php
/** Real MariaDB/InnoDB CAS regression using the actual MODX chunk schema.
 * CI ONLY: isolated DB with disposable ROWS in the existing chunk table.
 * Never run against any configured/remote/live CMS instance.
 */
require_once __DIR__ . '/../core/components/modxmcp/endpoint/chunk-cas.php';
$host = getenv('MODXMCP_CI_DB_HOST');
$dbname = getenv('MODXMCP_CI_DB_NAME');
$user = getenv('MODXMCP_CI_DB_USER');
$password = getenv('MODXMCP_CI_DB_PASSWORD');
if ($host !== '127.0.0.1' || $dbname !== 'modx_build' || $user !== 'modx_ci' ||
    $password !== 'ci-throwaway-only-user' || getenv('GITHUB_ACTIONS') !== 'true') {
    throw new RuntimeException('Refuse any non-disposable CI database');
}
function casCiPdo() {
    return new PDO('mysql:host=127.0.0.1;dbname=modx_build;charset=utf8mb4',
        'modx_ci', 'ci-throwaway-only-user', array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
        ));
}
$table = 'modx_site_htmlsnippets';
$db = casCiPdo();
if (isset($argv[1]) && $argv[1] === 'worker') {
    if (count($argv) !== 6) { throw new RuntimeException('invalid worker args'); }
    $id = (int)$argv[2];
    $before = base64_decode($argv[3], true);
    $after = base64_decode($argv[4], true);
    $start = (float)$argv[5];
    if (!$id || $before === false || $after === false) {
        throw new RuntimeException('invalid worker state');
    }
    $seconds = max(0.0, $start - microtime(true));
    if ($seconds > 0) { usleep((int)($seconds * 1000000)); }
    try {
        ModxMcpChunkCas::compareAndSwap($db, $table, $id, $before, $after);
        echo "WIN\n";
    } catch (RuntimeException $expectedConflict) {
        // Only a failed CAS precondition counts as a loser. SQL/transport
        // failures must fail the regression, not masquerade as safe contention.
        if ($expectedConflict->getMessage() !== 'chunk CAS precondition failed or result ambiguous') {
            throw $expectedConflict;
        }
        echo "LOSE\n";
    }
    exit(0);
}

function checkCas($ok, $label) {
    if (!$ok) { throw new RuntimeException('real InnoDB CAS check failed: ' . $label); }
}
function mustRejectRealCas($db, $table, $id, $before, $after) {
    try {
        ModxMcpChunkCas::compareAndSwap($db, $table, $id, $before, $after);
        throw new LogicException('unexpected CAS success');
    } catch (RuntimeException $expected) {
        return true;
    }
}
// No new tables, not even in CI: exercise existing MODX chunk storage.
$created=array();
try {
    $engine = $db->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'modx_build' AND TABLE_NAME = '".$table."'")->fetchColumn();
    checkCas(strcasecmp($engine, 'InnoDB') === 0, 'real InnoDB table');
    $insert = $db->prepare('INSERT INTO `'.$table.'` (`name`,`snippet`,`static`) VALUES (?,?,?)');
    $get = $db->prepare('SELECT `snippet`,`static` FROM `'.$table.'` WHERE `id`=?');
    $getRow = function($id) use ($get) { $get->execute(array($id)); return $get->fetch(PDO::FETCH_ASSOC); };
    $insert->execute(array('CAS_Synthetic_Pilot_'.bin2hex(random_bytes(6)), "Alpha\n", 0));
    $id=(int)$db->lastInsertId();checkCas($id>0,'chunk inserted');
    $created[]=$id;
    checkCas(ModxMcpChunkCas::compareAndSwap($db,$table,$id,"Alpha\n","Beta\n")===true,'initial write');
    checkCas($getRow($id)['snippet']==="Beta\n",'exact after');
    mustRejectRealCas($db,$table,$id,"Alpha\n","stale lost update");
    mustRejectRealCas($db,$table,$id,"beta\n","case-insensitive collation bypass");
    mustRejectRealCas($db,$table,$id,"Beta\n ","trailing space bypass");
    checkCas($getRow($id)['snippet']==="Beta\n",'rejected writes preserved');
    $db->prepare('UPDATE `'.$table.'` SET `static`=1 WHERE `id`=?')->execute(array($id));
    mustRejectRealCas($db,$table,$id,"Beta\n","STATIC DENIED");
    checkCas($getRow($id)['snippet']==="Beta\n",'static row did not mutate');
    $db->prepare('UPDATE `'.$table.'` SET `static`=0 WHERE `id`=?')->execute(array($id));
    checkCas(ModxMcpChunkCas::compareAndSwap($db,$table,$id,"Beta\n","Unicode Ещё 😀\n")===true,'Unicode');
    checkCas($getRow($id)['snippet']==="Unicode Ещё 😀\n",'Unicode exact bytes');

    $insert->execute(array('CAS_Concurrent_Synthetic_'.bin2hex(random_bytes(6)), 'same-before', 0));
    $raceId=(int)$db->lastInsertId();
    $created[]=$raceId;
    $start=microtime(true)+0.6;
    $processes=array();
    foreach (array('parallel-first','parallel-second') as $desired) {
        $cmd=array(PHP_BINARY, __FILE__, 'worker', (string)$raceId,
                   base64_encode('same-before'), base64_encode($desired), (string)$start);
        $spec=array(0=>array('pipe','r'),1=>array('pipe','w'),2=>array('pipe','w'));
        $proc=proc_open($cmd, $spec, $pipes);
        checkCas(is_resource($proc),'spawn concurrent writer');
        fclose($pipes[0]);
        $processes[]=array($proc,$pipes);
    }
    $outcomes=array();
    foreach ($processes as $process) {
        $output=trim(stream_get_contents($process[1][1]));
        $errors=trim(stream_get_contents($process[1][2]));
        fclose($process[1][1]);fclose($process[1][2]);
        $code=proc_close($process[0]);
        checkCas($code===0,'concurrent process exit: '.$errors);
        $outcomes[]=$output;
    }
    sort($outcomes);
    checkCas($outcomes===array('LOSE','WIN'),'exactly one concurrent writer succeeds: '.implode(',',$outcomes));
    $winner=$getRow($raceId)['snippet'];
    checkCas(in_array($winner,array('parallel-first','parallel-second'),true),'exact winner persisted');
    echo "REAL_INNODB_CHUNK_CAS_PASS engine=$engine; two concurrent processes; one winner\n";
} finally {
    // Only rows created by this test are removed; schema is untouched.
    $remove=$db->prepare('DELETE FROM `'.$table.'` WHERE id=?');
    foreach ($created as $createdId) { $remove->execute(array($createdId)); }
}
