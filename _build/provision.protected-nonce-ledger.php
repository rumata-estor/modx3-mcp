<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Служебный сценарий сборки/проверки MODX MCP: provision.protected-nonce-ledger.
 * EN: MODX MCP build/verification helper: provision.protected-nonce-ledger.
 */
/**
 * Explicit opt-in CLI migration for connector-owned single-use permit ledger.
 *
 * Does NOT enable protected mode, does NOT install a signing key, and does
 * NOT mutate MODX content. Never invoked automatically by the package.
 *
 * For production, requires an independent administrative approval before
 * setting MODXMCP_PROTECTED_SETUP_CONFIRMED=YES. No site is provisioned in
 * this branch: disposable CI alone exercises the migration.
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR,"CLI only.\n");exit(3);
}
$ci = getenv('MODXMCP_DISPOSABLE_CI')==='1'
  && strpos((string)getenv('MODX_CONFIG_CORE'),'/home/runner/')===0;
if (!$ci && getenv('MODXMCP_PROTECTED_SETUP_CONFIRMED')!=='YES') {
    fwrite(STDERR,"Protected setup confirmation required; no changes made.\n");
    exit(3);
}
$config=getenv('MODX_CONFIG_CORE');
if (!is_string($config) || $config==='' || !is_file($config)) {
    fwrite(STDERR,"Missing MODX_CONFIG_CORE; no changes made.\n");
    exit(3);
}
require_once $config;
$is2=!is_file(MODX_CORE_PATH.'src/Revolution/modX.php');
if($is2){
    require_once MODX_CORE_PATH.'model/modx/modx.class.php';
    $modx=new modX();
}else{
    require_once MODX_CORE_PATH.'vendor/autoload.php';
    $modx=\MODX\Revolution\modX::getInstance();
}
$modx->initialize('mgr');
// The migration is deliberately independent of the service account and
// dispatcher: MODX 2/3 may not have initialized its modular autoloader yet.
require_once MODX_CORE_PATH.'components/modxmcp/src/Security/ProtectedAuthorization.php';
if(!class_exists('ModxMcp\Security\ProtectedAuthorization')){
    fwrite(STDERR,"Protected authorization support missing; nothing created.\n");exit(3);
}
$name=\ModxMcp\Security\ProtectedAuthorization::ledgerTable($modx);
$ddl='CREATE TABLE IF NOT EXISTS `'.$name.'` ('
    .' `nonce_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,'
    .' `issued_at` BIGINT UNSIGNED NOT NULL,'
    .' `expires_at` BIGINT UNSIGNED NOT NULL,'
    .' PRIMARY KEY (`nonce_hash`)'
    .') ENGINE=InnoDB';
if($modx->exec($ddl)===false) {
    throw new RuntimeException('Could not provision protected nonce ledger.');
}
// Existing tables must have the expected engine; an unsafe/renamed table
// must not be treated as protection against concurrent replay.
$statement=$modx->prepare('SHOW TABLE STATUS LIKE ?');
if(!$statement || !$statement->execute(array($name))){
    throw new RuntimeException('Could not verify protected nonce ledger.');
}
$meta=$statement->fetch(PDO::FETCH_ASSOC);
if(!is_array($meta) || !isset($meta['Engine'])
   || strcasecmp((string)$meta['Engine'],'InnoDB')!==0){
    throw new RuntimeException('Protected nonce ledger is not InnoDB.');
}
$columns=$modx->query('SHOW COLUMNS FROM `'.$name.'`');
$seen=array();
foreach($columns->fetchAll(PDO::FETCH_ASSOC) as $field) {
    $seen[$field['Field']]=$field;
}
if(count($seen)!==3
  || !isset($seen['nonce_hash'],$seen['issued_at'],$seen['expires_at'])
  || !in_array(strtolower((string)$seen['nonce_hash']['Type']),array('char(64)'),true)
  || strtoupper((string)$seen['nonce_hash']['Key'])!=='PRI') {
    throw new RuntimeException('Protected nonce ledger schema mismatch.');
}
echo 'PROTECTED_NONCE_LEDGER_PROVISIONED_AND_VERIFIED'.PHP_EOL;
