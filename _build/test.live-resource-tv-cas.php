<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Проверка «live-resource-tv-cas»; тестовый код не запускается на production.
 * EN: «live-resource-tv-cas» regression test; not executed in production.
 */
// Opt-in LIVE regression: temporary unpublished TV + resource fixture.
// Calls the site's HTTP MCP endpoint with its existing API token (never printed).
// Requires: php _build/test.live-resource-tv-cas.php SITE_DOCUMENT_ROOT SITE_API_URL
// Run against authorized TEST installations only; fixture is deleted in finally.

if (PHP_SAPI !== 'cli') exit(2);
$root = rtrim($argv[1], '/');
$url = $argv[2];
$_SERVER['DOCUMENT_ROOT'] = $root;
require_once $root . '/config.core.php';
$is2 = !is_file(MODX_CORE_PATH.'src/Revolution/modX.php');
if ($is2) {
    require_once MODX_CORE_PATH.'model/modx/modx.class.php';
    $modx = new modX();
} else {
    require_once MODX_CORE_PATH.'vendor/autoload.php';
    $modx = MODX\Revolution\modX::getInstance();
}
$modx->initialize('mgr');
$token = trim((string)$modx->getOption('modxmcp.api_token', null, ''));
if (strlen($token) < 32) throw new RuntimeException('MCP token unavailable');
function mcpCall($action, $data = array()) {
    global $token, $url;
    $body = json_encode(array('action'=>$action,'data'=>$data), JSON_UNESCAPED_UNICODE);
    $ctx = stream_context_create(array('http'=>array(
        'method'=>'POST',
        'header'=>"Content-Type: application/json\r\nX-MCP-Token: {$token}\r\n",
        'content'=>$body,'timeout'=>30,'ignore_errors'=>true
    )));
    $raw = file_get_contents($url, false, $ctx);
    if ($raw === false) throw new RuntimeException("HTTP request failed for {$action}");
    $result = json_decode($raw,true);
    if (!is_array($result)) throw new RuntimeException('Non-JSON MCP response: '.substr($raw,0,120));
    return $result;
}
// All test objects are unpublished, hidden, temporary and removed in finally.
$tvClass = $is2 ? 'modTemplateVar' : 'MODX\\Revolution\\modTemplateVar';
$linkClass = $is2 ? 'modTemplateVarTemplate' : 'MODX\\Revolution\\modTemplateVarTemplate';
$resClass = $is2 ? 'modResource' : 'MODX\\Revolution\\modResource';
$tplClass = $is2 ? 'modTemplate' : 'MODX\\Revolution\\modTemplate';
$tv = null; $link = null; $resource = null;
$id = bin2hex(random_bytes(4));
$name = 'saCasSmoke'.$id;
$before = 'before_'.$id;
$after = 'after_'.$id;
$success = false;
try {
    $template = $modx->getObject($tplClass, 1);
    if (!$template) {
        $templates = $modx->getCollection($tplClass, array());
        $template = $templates ? reset($templates) : null;
    }
    if (!$template) throw new RuntimeException('No available resource template');
    $tv = $modx->newObject($tvClass);
    $tv->set('name', $name);
    $tv->set('caption', 'Temporary CAS smoke fixture');
    $tv->set('type', 'text');
    if (!$tv->save()) throw new RuntimeException('Cannot create temporary TV');
    $link = $modx->newObject($linkClass);
    $link->set('tmplvarid', (int)$tv->get('id'));
    $link->set('templateid', (int)$template->get('id'));
    if (!$link->save()) throw new RuntimeException('Cannot assign temporary TV');
    $resource = $modx->newObject($resClass);
    $resource->fromArray(array(
        'pagetitle'=>'SiteAgent CAS temporary fixture',
        'alias'=>'siteagent-cas-'.$id,
        'template'=>(int)$template->get('id'),
        'context_key'=>'web',
        'published'=>0, 'hidemenu'=>1, 'searchable'=>0, 'parent'=>0,
    ));
    if (!$resource->save()) throw new RuntimeException('Cannot save temporary unpublished resource');
    $resId=(int)$resource->get('id');
    if ($resource->setTVValue($name, $before) === false) {
        throw new RuntimeException('Cannot initialize temporary TV value');
    }
    $modx->cacheManager->refresh();
    $live=mcpCall('get_resource_tvs',array('resource_id'=>$resId));
    if (empty($live['success'])) throw new RuntimeException('TV read failed: '.($live['error']??'unknown'));
    $found = null;
    foreach (($live['data']['tvs']??array()) as $row) {
        if (($row['name']??'')===$name) $found=(string)($row['value']??'');
    }
    if ($found!==$before) throw new RuntimeException('TV fixture not visible through live API');
    $state=mcpCall('get_site_state');
    if (empty($state['success'])) throw new RuntimeException('Site revision read failed');
    $v=(int)$state['data']['site_revision'];
    if (($state['data']['precondition_version']??0)<2) throw new RuntimeException('CAS v2 absent');
    $pc = array('observed_site_revision'=>$v,'strict_site_revision'=>true,
                'read_set'=>array(),'expected_tvs'=>array($name=>$before));
    $write=mcpCall('update_resource_tvs',array('resource_id'=>$resId,
              'tvs'=>array($name=>$after),'_runtime_preconditions'=>$pc));
    if (empty($write['success'])) throw new RuntimeException('CAS WRITE FAILED '.($write['error']??'unknown'));
    $newRev=(int)($write['data']['_site_revision']??-1);
    if ($newRev!==$v+1) throw new RuntimeException('Revision did not advance by one');
    $stale=mcpCall('update_resource_tvs',array('resource_id'=>$resId,
              'tvs'=>array($name=>'SHOULD_NEVER_WRITE'),'_runtime_preconditions'=>$pc));
    if (!empty($stale['success']) || strpos($stale['error']??'','STALE_STATE')===false) {
        throw new RuntimeException('Old CAS revision incorrectly accepted');
    }
    $wrongPc=array('observed_site_revision'=>$newRev,'strict_site_revision'=>true,
                   'read_set'=>array(),'expected_tvs'=>array($name=>$before));
    $wrong=mcpCall('update_resource_tvs',array('resource_id'=>$resId,
              'tvs'=>array($name=>'SHOULD_NEVER_WRITE'),'_runtime_preconditions'=>$wrongPc));
    if (!empty($wrong['success']) || strpos($wrong['error']??'','STALE_STATE')===false) {
        throw new RuntimeException('Wrong expected_tvs incorrectly accepted');
    }
    $read=mcpCall('get_resource_tvs',array('resource_id'=>$resId));
    $updated = null;
    foreach (($read['data']['tvs']??array()) as $row) {
        if (($row['name']??'')===$name) $updated=(string)$row['value'];
    }
    if ($updated!==$after) throw new RuntimeException('Readback failed after CAS conflict');
    $rollbackPc=array('observed_site_revision'=>$newRev,'strict_site_revision'=>true,
                      'read_set'=>array(),'expected_tvs'=>array($name=>$after));
    $restored=mcpCall('update_resource_tvs',array('resource_id'=>$resId,
              'tvs'=>array($name=>$before),'_runtime_preconditions'=>$rollbackPc));
    if (empty($restored['success'])) throw new RuntimeException('CAS RESTORE FAILED '.($restored['error']??'unknown'));
    if ((int)($restored['data']['_site_revision']??-1)!==$newRev+1) {
        throw new RuntimeException('Rollback did not increment revision');
    }
    $read=mcpCall('get_resource_tvs',array('resource_id'=>$resId));
    $restoredValue = null;
    foreach (($read['data']['tvs']??array()) as $row) {
        if (($row['name']??'')===$name) $restoredValue=(string)$row['value'];
    }
    if ($restoredValue!==$before) throw new RuntimeException('Restored TV value does not match original');
    $success=true;
    echo 'LIVE_CAS_TV_PASS major='.($is2?2:3).' revision='.$v.'->'.$newRev.'->'.($newRev+1)
         .' writes=2 stale_conflicts=2 readback=pass rollback=pass'.PHP_EOL;
} finally {
    if ($resource && $resource->get('id')) $resource->remove();
    if ($link && $link->get('id')) $link->remove();
    if ($tv && $tv->get('id')) $tv->remove();
    if ($modx->cacheManager) $modx->cacheManager->refresh();
    if (!$success) echo 'FIXTURE_CLEANUP_ATTEMPTED'.PHP_EOL;
}
