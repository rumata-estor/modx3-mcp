<?php
/**
 * Disposable MODX historical upgrade regression:
 * 1.2.3 -> 1.2.4 -> 1.2.5 while preserving preexisting configuration.
 * The 1.2.5 -> 1.2.6-dev path is independently tested in the same CI job.
 */
$root=getenv('RUNNER_TEMP');
$config=getenv('MODX_CONFIG_CORE');
$platform=getenv('BUILD_PLATFORM');
$stage=isset($argv[1]) ? $argv[1] : '';
if (PHP_SAPI!=='cli' || getenv('GITHUB_ACTIONS')!=='true' ||
    !in_array($platform,array('modx2','modx3'),true) ||
    !in_array($stage,array('baseline','v124','v125'),true) ||
    !is_string($root) || $root==='' || !is_string($config) ||
    realpath($config)!==realpath($root.'/modx-site/config.core.php') ||
    getenv('MODX_DOCUMENT_ROOT')!==$root.'/modx-site') {
    throw new RuntimeException('Historical upgrade test is disposable-CI-only');
}
require $config;
if ($platform==='modx3') {
    require MODX_CORE_PATH.'vendor/autoload.php';
    $modx=\MODX\Revolution\modX::getInstance();
    $settingClass='MODX\\Revolution\\modSystemSetting';
    $packageClass='MODX\\Revolution\\Transport\\modTransportPackage';
} else {
    require MODX_CORE_PATH.'model/modx/modx.class.php';
    $modx=new modX();
    $settingClass='modSystemSetting';
    $packageClass='transport.modTransportPackage';
}
$modx->initialize('mgr');
$expectedVersion=array('baseline'=>'1.2.3','v124'=>'1.2.4','v125'=>'1.2.5');
$signature=$platform.'mcp-'.$expectedVersion[$stage].'-pl';
$package=$modx->getObject($packageClass,array('signature'=>$signature));
if (!$package || !$package->get('installed')) {
    throw new RuntimeException('Historical transport not installed: '.$signature);
}
$settings=$modx->getCollection($settingClass,array('key:LIKE'=>'modxmcp.%'));
$values=array();
foreach ($settings as $item) {
    $values[(string)$item->get('key')]=(string)$item->get('value');
}
ksort($values,SORT_STRING);
if (!isset($values['modxmcp.api_token'],$values['modxmcp.require_https'],
           $values['modxmcp.enabled']) ||
    !preg_match('/\A[0-9a-f]{64}\z/D',$values['modxmcp.api_token'])) {
    throw new RuntimeException('Historical release settings or token missing');
}
$file=$root.'/modxmcp-historical-upgrade-'.$platform.'.json';
if ($stage==='baseline') {
    if (is_file($file)) { throw new RuntimeException('Historical snapshot exists'); }
    // Simulate real administrator-edited settings in the OLD release.
    // All following updates must keep both changes without a reset.
    foreach (array('modxmcp.enabled'=>'1',
                   'modxmcp.require_https'=>'0') as $key=>$value) {
        $setting=$modx->getObject($settingClass,array('key'=>$key));
        $setting->set('value',$value);
        if (!$setting->save()) { throw new RuntimeException('Cannot edit old setting'); }
        $values[$key]=$value;
    }
    if (!$modx->getCacheManager()->refresh()) {
        throw new RuntimeException('Historical settings cache refresh failed');
    }
    $hashes=array();
    foreach ($values as $key=>$value) { $hashes[$key]=hash('sha256',$value); }
    $record=array('version'=>$expectedVersion[$stage],'settings_sha256'=>$hashes);
    if (file_put_contents($file,json_encode($record),LOCK_EX)===false ||
        !chmod($file,0600)) {
        throw new RuntimeException('Historical snapshot creation failed');
    }
} else {
    if (!is_file($file) || (fileperms($file)&0077)!==0) {
        throw new RuntimeException('Missing private historical snapshot');
    }
    $snapshot=json_decode(file_get_contents($file),true);
    if (!is_array($snapshot) ||
        !isset($snapshot['settings_sha256']) ||
        $snapshot['version']!=='1.2.3') {
        throw new RuntimeException('Invalid historical snapshot');
    }
    foreach ($snapshot['settings_sha256'] as $key=>$expected) {
        if (!array_key_exists($key,$values) ||
            !hash_equals($expected,hash('sha256',$values[$key]))) {
            // Do not leak the API token or its digest in CI output.
            throw new RuntimeException('Historical administrator setting changed: '.$key);
        }
    }
    if ($stage==='v125') { unlink($file); }
}
echo 'LEGACY_123_SETTINGS_TOKEN_PRESERVED_AT_'.strtoupper($stage).
     ' '.$platform.' existing='.count($values)."\n";
