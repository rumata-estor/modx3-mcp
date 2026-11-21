<?php
/**
 * Disposable GitHub Actions only: classify mutation/side-effect boundaries
 * by denying site_revision UPDATE after the operation is run.
 *
 * All data is uniquely named, unpublished, isolated, and cleaned in finally.
 * Reports real independent SQL readback rather than relying on MODX cache.
 */
if (PHP_SAPI !== 'cli' || getenv('MODXMCP_DISPOSABLE_CI') !== '1'
    || strpos((string)getenv('MODX_CONFIG_CORE'), '/home/runner/') !== 0
    || !is_file((string)getenv('MODX_CONFIG_CORE'))) {
    fwrite(STDERR, "Refusing mutation fault injection outside disposable CI.\n");
    exit(3);
}
require_once getenv('MODX_CONFIG_CORE');
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
$pdo = new \PDO(
    'mysql:host=127.0.0.1;dbname=modx_build;charset=utf8mb4',
    'modx_ci', 'ci-throwaway-only-user',
    array(\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION)
);
$id = bin2hex(random_bytes(6));
$trigger = 'cas_other_fault_' . $id;
$resourceClass = $is2 ? 'modResource' : 'MODX\\Revolution\\modResource';
$categoryClass = $is2 ? 'modCategory' : 'MODX\\Revolution\\modCategory';
$settingClass = $is2 ? 'modSystemSetting' : 'MODX\\Revolution\\modSystemSetting';
$resource = null;
$category = null;
$setting = null;
$deleteSetting = null;
$deleteCategory = null;
$created = array();
$failures = array();
$results = array();
function sqlValue($table, $field, $where, array $args) {
    global $pdo;
    $s = $pdo->prepare('SELECT `'.$field.'` FROM `modx_'.$table.'` WHERE '.$where.' LIMIT 1');
    $s->execute($args);
    return $s->fetchColumn();
}
function checkMutation($action, array $data, $label, $readback, $expected) {
    global $api, $results, $failures;
    $error = null;
    try { $api->processRequest($action, '', $data); }
    catch (\Throwable $e) { $error = get_class($e) . ':' . substr($e->getMessage(), 0, 120); }
    $after = $readback();
    $same = (string)$after === (string)$expected;
    $status = !$error ? 'UNEXPECTED_SUCCESS' : ($same ? 'ROLLBACK_OK' : 'PERSISTED_ON_REVISION_FAILURE');
    $results[] = array($label, $status, $error, $after);
    echo 'CAS_OTHER_BOUNDARY platform=' . (defined('MODX_VERSION') ? MODX_VERSION : 'modx')
        . ' action=' . $label . ' status=' . $status . PHP_EOL;
    if ($status !== 'ROLLBACK_OK') $failures[] = $label . ':' . $status;
}
function rawResource($name, $alias) {
    global $modx, $resourceClass, $created;
    $r = $modx->newObject($resourceClass);
    $r->fromArray(array(
        'pagetitle'=>$name, 'alias'=>$alias, 'content'=>'BEFORE',
        'template'=>0, 'context_key'=>'web', 'published'=>0,
        'searchable'=>0, 'hidemenu'=>1, 'parent'=>0,
    ));
    if (!$r->save()) throw new RuntimeException('Cannot create disposable resource fixture');
    $created[] = $r;
    return $r;
}
function rawCategory($name) {
    global $modx, $categoryClass, $created;
    $c = $modx->newObject($categoryClass);
    $c->fromArray(array('category'=>$name, 'parent'=>0, 'rank'=>0));
    if (!$c->save()) throw new RuntimeException('Cannot create disposable category fixture');
    $created[] = $c;
    return $c;
}
function rawSetting($key, $value) {
    global $modx, $settingClass, $created;
    $s = $modx->newObject($settingClass);
    $s->fromArray(array('key'=>$key,'value'=>$value,'xtype'=>'textfield','namespace'=>'core','area'=>'default'));
    if (!$s->save()) throw new RuntimeException('Cannot create disposable system setting fixture');
    $created[] = $s;
    return $s;
}
try {
    $engineQuery = $pdo->query(
        "SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES"
        . " WHERE TABLE_SCHEMA='modx_build'"
        . " AND TABLE_NAME IN ('modx_site_content','modx_categories','modx_system_settings')"
    );
    $engines = $engineQuery->fetchAll(\PDO::FETCH_KEY_PAIR);
    foreach (array('modx_site_content','modx_categories','modx_system_settings') as $table) {
        if (!isset($engines[$table]) || strtoupper((string)$engines[$table]) !== 'INNODB')
            throw new RuntimeException('Nontransactional test table: ' . $table);
    }
    $resource = rawResource('CAS boundary existing ' . $id, 'cas-boundary-existing-'.$id);
    $rid = (int)$resource->get('id');
    $category = rawCategory('CAS boundary category ' . $id);
    $cid = (int)$category->get('id');
    $deleteCategory = rawCategory('CAS boundary delete category '.$id);
    $deleteCid = (int)$deleteCategory->get('id');
    $setting = rawSetting('cas_test_update_'.$id, 'BEFORE');
    $deleteSetting = rawSetting('cas_test_delete_'.$id, 'BEFORE');
    $revision = (int)sqlValue('system_settings', 'value', '`key` = ?', array('modxmcp.site_revision'));

    // DDL, including CREATE TRIGGER, is intentionally outside any transaction.
    $pdo->exec(
        'CREATE TRIGGER `'.$trigger.'` BEFORE UPDATE ON `modx_system_settings`'
        . ' FOR EACH ROW BEGIN'
        . " IF NEW.`key` = 'modxmcp.site_revision' THEN"
        . " SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'CAS_REVISION_REJECTED';"
        . ' END IF; END'
    );

    checkMutation('create_element', array(
        'type'=>'resource', 'name'=>'CAS boundary created '.$id,
        'alias'=>'cas-boundary-created-'.$id,
        'template'=>0, 'published'=>0, 'hidemenu'=>1, 'parent'=>0
    ), 'create_resource', function() use ($id) {
        return sqlValue('site_content', 'id', '`alias` = ?', array('cas-boundary-created-'.$id));
    }, false);

    checkMutation('update_element', array(
        'type'=>'resource', 'id'=>$rid, 'content'=>'AFTER'
    ), 'update_resource', function() use ($rid) {
        return sqlValue('site_content', 'content', '`id` = ?', array($rid));
    }, 'BEFORE');

    checkMutation('delete_element', array(
        'type'=>'resource', 'id'=>$rid
    ), 'soft_delete_resource', function() use ($rid) {
        return sqlValue('site_content', 'deleted', '`id` = ?', array($rid));
    }, 0);

    checkMutation('create_category', array('name'=>'CAS boundary new category '.$id),
        'create_category', function() use ($id) {
            return sqlValue('categories', 'id', '`category` = ?', array('CAS boundary new category '.$id));
        }, false);

    checkMutation('update_category', array('id'=>$cid, 'name'=>'MUTATED '.$id),
        'update_category', function() use ($cid) {
            return sqlValue('categories', 'category', '`id` = ?', array($cid));
        }, 'CAS boundary category '.$id);

    checkMutation('delete_category', array('id'=>$deleteCid,'dry_run'=>false),
        'delete_category', function() use ($deleteCid) {
            return sqlValue('categories', 'id', '`id` = ?', array($deleteCid)) !== false ? 1 : 0;
        }, 1);

    checkMutation('create_system_setting', array(
        'key'=>'cas_test_create_'.$id,'value'=>'AFTER'
    ), 'create_setting', function() use ($id) {
        return sqlValue('system_settings', 'value', '`key` = ?', array('cas_test_create_'.$id));
    }, false);
    checkMutation('update_system_setting', array(
        'key'=>'cas_test_update_'.$id,'value'=>'AFTER'
    ), 'update_setting', function() use ($id) {
        return sqlValue('system_settings', 'value', '`key` = ?', array('cas_test_update_'.$id));
    }, 'BEFORE');
    checkMutation('delete_system_setting', array('key'=>'cas_test_delete_'.$id),
        'delete_setting', function() use ($id) {
            return sqlValue('system_settings', 'value', '`key` = ?', array('cas_test_delete_'.$id));
        }, 'BEFORE');

    $currentRevision = (int)sqlValue('system_settings', 'value', '`key` = ?', array('modxmcp.site_revision'));
    if ($currentRevision !== $revision) {
        $failures[] = 'REVISION_CHANGED_DURING_FAULT';
    }
} finally {
    try { $pdo->exec('DROP TRIGGER IF EXISTS `'.$trigger.'`'); }
    catch (\Throwable $e) { fwrite(STDERR, 'TRIGGER_CLEANUP_ERROR ' . $e->getMessage() . "\n"); }
    // Clean up even if revision failure left committed changes.
    foreach (array_reverse($created) as $obj) {
        try { if ($obj->get('id') || $obj->get('key')) $obj->remove(); }
        catch (\Throwable $ignored) {}
    }
    $pdo->prepare('DELETE FROM modx_site_content WHERE alias=?')
        ->execute(array('cas-boundary-created-'.$id));
    $pdo->prepare('DELETE FROM modx_categories WHERE category=?')
        ->execute(array('CAS boundary new category '.$id));
    $pdo->prepare('DELETE FROM modx_system_settings WHERE `key`=?')
        ->execute(array('cas_test_create_'.$id));
}
echo 'CAS_OTHER_BOUNDARY_SUMMARY checks=' . count($results)
    . ' rollback_ok=' . (count($results)-count($failures))
    . ' problematic=' . count($failures)
    . ' labels=' . implode(',', $failures) . PHP_EOL;
if (count($results) !== 9) exit(4);
exit(0);
