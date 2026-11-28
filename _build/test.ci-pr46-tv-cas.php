<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Проверка «ci-pr46-tv-cas»; тестовый код не запускается на production.
 * EN: «ci-pr46-tv-cas» regression test; not executed in production.
 */
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
$fixture = bin2hex(random_bytes(5));
$tvs = array();
$rid = 0;
$success = false;
$checks = 0;
function requireTrue($ok, $label) {
    global $checks;
    if (!$ok) throw new RuntimeException('PR46_LIVE_FAILED: ' . $label);
    $checks++;
}
require_once __DIR__ . '/test.cas-mcp-helper.php';
function rpc($action, array $data = array()) {
    global $api;
    return disposableCasRpc($api, $action, $data);
}
function findTV(array $data, $name) {
    foreach ($data['tvs'] as $row) {
        if ($row['name'] === $name) return (string)$row['value'];
    }
    throw new RuntimeException('Missing resource TV: ' . $name);
}

function freshMcpReadback($rid) {
    $cmd = array(
        PHP_BINARY,
        __DIR__ . '/test.ci-pr46-mcp-readback.php',
        (string)$rid,
    );
    $pipes = array();
    $proc = proc_open($cmd, array(
        0=>array('pipe','r'),
        1=>array('pipe','w'),
        2=>array('pipe','w')
    ), $pipes);
    if (!is_resource($proc)) throw new RuntimeException('MCP readback start failed');
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);fclose($pipes[2]);
    $exit = proc_close($proc);
    if ($exit !== 0) throw new RuntimeException('MCP readback failed: '.$error);
    $result = json_decode(trim($output), true);
    if (!is_array($result) || !isset($result['tvs'], $result['state'])) {
        throw new RuntimeException('MCP readback returned invalid data');
    }
    return $result;
}
try {
    $templates = rpc('list_elements',array('type'=>'template','limit'=>50));
    requireTrue(is_array($templates) && count($templates)>0,
        'MCP template listing for disposable fixture');
    $templateId = (int)$templates[0]['id'];
    requireTrue($templateId > 0, 'valid template ID from MCP');

    // The fixture is created, written and removed exclusively via MCP.
    $definitions = array(
        array('name'=>'raw_' . $fixture, 'before'=>'original_' . $fixture,
            'after'=>'changed_' . $fixture),
        array('name'=>'inherit_' . $fixture, 'before'=>'@INHERIT 0',
            'after'=>'@INHERIT 10'),
    );
    $raw = array();
    $after = array();
    foreach ($definitions as $def) {
        $createdTV = rpc('create_element', array(
            'type'=>'tv','name'=>$def['name'],
            'field_type'=>'text',
            'templates'=>array($templateId),
        ));
        requireTrue(isset($createdTV['id']) && (int)$createdTV['id']>0,
            'MCP TV creation ' . $def['name']);
        $tvs[] = (int)$createdTV['id'];
        $raw[$def['name']] = $def['before'];
        $after[$def['name']] = $def['after'];
    }
    $createdResource = rpc('create_element', array(
        'type'=>'resource','name'=>'CAS PR46 disposable '.$fixture,
        'alias'=>'cas-pr46-disposable-'.$fixture,
        'template'=>$templateId,'context_key'=>'web',
        'published'=>0,'hidemenu'=>1,'parent'=>0,
    ));
    requireTrue(isset($createdResource['id']) && (int)$createdResource['id']>0,
        'MCP resource fixture creation');
    $rid = (int)$createdResource['id'];
    $seed = rpc('update_resource_tvs', array(
        'resource_id'=>$rid,'tvs'=>$raw,
    ));
    foreach ($raw as $name=>$expected) {
        requireTrue(findTV($seed, $name) === $expected,
            'MCP initial TV write '.$name);
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
    // PDO is used only to arm and remove the failure-injection trigger.
    // All independent readbacks use a NEW PHP process and MODX MCP.
    $pdo = new \PDO(
        'mysql:host=127.0.0.1;dbname=modx_build;charset=utf8mb4',
        'modx_ci', 'ci-throwaway-only-user',
        array(\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION)
    );
    requireTrue($pdo instanceof \PDO, 'isolated CI fault injector');
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
    // Fresh-process MCP readback bypasses the caller's object cache.
    $verify = freshMcpReadback($rid);
    foreach ($raw as $name=>$expected) {
        requireTrue(findTV($verify['tvs'], $name) === $expected,
            'MCP revision-failure rollback TV '. $name);
    }
    requireTrue((int)$verify['state']['site_revision'] === $r0 + 2,
        'MCP revision-failure rollback site_revision');
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
    $raceVerify = freshMcpReadback($rid);
    requireTrue((int)$raceVerify['state']['site_revision'] === $raceRevision + 1,
        'MCP racing updates increment revision only once');
    $winnerValue = findTV($raceVerify['tvs'], $raceName);
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

    // RU: Многозначный TV принимает массив и сохраняет разделитель ||.
    // RU: Обычный TV и все несвязанные значения не меняются.
    // EN: Multi-select TV accepts arrays serialized as ||; unrelated TVs
    // EN: and CAS protection must remain unchanged.
    $multiName = 'multi_' . $fixture;
    $multi = rpc('create_element', array(
        'type'=>'tv','name'=>$multiName,'field_type'=>'listbox-multiple',
        'elements'=>'Item18==18||Item19==19','templates'=>array($templateId),
        'description'=>'Описание TV','rank'=>17,'default_text'=>'18',
    ));
    requireTrue(isset($multi['id']) && (int)$multi['id']>0,
        'create disposable listbox-multiple TV');
    $tvs[] = (int)$multi['id'];
    $multiId=(int)$multi['id'];
    $tvRead=rpc('get_element',array('type'=>'tv','id'=>$multiId));
    requireTrue(($tvRead['description']??'')==='Описание TV' && (int)$tvRead['rank']===17
        && $tvRead['elements']==='Item18==18||Item19==19'
        && in_array($templateId,array_map('intval',$tvRead['templates']),true),'TV fields and link persisted');
    foreach (array(array('rank'=>-1),array('templates'=>array(0)),array('elements'=>array('bad'))) as $invalid) {
        $rev=rpc('get_site_state')['site_revision'];$denied=false;
        try {rpc('update_element',array_merge(array('type'=>'tv','id'=>$multiId),$invalid));}
        catch(ModxMCPClientException $e){$denied=strpos($e->getMessage(),'VALIDATION_ERROR:')===0;}
        requireTrue($denied && rpc('get_site_state')['site_revision']===$rev,'Invalid TV fields rejected before CAS');
    }
    rpc('update_element',array('type'=>'tv','id'=>$multiId,'description'=>'Updated TV','rank'=>18));
    $tvRead=rpc('get_element',array('type'=>'tv','id'=>$multiId));
    requireTrue($tvRead['description']==='Updated TV' && (int)$tvRead['rank']===18
        && $tvRead['elements']==='Item18==18||Item19==19'
        && in_array($templateId,array_map('intval',$tvRead['templates']),true),'Partial TV update preserves options and links');
    $checkbox=rpc('create_element',array('type'=>'tv','name'=>'checkbox_'.$fixture,
        'field_type'=>'checkbox','elements'=>'Yes==1||No==0','rank'=>2,'templates'=>array($templateId)));
    $checkboxId=(int)($checkbox['id']??0);
    requireTrue($checkboxId>0,'create checkbox TV');$tvs[]=$checkboxId;
    $checkRead=rpc('get_element',array('type'=>'tv','id'=>$checkboxId));
    requireTrue($checkRead['field_type']==='checkbox' && $checkRead['elements']==='Yes==1||No==0'
        && in_array($templateId,array_map('intval',$checkRead['templates']),true),'checkbox definition and link');
    rpc('update_element',array('type'=>'tv','id'=>$checkboxId,'templates'=>array()));
    $checkRead=rpc('get_element',array('type'=>'tv','id'=>$checkboxId));
    requireTrue($checkRead['templates']===array(),'explicit empty links detach TV');
    echo 'TV_SCHEMA_NATIVE_FIELDS_AND_LINKS_OK'.PHP_EOL;

    $beforeMulti = rpc('get_resource_tvs',array('resource_id'=>$rid));
    $oldMulti = findTV($beforeMulti,$multiName);
    $beforeUnrelated = findTV($beforeMulti,$raceName);
    $multiUpdate = rpc('update_resource_tvs',array(
        'resource_id'=>$rid,'tvs'=>array($multiName=>array('18','19')),
        '_runtime_preconditions'=>array(
            'observed_site_revision'=>(int)rpc('get_site_state')['site_revision'],
            'strict_site_revision'=>true,'read_set'=>array(),
            'expected_tvs'=>array($multiName=>$oldMulti),
        ),
    ));
    requireTrue(findTV($multiUpdate,$multiName)==='18||19',
        'multi-select array persisted as raw 18||19');
    requireTrue(findTV($multiUpdate,$raceName)===$beforeUnrelated,
        'unrelated TV preserved after multi-select update');
    $freshMulti=rpc('get_resource_tvs',array('resource_id'=>$rid));
    requireTrue(findTV($freshMulti,$multiName)==='18||19',
        'multi-select raw readback');
    $staleMulti=false;
    try {
        rpc('update_resource_tvs',array(
            'resource_id'=>$rid,'tvs'=>array($multiName=>array('19')),
            '_runtime_preconditions'=>array(
                'observed_site_revision'=>(int)rpc('get_site_state')['site_revision'],
                'strict_site_revision'=>true,'read_set'=>array(),
                'expected_tvs'=>array($multiName=>$oldMulti),
            ),
        ));
    } catch (ModxMCPClientException $e) {
        $staleMulti=strpos($e->getMessage(),'STALE_STATE')!==false;
    }
    requireTrue($staleMulti,'multi-select stale raw CAS blocked');
    echo 'MULTI_VALUE_TV_CAS_OK'.PHP_EOL;

    // Native renderer test on an isolated installation; the administrator-only
    // setting is changed in the disposable DB and restored in finally.
    $settingClass=$is2?'modSystemSetting':'MODX\Revolution\modSystemSetting';
    $tvClass=$is2?'modTemplateVar':'MODX\Revolution\modTemplateVar';
    $renderSetting=$modx->getObject($settingClass,array('key'=>'modxmcp.allow_native_tv_rendering'),false);
    $protectedSetting=$modx->getObject($settingClass,array('key'=>'modxmcp.external_authorization_required'),false);
    requireTrue($renderSetting && $protectedSetting,'installed rendering policy settings');
    $savedRender=$renderSetting->get('value');$savedProtected=$protectedSetting->get('value');
    $originalRenders=$modx->tvRenders??array();
    try {
        $renderSetting->set('value','0');requireTrue($renderSetting->save(),'disable native rendering');
        $blocked=false;
        try {rpc('get_resource_tvs',array('resource_id'=>$rid,'value_mode'=>'both'));}
        catch(ModxMCPClientException $e){$blocked=strpos($e->getMessage(),'SAFETY_CONFIRMATION_REQUIRED:')===0;}
        requireTrue($blocked,'native rendering disabled by default');
        $renderSetting->set('value','1');requireTrue($renderSetting->save(),'disposable administrator opt-in');
        $protectedSetting->set('value','0');requireTrue($protectedSetting->save(),'ordinary disposable policy');
        // Test actual registered custom PHP output renderer, not a mock TV.
        class McpDisposableTvOutput {
            public function __construct($tv){}
            public function render($value,$params){return 'CUSTOM:'.str_replace('||',',',(string)$value);}
        }
        $nativeMulti=$modx->getObject($tvClass,$multiId,false);
        requireTrue($nativeMulti && $nativeMulti->registerRenderMethod('mcp_disposable_custom','output','McpDisposableTvOutput'),'registered native custom renderer');
        rpc('update_element',array('type'=>'tv','id'=>$multiId,'display'=>'mcp_disposable_custom'));
        $emptyName='empty_'.$fixture;
        $empty=rpc('create_element',array('type'=>'tv','name'=>$emptyName,'field_type'=>'text','default_text'=>'','templates'=>array($templateId)));
        $tvs[]=(int)$empty['id'];
        $revision=(int)rpc('get_site_state')['site_revision'];
        $view=rpc('get_resource_tvs',array('resource_id'=>$rid,'value_mode'=>'both'));
        $rows=array_column($view['tvs'],null,'name');
        requireTrue($rows[$multiName]['value']==='18||19' && $rows[$multiName]['raw']==='18||19'
            && $rows[$multiName]['rendered']==='CUSTOM:18,19' && $rows[$multiName]['render_status']==='rendered','custom rendering distinct from raw multiple');
        $inherit='inherit_'.$fixture;
        requireTrue(strpos($rows[$inherit]['raw'],'@INHERIT')===0 && $rows[$inherit]['value']===$rows[$inherit]['raw']
            && $rows[$inherit]['rendered']!==$rows[$inherit]['raw'] && $rows[$inherit]['render_status']==='rendered','native inherit distinct from raw');
        requireTrue($rows[$emptyName]['raw']==='' && $rows[$emptyName]['value']==='','empty raw preserved');
        requireTrue((int)rpc('get_site_state')['site_revision']===$revision,'trusted fixture render did not reserve CAS');
        $protectedSetting->set('value','1');requireTrue($protectedSetting->save(),'protected policy fixture');
        $blocked=false;
        try {rpc('get_resource_tvs',array('resource_id'=>$rid,'value_mode'=>'both'));}
        catch(ModxMCPClientException $e){$blocked=strpos($e->getMessage(),'SAFETY_CONFIRMATION_REQUIRED:')===0;}
        requireTrue($blocked,'signed mode never executes renderer through read route');
        requireTrue(findTV(rpc('get_resource_tvs',array('resource_id'=>$rid)),$multiName)==='18||19','raw remains available in protected mode');
        echo 'TV_RAW_RENDERED_NATIVE_OK'.PHP_EOL;
    } finally {
        $renderSetting->set('value',$savedRender);$renderSetting->save();
        $protectedSetting->set('value',$savedProtected);$protectedSetting->save();
        $modx->tvRenders=$originalRenders;
    }


    $success = true;
    echo 'PR46_LIVE_CAS_OK platform=' . ($is2 ? 'modx2' : 'modx3')
        . ' revision=' . $r0 . '->' . ($r0 + 1) . '->' . ($r0 + 2)
        . ' checks=' . $checks
        . ' raw_and_inherit=pass stale=2 rollback=pass revision_fault=pass concurrency=pass' . PHP_EOL;
} finally {
    // MCP-only cleanup of disposable fixture. It runs after SQL fault
    // injection is removed, including if the CAS regression fails.
    if ($rid > 0) {
        try { rpc('delete_element',array('type'=>'resource','id'=>$rid)); }
        catch (\Throwable $e) {
            fwrite(STDERR, 'PR46_MCP_CLEANUP_RESOURCE_ERROR '.$e->getMessage().PHP_EOL);
        }
    }
    $cleanupFailed = array();
    // Unassign all TVs BEFORE deleting any of them: MODX TV processors may
    // rely on the template relations while resolving each TV.
    foreach (array_reverse($tvs) as $tvId) {
        try {
            rpc('update_element', array(
                'type'=>'tv','id'=>$tvId,'templates'=>array(),
            ));
        } catch (\Throwable $e) {
            $cleanupFailed[] = 'detach:'.(string)$tvId;
            fwrite(STDERR, 'PR46_MCP_TV_DETACH_ERROR '.$e->getMessage().PHP_EOL);
        }
    }
    if (!$cleanupFailed) {
        foreach (array_reverse($tvs) as $tvId) {
            try {
                rpc('delete_element', array('type'=>'tv','id'=>$tvId));
                // Verify the deletion through the public MCP read surface.
                try {
                    rpc('get_element', array('type'=>'tv','id'=>$tvId));
                    throw new \RuntimeException('TV is still readable after delete');
                } catch (\ModxMCPClientException $missing) {
                    // Expected after deletion.
                }
            } catch (\Throwable $e) {
                $cleanupFailed[] = 'delete:'.(string)$tvId;
                fwrite(STDERR, 'PR46_MCP_CLEANUP_TV_ERROR '.$e->getMessage().PHP_EOL);
            }
        }
    }
    if ($cleanupFailed) {
        throw new \RuntimeException(
            'MCP TV cleanup failed for IDs: '.implode(',',$cleanupFailed)
        );
    }
    if (!$success) fwrite(STDERR, "PR46_LIVE_FIXTURE_CLEANUP_ATTEMPTED
");
}
