<?php
/**
 * PR #46 real-MODX CAS integration regression, MODX 2 and 3.
 * Runs exclusively on disposable GitHub Actions MODX/MariaDB installations.
 * Original TV/CAS fix: AgelxNash, PR #40, selected into PR #46.
 */
if (PHP_SAPI !== 'cli' || getenv('MODXMCP_DISPOSABLE_CI') !== '1') {
    fwrite(STDERR, "Requires CLI and MODXMCP_DISPOSABLE_CI=1.\n");
    exit(3);
}
$config = getenv('MODX_CONFIG_CORE');
if (!is_string($config) || strpos($config, '/home/runner/') !== 0 || !is_file($config)) {
    fwrite(STDERR, "Refusing to run outside isolated GitHub Actions MODX.\n");
    exit(3);
}
require_once $config;
$is2 = !is_file(MODX_CORE_PATH . 'src/Revolution/modX.php');
if ($is2) {
    require_once MODX_CORE_PATH . 'model/modx/modx.class.php';
    $modx = new modX();
} else {
    require_once MODX_CORE_PATH . 'vendor/autoload.php';
    $modx = \MODX\Revolution\modX::getInstance();
}
$modx->initialize('mgr');
require_once MODX_CORE_PATH . 'components/modxmcp/model/modxmcp.class.php';
$api = new modxMCP($modx);
$tvClass = $is2 ? 'modTemplateVar' : 'MODX\\Revolution\\modTemplateVar';
$linkClass = $is2 ? 'modTemplateVarTemplate' : 'MODX\\Revolution\\modTemplateVarTemplate';
$resClass = $is2 ? 'modResource' : 'MODX\\Revolution\\modResource';
$tplClass = $is2 ? 'modTemplate' : 'MODX\\Revolution\\modTemplate';
$fixture = bin2hex(random_bytes(5));
$tvs = array();
$links = array();
$resource = null;
$success = false;
$checks = 0;
function requireTrue($ok, $label) {
    global $checks;
    if (!$ok) throw new RuntimeException('PR46_LIVE_FAILED: ' . $label);
    $checks++;
}
function rpc($action, array $data = array()) {
    global $api;
    return $api->processRequest($action, '', $data);
}
function findTV(array $data, $name) {
    foreach ($data['tvs'] as $row) {
        if ($row['name'] === $name) return (string)$row['value'];
    }
    throw new RuntimeException('Missing resource TV: ' . $name);
}
try {
    $template = $modx->getObject($tplClass, 1);
    if (!$template) {
        $templates = $modx->getCollection($tplClass, array());
        $template = $templates ? reset($templates) : null;
    }
    requireTrue(!!$template, 'template fixture');
    $templateId = (int)$template->get('id');

    // Two temporary, assigned TVs. The second uses the literal @INHERIT
    // binding: CAS must not compare rendered HTML, expand bindings, or store
    // an expanded value on retry.
    $definitions = array(
        array('name'=>'raw_' . $fixture, 'before'=>'original_' . $fixture,
            'after'=>'changed_' . $fixture),
        array('name'=>'inherit_' . $fixture, 'before'=>'@INHERIT 0',
            'after'=>'@INHERIT 10'),
    );
    foreach ($definitions as $def) {
        $tv = $modx->newObject($tvClass);
        $tv->set('name', $def['name']);
        $tv->set('caption', 'Temporary CAS fixture ' . $fixture);
        $tv->set('type', 'text');
        requireTrue($tv->save(), 'TV creation ' . $def['name']);
        $tvs[] = $tv;
        $link = $modx->newObject($linkClass);
        $link->set('tmplvarid', (int)$tv->get('id'));
        $link->set('templateid', $templateId);
        requireTrue($link->save(), 'TV template assignment ' . $def['name']);
        $links[] = $link;
    }
    $resource = $modx->newObject($resClass);
    $resource->fromArray(array(
        'pagetitle'=>'CAS PR46 disposable ' . $fixture,
        'alias'=>'cas-pr46-disposable-' . $fixture,
        'template'=>$templateId, 'context_key'=>'web',
        'published'=>0, 'searchable'=>0, 'hidemenu'=>1, 'parent'=>0,
    ));
    requireTrue($resource->save(), 'resource fixture creation');
    $rid = (int)$resource->get('id');
    foreach ($definitions as $def) {
        requireTrue($resource->setTVValue($def['name'], $def['before']) !== false,
            'initial TV value ' . $def['name']);
    }
    // Fresh MODX model to ensure readback is from persisted values.
    unset($resource);
    $resource = $modx->getObject($resClass, $rid);
    requireTrue(!!$resource, 'reload resource');
    $raw = array(); $after = array();
    foreach ($definitions as $def) {
        $raw[$def['name']] = $def['before'];
        $after[$def['name']] = $def['after'];
    }

    $first = rpc('get_resource_tvs', array('resource_id'=>$rid));
    foreach ($raw as $name=>$expected) {
        requireTrue(findTV($first, $name) === $expected,
            'unrendered TV read ' . $name);
    }
    $state = rpc('get_site_state');
    requireTrue(!empty($state['atomic_preconditions'])
        && (int)$state['precondition_version'] === 2, 'CAS v2 state');
    $r0 = (int)$state['site_revision'];
    $pc0 = array('observed_site_revision'=>$r0, 'strict_site_revision'=>true,
        'read_set'=>array(), 'expected_tvs'=>$raw);
    $result = rpc('update_resource_tvs',
        array('resource_id'=>$rid, 'tvs'=>$after, '_runtime_preconditions'=>$pc0));
    requireTrue(isset($result['_site_revision']) &&
        (int)$result['_site_revision'] === $r0 + 1, 'revision increment after write');
    foreach ($after as $name=>$expected) {
        requireTrue(findTV($result, $name) === $expected,
            'raw return after update ' . $name);
    }
    $unchanged = rpc('get_resource_tvs', array('resource_id'=>$rid));
    foreach ($after as $name=>$expected) {
        requireTrue(findTV($unchanged, $name) === $expected,
            'readback after commit ' . $name);
    }

    // First writer wins; reusing exactly the same revision must be rejected.
    $staleRejected = false;
    try {
        rpc('update_resource_tvs', array('resource_id'=>$rid, 'tvs'=>$raw,
            '_runtime_preconditions'=>$pc0));
    } catch (ModxMCPClientException $e) {
        $staleRejected = strpos($e->getMessage(), 'STALE_STATE') !== false;
    }
    requireTrue($staleRejected, 'stale revision reject');

    // Revision is current, but TV data was changed outside an earlier
    // snapshot. The field-level raw CAS must reject despite current revision.
    $pcWrong = array('observed_site_revision'=>$r0 + 1,
        'strict_site_revision'=>true, 'read_set'=>array(), 'expected_tvs'=>$raw);
    $wrongRejected = false;
    try {
        rpc('update_resource_tvs', array('resource_id'=>$rid, 'tvs'=>$raw,
            '_runtime_preconditions'=>$pcWrong));
    } catch (ModxMCPClientException $e) {
        $wrongRejected = strpos($e->getMessage(), 'STALE_STATE') !== false;
    }
    requireTrue($wrongRejected, 'wrong raw TV value reject');
    $current = rpc('get_resource_tvs', array('resource_id'=>$rid));
    foreach ($after as $name=>$expected) {
        requireTrue(findTV($current, $name) === $expected,
            'failed CAS did not mutate ' . $name);
    }
    requireTrue((int)(rpc('get_site_state')['site_revision']) === $r0 + 1,
        'failed CAS did not increment revision');

    // Authorized restoration via a new CAS, not a blind overwrite.
    $pcRestore = array('observed_site_revision'=>$r0 + 1,
        'strict_site_revision'=>true, 'read_set'=>array(), 'expected_tvs'=>$after);
    $restored = rpc('update_resource_tvs',
        array('resource_id'=>$rid, 'tvs'=>$raw, '_runtime_preconditions'=>$pcRestore));
    requireTrue((int)$restored['_site_revision'] === $r0 + 2,
        'restore increments revision exactly once');
    foreach ($raw as $name=>$expected) {
        requireTrue(findTV($restored, $name) === $expected,
            'restored raw TV value ' . $name);
    }

    // Fault injection: the revision write is deliberately rejected by
    // MariaDB AFTER the TV mutations have been issued. In the old connector
    // the TV transaction had already committed; with the repaired boundary
    // both the raw TV values and the revision must roll back together.
    // Use an independent SQL observer to detect persisted values, not
    // MODX/xPDO's connection wrapper or its in-memory object cache.
    // Credentials are GitHub Actions disposable service credentials only.
    $pdo = new \PDO(
        'mysql:host=127.0.0.1;dbname=modx_build;charset=utf8mb4',
        'modx_ci', 'ci-throwaway-only-user',
        array(\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION)
    );
    requireTrue($pdo instanceof \PDO, 'isolated CI SQL observer');
    $revisionTrigger = 'cas_pr46_rev_fail_' . $fixture;
    $sql = 'CREATE TRIGGER `' . $revisionTrigger . '`'
        . ' BEFORE UPDATE ON `modx_system_settings` FOR EACH ROW'
        . " SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'CAS_CI_REVISION_DENIED'";
    requireTrue($pdo->exec($sql) !== false, 'create revision fault trigger');
    $forcedRejected = false;
    try {
        rpc('update_resource_tvs', array(
            'resource_id'=>$rid,
            'tvs'=>$after,
            '_runtime_preconditions'=>array(
                'observed_site_revision'=>$r0 + 2,
                'strict_site_revision'=>true,
                'read_set'=>array(),
                'expected_tvs'=>$raw,
            ),
        ));
    } catch (\Throwable $e) {
        $forcedRejected = true;
    } finally {
        $pdo->exec('DROP TRIGGER IF EXISTS `' . $revisionTrigger . '`');
    }
    requireTrue($forcedRejected, 'forced revision save rejected mutation');
    // Independent direct SQL readback avoids MODX/xPDO object-cache effects.
    $readRows = $pdo->prepare(
        'SELECT `value` FROM `modx_site_tmplvar_contentvalues`'
        . ' WHERE `contentid` = ? AND `tmplvarid` = ?'
    );
    foreach ($tvs as $i=>$tv) {
        $readRows->execute(array($rid, (int)$tv->get('id')));
        requireTrue((string)$readRows->fetchColumn() === $definitions[$i]['before'],
            'revision-failure rollback persisted TV ' . $i);
    }
    $revisionRow = $pdo->query(
        "SELECT `value` FROM `modx_system_settings`"
        . " WHERE `key`='modxmcp.site_revision'"
    );
    requireTrue((int)$revisionRow->fetchColumn() === $r0 + 2,
        'revision-failure rollback persisted revision');
    echo 'CAS_REVISION_FAULT_ATOMIC_ROLLBACK_OK' . PHP_EOL;

    // Two genuine independent PHP processes race with the SAME CAS snapshot.
    // The shared flock should serialize them; one succeeds, one sees stale.
    $raceRevision = (int)rpc('get_site_state')['site_revision'];
    $raceName = $definitions[0]['name'];
    $raceBefore = $definitions[0]['before'];
    $gate = getenv('RUNNER_TEMP') . '/cas-pr46-' . $fixture . '.gate';
    $workers = array();
    for ($i = 0; $i < 2; $i++) {
        $ready = getenv('RUNNER_TEMP') . '/cas-pr46-' . $fixture
            . '-ready-' . $i;
        $next = 'concurrent_writer_' . $i . '_' . $fixture;
        $command = array(
            PHP_BINARY, __DIR__ . '/test.ci-pr46-cas-worker.php',
            (string)$rid, $raceName, $raceBefore, $next,
            (string)$raceRevision, $ready, $gate,
        );
        $descriptors = array(
            0=>array('pipe','r'),
            1=>array('pipe','w'),
            2=>array('pipe','w'),
        );
        $pipes = array();
        $process = proc_open($command, $descriptors, $pipes);
        requireTrue(is_resource($process), 'start concurrent CAS writer');
        fclose($pipes[0]);
        $workers[] = array($process, $pipes, $ready, $next);
    }
    $deadline = microtime(true) + 15;
    while ((!is_file($workers[0][2]) || !is_file($workers[1][2]))
        && microtime(true) < $deadline) {
        usleep(10000);
    }
    requireTrue(is_file($workers[0][2]) && is_file($workers[1][2]),
        'both independent CAS writers ready');
    file_put_contents($gate, 'go');
    $outcomes = array();
    foreach ($workers as $worker) {
        $stdout = stream_get_contents($worker[1][1]);
        $stderr = stream_get_contents($worker[1][2]);
        fclose($worker[1][1]);
        fclose($worker[1][2]);
        $exit = proc_close($worker[0]);
        requireTrue($exit === 0, 'CAS worker exit: ' . $stderr);
        $outcomes[] = trim($stdout);
    }
    requireTrue(count(array_filter($outcomes, function ($x) {
        return $x === 'CAS_WORKER_OK';
    })) === 1, 'exactly one concurrent CAS winner');
    requireTrue(count(array_filter($outcomes, function ($x) {
        return $x === 'CAS_WORKER_STALE';
    })) === 1, 'exactly one concurrent CAS stale rejection');
    $revisionRow = $pdo->query(
        "SELECT `value` FROM `modx_system_settings`"
        . " WHERE `key`='modxmcp.site_revision'"
    );
    requireTrue((int)$revisionRow->fetchColumn() === $raceRevision + 1,
        'racing updates increment revision only once');
    $readRows->execute(array($rid, (int)$tvs[0]->get('id')));
    $winnerValue = (string)$readRows->fetchColumn();
    requireTrue(in_array($winnerValue, array($workers[0][3], $workers[1][3]), true),
        'racing updates persisted only winner value');
    $raceRestore = rpc('update_resource_tvs', array(
        'resource_id'=>$rid,
        'tvs'=>array($raceName=>$raceBefore),
        '_runtime_preconditions'=>array(
            'observed_site_revision'=>$raceRevision + 1,
            'strict_site_revision'=>true,
            'read_set'=>array(),
            'expected_tvs'=>array($raceName=>$winnerValue),
        ),
    ));
    requireTrue((int)$raceRestore['_site_revision'] === $raceRevision + 2,
        'concurrent winning value can be restored via CAS');
    echo 'CAS_CONCURRENT_WRITERS_ONE_WINNER_OK' . PHP_EOL;

    $success = true;
    echo 'PR46_LIVE_CAS_OK platform=' . ($is2 ? 'modx2' : 'modx3')
        . ' revision=' . $r0 . '->' . ($r0 + 1) . '->' . ($r0 + 2)
        . ' checks=' . $checks
        . ' raw_and_inherit=pass stale=2 rollback=pass revision_fault=pass concurrency=pass' . PHP_EOL;
} finally {
    // Remove only resources created inside the CI fixture; never run on live sites.
    if ($resource && $resource->get('id')) $resource->remove();
    foreach (array_reverse($links) as $link) {
        if ($link->get('id')) $link->remove();
    }
    foreach (array_reverse($tvs) as $tv) {
        if ($tv->get('id')) $tv->remove();
    }
    if ($modx->cacheManager) $modx->cacheManager->refresh();
    if (!$success) fwrite(STDERR, "PR46_LIVE_FIXTURE_CLEANUP_ATTEMPTED\n");
}
