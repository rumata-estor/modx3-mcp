<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Проверка «ci-cas-other-mutations»; тестовый код не запускается на production.
 * EN: «ci-cas-other-mutations» regression test; not executed in production.
 */
/**
 * Disposable CI failure injection through the real modxMCP dispatcher.
 *
 * ALL functional creation, changes, readbacks and cleanup are made with
 * modxMCP::processRequest(). Direct PDO is used ONLY to arm/remove a
 * MariaDB trigger that rejects site_revision; no SQL reads/writes of
 * resources, categories, TV, or user settings are made by the harness.
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

require_once __DIR__ . '/test.cas-mcp-helper.php';
function rpc($action, array $data = array()) {
    global $api;
    return disposableCasRpc($api, $action, $data);
}
function checked($action, array $data) {
    $result = rpc($action, $data);
    if ($result === false) {
        throw new RuntimeException('MCP action returned false: ' . $action);
    }
    return $result;
}
function exactResource($name) {
    $list = rpc('list_elements', array('type'=>'resource', 'query'=>$name, 'limit'=>50));
    foreach ($list as $item) {
        if (isset($item['name']) && $item['name'] === $name) return (int)$item['id'];
    }
    return null;
}
function exactCategory($name) {
    $list = rpc('list_categories', array('query'=>$name, 'limit'=>50));
    foreach ($list['results'] as $item) {
        if ($item['name'] === $name) return (int)$item['id'];
    }
    return null;
}
function getResource($id) {
    return rpc('get_element', array('type'=>'resource','id'=>$id));
}
function getCategory($id) {
    return rpc('get_category', array('id'=>$id));
}
function settingValue($key) {
    try {
        $item = rpc('get_system_setting', array('key'=>$key));
        return (string)$item['value'];
    } catch (ModxMCPClientException $e) {
        if (strpos($e->getMessage(), 'not found') !== false) return null;
        throw $e;
    }
}
function probe($action, array $args, $name, $reader, $expected) {
    global $results;
    $error = null;
    try { rpc($action, $args); } catch (\Throwable $e) {
        $error = get_class($e) . ':' . substr($e->getMessage(),0,160);
    }
    $actual = $reader();
    $rolledBack = $actual === $expected;
    $result = array(
        'action'=>$name,'result'=>$error === null ? 'unexpected-success'
            : ($rolledBack ? 'rolled-back' : 'persisted-after-error'),
        'actual'=>$actual, 'expected'=>$expected, 'exception'=>$error,
    );
    $results[] = $result;
    echo 'CAS_OTHER_BOUNDARY action=' . $name
        . ' result=' . $result['result'] . PHP_EOL;
}
$unique = bin2hex(random_bytes(5));
$trigger = 'cas_other_fault_' . $unique;
$resources = array();
$categories = array();
$chunks = array();
$settings = array();
$results = array();
$triggerArmed = false;
$testRan = false;
$mediaName = 'cas-external-refused-' . $unique . '.txt';
$mediaCleanup = false;
try {
    // MCP-only fixtures: all are hidden and unpublished, with unique names.
    $newPage = 'CAS boundary page ' . $unique;
    $created = checked('create_element', array(
        'type'=>'resource', 'name'=>$newPage,
        'alias'=>'cas-boundary-existing-'.$unique,
        'content'=>'BEFORE',
        'template'=>0,'published'=>0,'hidemenu'=>1,'parent'=>0
    ));
    $resourceId = (int)$created['id'];
    if ($resourceId <= 0) throw new RuntimeException('Invalid resource ID');
    $resources[] = $resourceId;
    $newCat = 'CAS boundary category ' . $unique;
    $cat = checked('create_category', array('name'=>$newCat));
    $categoryId = (int)$cat['id'];
    $categories[] = $categoryId;
    $newCatDelete = 'CAS boundary delete category ' . $unique;
    $catDelete = checked('create_category',array('name'=>$newCatDelete));
    $categoryDeleteId = (int)$catDelete['id'];
    $categories[] = $categoryDeleteId;
    $chunk = checked('create_element', array(
        'type'=>'chunk','name'=>'CAS boundary chunk '.$unique,
        'content'=>'A disposable uncategorized chunk for the CAS test'
    ));
    $chunkId = (int)$chunk['id'];
    if ($chunkId <= 0) throw new RuntimeException('Invalid CAS chunk fixture');
    $chunks[] = $chunkId;
    // Settings must also be created through MCP, never via direct MODX API.
    $keyUpdate = 'cas_test_update_'.$unique;
    $keyDelete = 'cas_test_delete_'.$unique;
    foreach (array($keyUpdate, $keyDelete) as $key) {
        checked('create_system_setting', array('key'=>$key,'value'=>'BEFORE'));
        $settings[] = $key;
    }

    $revisionBefore = (int)rpc('get_site_state')['site_revision'];

    // Fault injection is deliberately restricted to disposable MariaDB.
    // Triggers are not part of MODX MCP's functional operation surface.
    $fault = new \PDO(
        'mysql:host=127.0.0.1;dbname=modx_build;charset=utf8mb4',
        'modx_ci', 'ci-throwaway-only-user',
        array(\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION)
    );
    $fault->exec(
        'CREATE TRIGGER `'.$trigger.'` BEFORE UPDATE ON `modx_system_settings`'
        . ' FOR EACH ROW BEGIN'
        . " IF NEW.`key`='modxmcp.site_revision' THEN"
        . " SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='CAS_REVISION_REJECTED';"
        . ' END IF; END'
    );
    $triggerArmed = true;
    $candidateName = 'CAS boundary created ' . $unique;
    probe('create_element', array(
        'type'=>'resource','name'=>$candidateName,
        'alias'=>'cas-boundary-created-'.$unique,
        'template'=>0,'published'=>0,'hidemenu'=>1,'parent'=>0
    ), 'create_resource', function() use ($candidateName, &$resources) {
        $r = exactResource($candidateName);
        if ($r) $resources[] = $r;
        return $r;
    }, null);

    probe('update_element', array(
        'type'=>'resource','id'=>$resourceId,'content'=>'AFTER'
    ), 'update_resource', function() use ($resourceId) {
        return (string)getResource($resourceId)['content'];
    }, 'BEFORE');

    probe('delete_element', array(
        'type'=>'resource','id'=>$resourceId
    ), 'soft_delete_resource', function() use ($resourceId) {
        return (int)getResource($resourceId)['deleted'];
    }, 0);

    probe('assign_element_category', array(
        'type'=>'chunk','id'=>$chunkId,'category_id'=>$categoryId
    ), 'assign_element_category', function() use ($chunkId) {
        return (int)rpc('get_element',array('type'=>'chunk','id'=>$chunkId))['category'];
    }, 0);

    $candidateCat = 'CAS boundary new category '.$unique;
    probe('create_category', array('name'=>$candidateCat),
        'create_category', function() use ($candidateCat, &$categories) {
            $id=exactCategory($candidateCat);
            if ($id) $categories[]=$id;
            return $id;
        }, null);

    probe('update_category', array('id'=>$categoryId,'name'=>'MUTATED '.$unique),
        'update_category', function() use ($categoryId) {
            return (string)getCategory($categoryId)['name'];
        }, $newCat);

    probe('delete_category', array('id'=>$categoryDeleteId,'dry_run'=>false),
        'delete_category', function() use ($categoryDeleteId) {
            try { getCategory($categoryDeleteId);return true; }
            catch (ModxMCPClientException $e) { return false; }
        }, true);

    $createKey = 'cas_test_create_'.$unique;
    probe('create_system_setting', array('key'=>$createKey,'value'=>'AFTER'),
        'create_setting', function() use ($createKey, &$settings) {
            $v=settingValue($createKey);
            if ($v !== null) $settings[]=$createKey;
            return $v;
        }, null);

    probe('update_system_setting', array('key'=>$keyUpdate,'value'=>'AFTER'),
        'update_setting', function() use ($keyUpdate) {
            return settingValue($keyUpdate);
        }, 'BEFORE');

    probe('delete_system_setting', array('key'=>$keyDelete),
        'delete_setting', function() use ($keyDelete) {
            return settingValue($keyDelete);
        }, 'BEFORE');

    // External filesystem effects are never rolled back by SQL. For this
    // class of operations, the dispatcher reserves site_revision BEFORE the
    // tool starts. The trigger must reject that reservation, and MCP must
    // prove that no media file was created.
    $modx->setOption('modxmcp.allow_root_filesystem_read', true);
    $mediaFailure = null;
    try {
        rpc('create_media_file', array(
            'source'=>1,'path'=>'','name'=>$mediaName,
            'content'=>'MUST_NOT_EXIST_'.$unique,
        ));
    } catch (\Throwable $e) {
        $mediaFailure = $e->getMessage();
    }
    if ($mediaFailure === null
        || strpos($mediaFailure, 'Failed to persist MODX MCP site revision') === false) {
        throw new RuntimeException(
            'Media create did not fail at revision reservation: '.(string)$mediaFailure
        );
    }
    $mediaExists = false;
    try {
        rpc('read_media_source_file', array('id'=>1,'path'=>$mediaName));
        $mediaExists = true;
        $mediaCleanup = true;
    } catch (\ModxMCPClientException $e) {
        if (strpos($e->getMessage(), 'File not found') === false) throw $e;
    }
    if ($mediaExists) {
        throw new RuntimeException('External media file changed after revision denial');
    }
    echo 'CAS_MEDIA_FILE_REVISION_PRECHECK_OK' . PHP_EOL;

    $revisionAfter = (int)rpc('get_site_state')['site_revision'];
    if ($revisionAfter !== $revisionBefore) {
        throw new RuntimeException('CAS revision changed despite denied writes');
    }
    echo 'CAS_OTHER_REVISION_UNCHANGED=YES' . PHP_EOL;
    $testRan = true;
} finally {
    if ($triggerArmed) {
        try { $fault->exec('DROP TRIGGER IF EXISTS `'.$trigger.'`'); }
        catch (\Throwable $e) {
            fwrite(STDERR,'FAULT_TRIGGER_CLEANUP_ERROR '.$e->getMessage()."\n");
        }
    }
    // MCP-only cleanup; no raw MODX objects, SQL DELETE, or direct files.
    if ($mediaCleanup) {
        try { rpc('delete_media_file',array('source'=>1,'path'=>$mediaName)); }
        catch (\Throwable $e) {
            fwrite(STDERR, 'MCP_CLEANUP_MEDIA_FAILED '.$e->getMessage()."\n");
        }
    }
    foreach (array_unique(array_reverse($settings)) as $key) {
        try { rpc('delete_system_setting',array('key'=>$key)); }
        catch (\Throwable $e) {
            fwrite(STDERR,'MCP_CLEANUP_SETTING_FAILED '.$key.' '.$e->getMessage()."\n");
        }
    }
    foreach (array_unique(array_reverse($chunks)) as $cid) {
        try { rpc('delete_element',array('type'=>'chunk','id'=>$cid)); }
        catch (\Throwable $e) {
            fwrite(STDERR,'MCP_CLEANUP_CHUNK_FAILED '.$cid.' '.$e->getMessage()."\n");
        }
    }
    foreach (array_unique(array_reverse($categories)) as $cid) {
        try { rpc('delete_category',array('id'=>$cid,'dry_run'=>false)); }
        catch (\Throwable $e) {
            fwrite(STDERR,'MCP_CLEANUP_CATEGORY_FAILED '.$cid.' '.$e->getMessage()."\n");
        }
    }
    foreach (array_unique(array_reverse($resources)) as $rid) {
        try { rpc('delete_element',array('type'=>'resource','id'=>$rid)); }
        catch (\Throwable $e) {
            fwrite(STDERR,'MCP_CLEANUP_RESOURCE_FAILED '.$rid.' '.$e->getMessage()."\n");
        }
    }
}
if (!$testRan) throw new RuntimeException('CAS fault matrix did not finish');
$summary=array();
foreach ($results as $entry) $summary[$entry['action']]=$entry['result'];
echo 'CAS_OTHER_BOUNDARY_SUMMARY ' . json_encode($summary, JSON_UNESCAPED_SLASHES)
    . PHP_EOL;
if (count($results)!==10) throw new RuntimeException('Expected 10 probes');
foreach ($results as $entry) {
    if ($entry['result'] !== 'rolled-back') {
        throw new RuntimeException(
            'CAS boundary regression: ' . $entry['action'] . ' '
            . $entry['result'] . ' ' . (string)$entry['exception']
        );
    }
}
echo 'CAS_ALL_OTHER_DB_BOUNDARIES_ATOMIC_OK=10' . PHP_EOL;
