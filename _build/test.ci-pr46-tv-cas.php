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
    $success = true;
    echo 'PR46_LIVE_CAS_OK platform=' . ($is2 ? 'modx2' : 'modx3')
        . ' revision=' . $r0 . '->' . ($r0 + 1) . '->' . ($r0 + 2)
        . ' checks=' . $checks
        . ' raw_and_inherit=pass stale=2 rollback=pass' . PHP_EOL;
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
