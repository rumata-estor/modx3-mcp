<?php
/** Disposable transport promotion check, 1.2.6-dev -> 1.2.6-pl. */
$root=getenv('RUNNER_TEMP');
$cfg=getenv('MODX_CONFIG_CORE');
$platform=getenv('BUILD_PLATFORM');
$mode=$argv[1] ?? '';
if (PHP_SAPI!=='cli' || getenv('GITHUB_ACTIONS')!=='true'
    || !in_array($mode,array('before','after'),true)
    || !in_array($platform,array('modx2','modx3'),true)
    || !$root || !$cfg
    || realpath($cfg)!==realpath($root.'/modx-site/config.core.php')
    || getenv('MODX_DOCUMENT_ROOT')!==$root.'/modx-site') {
    throw new RuntimeException('Refusing promotion test outside disposable CI');
}
require $cfg;
if ($platform==='modx3') {
    require MODX_CORE_PATH.'vendor/autoload.php';
    $modx=\MODX\Revolution\modX::getInstance();
    $settingsClass='MODX\\Revolution\\modSystemSetting';
    $packageClass='MODX\\Revolution\\Transport\\modTransportPackage';
} else {
    require MODX_CORE_PATH.'model/modx/modx.class.php';
    $modx=new modX();
    $settingsClass='modSystemSetting';
    $packageClass='transport.modTransportPackage';
}
$modx->initialize('mgr');
$dev=$platform.'mcp-1.2.6-dev';
$pl=$platform.'mcp-1.2.6-pl';
$devPkg=$modx->getObject($packageClass,array('signature'=>$dev));
if (!$devPkg || !$devPkg->get('installed')) {
    throw new RuntimeException('Dev package must be installed before stable candidate');
}
$rows=$modx->getCollection($settingsClass,array('key:LIKE'=>'modxmcp.%'));
$hashes=array();
foreach ($rows as $row) {
    $key=(string)$row->get('key');
    $hashes[$key]=hash('sha256',(string)$row->get('value'));
}
ksort($hashes,SORT_STRING);
if (count($hashes)!==23 || !isset($hashes['modxmcp.api_token'])) {
    throw new RuntimeException('Unexpected CMS setting count for upgrade');
}
$sql='SELECT COUNT(*) FROM information_schema.tables WHERE TABLE_SCHEMA=DATABASE()';
$result=$modx->query($sql);
if (!$result) { throw new RuntimeException('Schema cannot be inspected'); }
$tableCount=(int)$result->fetchColumn();
$snapshot=$root.'/modxmcp-ci-126-pl-promotion.json';
if ($mode==='before') {
    if (file_exists($snapshot) ||
        $modx->getObject($packageClass,array('signature'=>$pl))) {
        throw new RuntimeException('Stable signature or snapshot exists before test');
    }
    if (file_put_contents($snapshot,json_encode(array(
        'settings'=>$hashes,'tables'=>$tableCount)),LOCK_EX)===false
        || !chmod($snapshot,0600)) {
        throw new RuntimeException('Cannot securely store version baseline');
    }
    echo "MODX126PL_PROMOTION_BASELINE_OK $platform\n";
    exit(0);
}
if (!is_file($snapshot) || (fileperms($snapshot)&0077)!==0) {
    throw new RuntimeException('Stable promotion snapshot absent or unsafe');
}
$before=json_decode(file_get_contents($snapshot),true);
if (!is_array($before) || ($before['settings'] ?? null)!==$hashes
    || ($before['tables'] ?? null)!==$tableCount) {
    throw new RuntimeException('1.2.6-pl changed settings or schema');
}
$stable=$modx->getObject($packageClass,array('signature'=>$pl));
if (!$stable || !$stable->get('installed')) {
    throw new RuntimeException('1.2.6-pl transport not installed');
}
$source=MODX_CORE_PATH.'components/modxmcp/model/modxmcp.class.php';
$cas=MODX_CORE_PATH.'components/modxmcp/endpoint/reviewed-cas-processor-v1.php';
if (!is_file($source) || !is_file($cas)) {
    throw new RuntimeException('New protected CAS files not installed');
}
require_once $source;
if (!defined('modxMCP::VERSION') || modxMCP::VERSION!=='1.2.6') {
    throw new RuntimeException('Installed model not upgraded to 1.2.6');
}
unlink($snapshot);
echo "MODX126PL_REAL_INSTALL_SETTINGS_SCHEMA_AND_TOKEN_PRESERVED_OK $platform\n";
