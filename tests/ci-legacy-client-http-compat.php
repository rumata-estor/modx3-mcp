<?php
/**
 * Real legacy MCP HTTP client regression: old request shapes on MODX 2/3.
 * Run first against 1.2.5-pl, then against the updated 1.2.6-dev on the
 * SAME disposable CI database, preserving the original API token.
 *
 * All writes use random, unpublished CI-only objects. Never run on a live site.
 */
$root = getenv('RUNNER_TEMP');
$config = getenv('MODX_CONFIG_CORE');
$platform = getenv('BUILD_PLATFORM');
$phase = isset($argv[1]) ? $argv[1] : '';
if (PHP_SAPI !== 'cli' || getenv('GITHUB_ACTIONS') !== 'true' ||
    !in_array($platform, array('modx2', 'modx3'), true) ||
    !in_array($phase, array('prepare', 'before', 'after'), true) ||
    !is_string($root) || $root === '' || !is_string($config) ||
    realpath($config) !== realpath($root.'/modx-site/config.core.php') ||
    getenv('MODX_DOCUMENT_ROOT') !== $root.'/modx-site' ||
    getenv('MODXMCP_LEGACY_HTTP_URL') !==
        'http://127.0.0.1:8084/assets/components/modxmcp/api.php') {
    throw new RuntimeException('Refusing any non-disposable legacy compatibility test');
}
require $config;
if ($platform === 'modx3') {
    require MODX_CORE_PATH.'vendor/autoload.php';
    $modx = \MODX\Revolution\modX::getInstance();
    $settingClass = 'MODX\\Revolution\\modSystemSetting';
} else {
    require MODX_CORE_PATH.'model/modx/modx.class.php';
    $modx = new modX();
    $settingClass = 'modSystemSetting';
}
$modx->initialize('mgr');
if ($phase==='prepare') {
    // CI may have just exercised deny-only protected mode. Return ONLY the
    // throwaway database to original legacy mode; never touch live settings.
    foreach (array('modxmcp.enabled'=>'1',
                   'modxmcp.require_https'=>'0',
                   'modxmcp.external_authorization_required'=>'0',
                   'modxmcp.auto_static'=>'0') as $key=>$value) {
        $row=$modx->getObject($settingClass,array('key'=>$key));
        if (!$row) { throw new RuntimeException('Legacy prepare missing setting'); }
        $row->set('value',$value);
        if (!$row->save()) { throw new RuntimeException('Legacy prepare setting save failed'); }
    }
    $cache=$modx->getCacheManager();
    if (!$cache || $cache->refresh()===false) {
        throw new RuntimeException('Legacy CI cache refresh failed');
    }
    echo "LEGACY_MCP_DISPOSABLE_MODE_READY $platform\n";
    exit(0);
}
$setting = function ($key) use ($modx, $settingClass) {
    $item = $modx->getObject($settingClass, array('key'=>$key));
    if (!$item) { throw new RuntimeException('Missing existing system setting: '.$key); }
    return (string) $item->get('value');
};
if ($setting('modxmcp.enabled') !== '1' ||
    $setting('modxmcp.require_https') !== '0' ||
    $setting('modxmcp.external_authorization_required') !== '0' ||
    $setting('modxmcp.auto_static') !== '0') {
    throw new RuntimeException('Legacy path must be enabled only in throwaway CI');
}
$token = $setting('modxmcp.api_token');
if (!preg_match('/\A[0-9a-f]{64}\z/D', $token)) {
    throw new RuntimeException('Existing API token is invalid');
}
$endpoint = getenv('MODXMCP_LEGACY_HTTP_URL');
$http = function ($action, $kind, array $data) use ($endpoint, $token) {
    // The old MCP client sends a top-level action/type/data JSON triplet.
    // Do not add modern CAS headers, approvals or new reserved fields.
    $wire = json_encode(array('action'=>$action,'type'=>$kind,'data'=>$data),
                        JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    if (!is_string($wire)) { throw new RuntimeException('Legacy JSON encode failed'); }
    $ch = curl_init($endpoint);
    curl_setopt_array($ch,array(
        CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>$wire,
        CURLOPT_HTTPHEADER=>array('Content-Type: application/json; charset=utf-8',
                                   'X-MCP-Token: '.$token),
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_CONNECTTIMEOUT=>5,
        CURLOPT_TIMEOUT=>25,
        CURLOPT_FOLLOWLOCATION=>false,
    ));
    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    $parsed = is_string($raw) ? json_decode($raw,true) : null;
    if (!is_array($parsed) || $status !== 200 ||
        !isset($parsed['success']) || $parsed['success'] !== true ||
        !array_key_exists('data',$parsed)) {
        // No token, page content or payload in CI diagnostic output.
        throw new RuntimeException('Legacy '.$action.'/'.$kind.
                                   ' failed HTTP '.$status.' ('.
                                   (is_array($parsed) ? (string)($parsed['error'] ?? 'response error')
                                                     : 'non-JSON response').')');
    }
    if (!array_key_exists('caps',$parsed) || !is_string($parsed['caps'])) {
        throw new RuntimeException('Legacy capability fingerprint absent');
    }
    return $parsed['data'];
};
// Existing legacy client response: additive properties are OK, removed or
// type-changed properties break old consumers.
$shape = function ($value) use (&$shape) {
    if (is_array($value)) {
        // PHP has one array type for both JSON [] and {}. Treat an empty
        // legacy response as an empty list, not an object with missing keys.
        $assoc = !empty($value) && array_keys($value) !== range(0,count($value)-1);
        if (!$assoc) {
            return array('list', $value ? $shape(reset($value)) : null);
        }
        $result = array();
        foreach ($value as $key=>$item) {
            $result[$key] = $shape($item);
        }
        ksort($result,SORT_STRING);
        return array('object',$result);
    }
    if (is_int($value) || is_float($value)) { return 'number'; }
    if (is_bool($value)) { return 'boolean'; }
    if (is_null($value)) { return 'null'; }
    return gettype($value);
};
$superset = function ($current,$previous,$at='response') use (&$superset) {
    if ($current === $previous) { return; }
    if (is_array($previous) && is_array($current)
        && isset($previous[0],$current[0]) && $previous[0]==='object'
        && $current[0]==='object') {
        foreach ($previous[1] as $key=>$part) {
            if (!array_key_exists($key,$current[1])) {
                throw new RuntimeException('Legacy response field disappeared: '.$at.'.'.$key);
            }
            $superset($current[1][$key],$part,$at.'.'.$key);
        }
        return;
    }
    if (is_array($previous) && is_array($current)
        && ($previous[0] ?? null)==='list' && ($current[0] ?? null)==='list') {
        if ($previous[1]===null) { return; }
        if ($current[1]===null) { throw new RuntimeException('Legacy list became empty: '.$at); }
        $superset($current[1],$previous[1],$at.'[]');
        return;
    }
    throw new RuntimeException('Legacy response type changed: '.$at);
};
$fixture = $root.'/modxmcp-legacy-http-compat-'.$platform.'.json';
$results = array();
$remember = function ($action,$kind,$data) use ($http,$shape,&$results) {
    $response = $http($action,$kind,$data);
    $results[$action.'/'.$kind] = $shape($response);
    return $response;
};
$cap = $remember('get_capabilities','',array());
if (!is_array($cap)) { throw new RuntimeException('Legacy get_capabilities response invalid'); }
$state = $remember('get_site_state','',array());
if (!is_array($state) || !array_key_exists('site_revision',$state)) {
    throw new RuntimeException('Legacy site state lost revision');
}
$resources = $remember('list_resources','',array('limit'=>20,'start'=>0));
if (!is_array($resources) || !isset($resources['results']) ||
    !is_array($resources['results'])) {
    throw new RuntimeException('Legacy resource list shape changed');
}
$existingChunks = $remember('list_elements','chunk',array('limit'=>50));
if (!is_array($existingChunks)) {
    throw new RuntimeException('Legacy element listing no longer returns an array');
}
// Perform the same legacy actions before and after the update.
// All created objects are deliberately unpublished / nonexecutable.
$suffix = $phase==='before' ? 'PRE' : 'POST';
$uniq = bin2hex(random_bytes(5));
$chunkName = 'Legacy_Compat_'.$platform.'_'.$suffix.'_'.$uniq;
$chunkText = 'Legacy MCP chunk content '.$suffix;
$created = $http('create_element','chunk',array(
    'name'=>$chunkName, 'content'=>$chunkText, 'static'=>0));
if (!is_array($created) || !isset($created['id']) || (int)$created['id']<1) {
    throw new RuntimeException('Old client chunk creation lost its ID');
}
$chunkId = (int)$created['id'];
$found = $remember('get_element','chunk',array('id'=>$chunkId));
if (!is_array($found) || (string)($found['snippet'] ?? '')!==$chunkText) {
    throw new RuntimeException('Old client chunk content readback failed');
}
$updatedText = 'Legacy MCP updated chunk '.$suffix;
$http('update_element','chunk',array(
    'id'=>$chunkId,'name'=>$chunkName,'content'=>$updatedText));
$found = $http('get_element','chunk',array('id'=>$chunkId));
if ((string)($found['snippet'] ?? '')!==$updatedText) {
    throw new RuntimeException('Old client chunk update did not persist');
}
$match = $http('list_elements','chunk',array('query'=>$chunkName,'limit'=>50));
if (!is_array($match)) {
    throw new RuntimeException('Old client chunk search failed');
}
$matchIds = array();
foreach ($match as $item) {
    if (isset($item['id']) && (int)$item['id']===$chunkId) {
        $matchIds[] = (int)$item['id'];
    }
}
if (count($matchIds)!==1) {
    throw new RuntimeException('Old client created chunk not findable');
}
// Test an actual pre-CAS-era template and TV definition, including the
// template relationship, not just a synthetic read-only TV request.
$templateName = 'Legacy_Template_'.$platform.'_'.$suffix.'_'.$uniq;
$template = $http('create_element','template',array(
    'name'=>$templateName,'content'=>'<main>[[*content]]</main>','static'=>0));
$templateId=(int)($template['id'] ?? 0);
if ($templateId<1) { throw new RuntimeException('Legacy template creation failed'); }
$templateFound=$remember('get_element','template',array('id'=>$templateId));
if (!is_array($templateFound) ||
    (string)($templateFound['content'] ?? '')!=='<main>[[*content]]</main>') {
    throw new RuntimeException('Legacy template readback mismatch');
}
$tvName = 'legacy_subtitle_'.$platform.'_'.$suffix.'_'.$uniq;
$tv=$http('create_element','tv',array(
    'name'=>$tvName,'caption'=>'Legacy subtitle','field_type'=>'text',
    'templates'=>array($templateId)));
$tvId=(int)($tv['id'] ?? 0);
if ($tvId<1) { throw new RuntimeException('Legacy TV definition creation failed'); }
$tvFound=$remember('get_element','tv',array('id'=>$tvId));
if (!is_array($tvFound) ||
    !in_array((string)$templateId,array_map('strval',(array)($tvFound['templates'] ?? array())),true)) {
    throw new RuntimeException('Legacy TV template assignment disappeared');
}
$pageName = 'Legacy Compat '.$platform.' '.$suffix.' '.$uniq;
$page = $http('create_element','resource',array(
    'name'=>$pageName, 'content'=>'Unpublished legacy content',
    'published'=>0,'parent'=>0,'context_key'=>'web',
    'template'=>$templateId,
    'alias'=>'legacy-compat-'.strtolower($suffix).'-'.$uniq));
if (!is_array($page) || (int)($page['id'] ?? 0)<1) {
    throw new RuntimeException('Old client resource creation failed');
}
$pageId=(int)$page['id'];
$pageFound=$http('get_element','resource',array('id'=>$pageId));
if (!is_array($pageFound) ||
    (string)($pageFound['pagetitle'] ?? '')!==$pageName ||
    (int)($pageFound['published'] ?? -1)!==0) {
    throw new RuntimeException('Legacy unpublished resource readback failed');
}
$http('update_element','resource',array(
    'id'=>$pageId,'name'=>$pageName,
    'content'=>'Updated unpublished legacy content'));
$pageFound=$http('get_element','resource',array('id'=>$pageId));
if ((string)($pageFound['content'] ?? '')!=='Updated unpublished legacy content') {
    throw new RuntimeException('Legacy resource update did not persist');
}
$tvValues = $remember('get_resource_tvs','',array('resource_id'=>$pageId));
if (!is_array($tvValues) || !array_key_exists('tvs',$tvValues)) {
    throw new RuntimeException('Legacy TV listing lost tvs field');
}
$present=false;
foreach ($tvValues['tvs'] as $entry) {
    if (($entry['name'] ?? '')===$tvName) { $present=true; }
}
if (!$present) { throw new RuntimeException('Legacy linked TV missing from resource'); }
$expectedValue='Legacy TV value '.$suffix;
$writtenTvs=$remember('update_resource_tvs','',array(
    'resource_id'=>$pageId,'tvs'=>array($tvName=>$expectedValue)));
if (!is_array($writtenTvs)) {
    throw new RuntimeException('Legacy TV mutation response changed');
}
$verifyTvs=$http('get_resource_tvs','',array('resource_id'=>$pageId));
$foundValue=null;
foreach ((array)($verifyTvs['tvs'] ?? array()) as $entry) {
    if (($entry['name'] ?? '')===$tvName) { $foundValue=$entry['value'] ?? null; }
}
if ((string)$foundValue!==$expectedValue) {
    throw new RuntimeException('Legacy TV value update did not persist');
}
if ($phase==='before') {
    if (file_exists($fixture)) { throw new RuntimeException('Legacy pre-upgrade fixture exists'); }
    $snapshot = array('schema'=>$results,'api_token_hash'=>hash('sha256',$token));
    if (file_put_contents($fixture,json_encode($snapshot),LOCK_EX)===false ||
        !chmod($fixture,0600)) {
        throw new RuntimeException('Cannot store private compatibility snapshot');
    }
} else {
    if (!is_file($fixture) || (fileperms($fixture)&0077)!==0) {
        throw new RuntimeException('Missing protected pre-upgrade snapshot');
    }
    $snapshot = json_decode(file_get_contents($fixture),true);
    if (!is_array($snapshot) ||
        !isset($snapshot['api_token_hash'],$snapshot['schema']) ||
        !hash_equals($snapshot['api_token_hash'],hash('sha256',$token))) {
        throw new RuntimeException('API token changed after component upgrade');
    }
    foreach ($snapshot['schema'] as $action=>$previous) {
        if (!array_key_exists($action,$results)) {
            throw new RuntimeException('Legacy API action removed: '.$action);
        }
        $superset($results[$action],$previous,$action);
    }
    unlink($fixture);
}
echo 'LEGACY_MCP_REAL_HTTP_'.strtoupper($phase).
     '_CLIENT_CONTRACT_OK '.$platform.' actions='.count($results)."\n";
