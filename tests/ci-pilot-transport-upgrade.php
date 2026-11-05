<?php
/**
 * Isolated MODX 2/3 transport regression:
 * 1.2.5-pl already installed -> 1.2.6-dev test-only upgrade.
 * This script NEVER executes on a live site. No new tables, no CMS writes.
 */
$root=getenv('RUNNER_TEMP');
$config=getenv('MODX_CONFIG_CORE');
$platform=getenv('BUILD_PLATFORM');
$mode=isset($argv[1]) ? $argv[1] : '';
if (PHP_SAPI!=='cli' || getenv('GITHUB_ACTIONS')!=='true' ||
    !in_array($platform,array('modx2','modx3'),true) ||
    !in_array($mode,array('before','after'),true) ||
    !is_string($root) || $root==='' ||
    realpath($config)!==realpath($root.'/modx-site/config.core.php') ||
    getenv('MODX_DOCUMENT_ROOT')!==$root.'/modx-site') {
    throw new RuntimeException('refusing non-disposable pilot upgrade test');
}
require_once $config;
if ($platform==='modx3') {
    require_once MODX_CORE_PATH.'vendor/autoload.php';
    $modx=\MODX\Revolution\modX::getInstance();
    $class='MODX\\Revolution\\modSystemSetting';
    $package='MODX\\Revolution\\Transport\\modTransportPackage';
} else {
    require_once MODX_CORE_PATH.'model/modx/modx.class.php';
    $modx=new modX();
    $class='modSystemSetting';
    $package='transport.modTransportPackage';
}
$modx->initialize('mgr');
$oldSignature=$platform==='modx3'?'modx3mcp-1.2.5-pl':'modx2mcp-1.2.5-pl';
$newSignature=$platform==='modx3'?'modx3mcp-1.2.6-dev':'modx2mcp-1.2.6-dev';
$old=$modx->getObject($package,array('signature'=>$oldSignature));
if (!$old || !$old->get('installed')) {
    throw new RuntimeException('pilot upgrade requires installed 1.2.5-pl');
}
$settings=$modx->getCollection($class,array('key:LIKE'=>'modxmcp.%'));
$values=array();
foreach ($settings as $setting) {
    $values[(string)$setting->get('key')]=hash('sha256',(string)$setting->get('value'));
}
ksort($values);
if (count($values)!==23 ||
    !isset($values['modxmcp.api_token'],$values['modxmcp.site_revision'])) {
    throw new RuntimeException('unexpected pre-upgrade setting snapshot');
}
$countStmt=$modx->query('SELECT COUNT(*) FROM information_schema.tables '
    .'WHERE TABLE_SCHEMA=DATABASE()');
if (!$countStmt) {
    throw new RuntimeException('cannot count existing CI schema');
}
$tableCount=(int)$countStmt->fetchColumn();
$snapshot=$root.'/modxmcp-ci-pilot-upgrade-snapshot.json';
if ($mode==='before') {
    if (is_file($snapshot) ||
        $modx->getObject($package,array('signature'=>$newSignature))) {
        throw new RuntimeException('pilot transport already installed before upgrade');
    }
    $record=array('setting_sha256'=>$values,'schema_table_count'=>$tableCount);
    if (file_put_contents($snapshot,json_encode($record))===false ||
        !chmod($snapshot,0600)) {
        throw new RuntimeException('cannot save private CI snapshot');
    }
    echo "CAS_PILOT_UPGRADE_PRIOR_125_SNAPSHOT_OK $platform\n";
    exit(0);
}
if (!is_file($snapshot) || (fileperms($snapshot)&0077)!==0) {
    throw new RuntimeException('missing or unsafe upgrade snapshot');
}
$previous=json_decode(file_get_contents($snapshot),true);
if (!is_array($previous) ||
    !isset($previous['setting_sha256'],$previous['schema_table_count'])) {
    throw new RuntimeException('invalid upgrade snapshot');
}
if ($previous['setting_sha256']!==$values) {
    throw new RuntimeException('test upgrade modified a preexisting system setting');
}
if ((int)$previous['schema_table_count']!==$tableCount) {
    throw new RuntimeException('test upgrade added or removed a database table');
}
$new=$modx->getObject($package,array('signature'=>$newSignature));
if (!$new || !$new->get('installed')) {
    throw new RuntimeException('distinct test-only version did not install');
}
$model=MODX_CORE_PATH.'components/modxmcp/model/modxmcp.class.php';
$cas=MODX_CORE_PATH.'components/modxmcp/endpoint/chunk-cas-processor-v2.php';
if (!is_file($model) || !is_file($cas)) {
    throw new RuntimeException('new pilot CAS files are absent');
}
require_once $model;
if (!defined('modxMCP::VERSION') || modxMCP::VERSION!=='1.2.6') {
    throw new RuntimeException('transport did not replace old model with pilot');
}
unlink($snapshot);
echo "CAS_PILOT_REAL_125_TO_126DEV_UPGRADE_PRESERVED_SETTINGS_NO_TABLES_OK $platform\n";
