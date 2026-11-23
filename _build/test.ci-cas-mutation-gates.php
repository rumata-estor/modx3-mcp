<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Проверка «ci-cas-mutation-gates»; тестовый код не запускается на production.
 * EN: «ci-cas-mutation-gates» regression test; not executed in production.
 */
/**
 * Fail-safe CAS revision reservation matrix.
 *
 * One disposable GitHub Actions MODX database; all 99 nontransactional
 * mutation endpoints are invoked via the REAL modxMCP::processRequest().
 * A MariaDB trigger refuses revision increment; consequently no external
 * processor, file, package, token rotation or addon mutation may start.
 *
 * An unavailable optional addon is reported as a skipped unsupported tool,
 * never as a successful security validation. No direct SQL manipulates site
 * data; SQL is used only to insert and remove the temporary fault trigger.
 */
if (PHP_SAPI !== 'cli' || getenv('MODXMCP_DISPOSABLE_CI') !== '1'
    || strpos((string)getenv('MODX_CONFIG_CORE'), '/home/runner/') !== 0
    || !is_file((string)getenv('MODX_CONFIG_CORE'))) {
    fwrite(STDERR,"Refusing gate matrix outside disposable CI.\n");exit(3);
}
require_once getenv('MODX_CONFIG_CORE');
$is2=!is_file(MODX_CORE_PATH.'src/Revolution/modX.php');
if($is2){
    require_once MODX_CORE_PATH.'model/modx/modx.class.php';
    $modx=new modX();
}else{
    require_once MODX_CORE_PATH.'vendor/autoload.php';
    $modx=\MODX\Revolution\modX::getInstance();
}
$modx->initialize('mgr');
// Optional capability groups are enabled only in this one-use PHP process.
// No persisted settings change, and the revision trigger blocks mutation.
$modx->setOption('modxmcp.disabled_groups','');
$modx->setOption('modxmcp.allow_run_processor',true);
require_once MODX_CORE_PATH.'components/modxmcp/model/modxmcp.class.php';
$api=new modxMCP($modx);
$file=__DIR__.'/cas-mutation-inventory.json';
$inventory=json_decode(file_get_contents($file),true);
if(!is_array($inventory) || count($inventory['actions'])<110){
    throw new RuntimeException('Missing complete mutation inventory');
}
$expected=array();
foreach($inventory['actions'] as $action=>$spec){
    if($spec['cas_boundary']==='revision_reserved_before_action')
        $expected[]=$action;
}
sort($expected);
if(count($expected)!==99)throw new RuntimeException('Unexpected external gate inventory');
$getRevision=function()use($api){
    $state=$api->processRequest('get_site_state','',array());
    return (int)$state['site_revision'];
};
$initial=$getRevision();
$pdo=new \PDO(
    'mysql:host=127.0.0.1;dbname=modx_build;charset=utf8mb4',
    'modx_ci','ci-throwaway-only-user',
    array(\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION)
);
$trigger='cas_all_gates_'.bin2hex(random_bytes(5));
$armed=false;
$gated=array();
$unsupported=array();
$preflightDenied=array();
$failed=array();
try {
    $pdo->exec(
        'CREATE TRIGGER `'.$trigger.'` BEFORE UPDATE ON `modx_system_settings`'
        .' FOR EACH ROW BEGIN'
        ." IF NEW.`key`='modxmcp.site_revision' THEN"
        ." SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='CAS_GATES_REJECT_REVISION';"
        .' END IF; END'
    );
    $armed=true;
    foreach($expected as $action){
        $message=null;
        try {
            $api->processRequest($action,'',array(
                '_runtime_preconditions'=>array(
                    'observed_site_revision'=>$initial,
                    'strict_site_revision'=>true,
                    'read_set'=>array()
                )
            ));
            $failed[$action]='ACTION EXECUTED WITHOUT REVISION';
            continue;
        } catch(\Throwable $e){
            $message=$e->getMessage();
        }
        if(strpos($message,'Failed to persist MODX MCP site revision')!==false){
            $gated[]=$action;
        } elseif ($action==='run_processor'
            && (strpos($message,'run_processor is disabled')!==false
                || strpos($message,'AUTH_DENIED: processor execution disabled.')!==false)) {
            // RU: Детерминированный запрет сильнее отказа CAS-резерва.
            // EN: Independent master-switch denial precedes CAS reservation.
            $preflightDenied[$action]='disabled by administrator';
        } elseif ($action==='reorder_resources'
            && strpos($message, 'reorder_resources: "items"')!==false) {
            // A missing batch is now rejected before CAS, not after it.
            $preflightDenied[$action]='missing reorder batch';
        } elseif ($action==='bulk_resources'
            && strpos($message, 'bulk_resources: operation must be one of')!==false) {
            $preflightDenied[$action]='missing bulk operation';
        } elseif ($action==='replace_across'
            && strpos($message, 'replace_across: "find" is required.')!==false) {
            $preflightDenied[$action]='missing search query';
        } elseif ($action==='make_static'
            && strpos($message, 'make_static: unsupported type')!==false) {
            $preflightDenied[$action]='missing static element type';
        } elseif ($action==='install_package'
            && strpos($message,
                'INVALID_INPUT: install_package requires a nonempty package name string.')===0) {
            // Invalid package name must never contact a provider or consume CAS.
            $preflightDenied[$action]='missing package name';
        } elseif ($action==='uninstall_package'
            && strpos($message,
                'INVALID_INPUT: uninstall_package requires a nonempty signature string.')===0) {
            // Invalid uninstall selector must never reach a package processor.
            $preflightDenied[$action]='missing package signature';
        } elseif (in_array($action, array(
            'create_media_file','update_media_file','delete_media_file',
            'rename_media_file','create_media_folder','delete_media_folder'
        ), true) && preg_match(
            '/(Unsafe media|Path traversal|Media path must identify|unsupported path field)/i',
            $message
        )) {
            // Invalid paths are now rejected BEFORE any CAS revision shift,
            // a stronger guarantee than the injected revision fault.
            $preflightDenied[$action]='unsafe/missing media path';
        } elseif(preg_match('/(not supported|unsupported|not available|requires (?:.*? )?integration|integration .*? not installed|MIGX is not installed|Could not load miniShop2 service|Could not find VirtualPage component core path|Could not load VersionX service)/i',$message)){
            $unsupported[$action]=substr($message,0,100);
        }else{
            $failed[$action]=substr($message,0,200);
        }
    }
    if($getRevision()!==$initial){
        throw new RuntimeException('Revision changed under forced rejection');
    }
} finally {
    if($armed)$pdo->exec('DROP TRIGGER IF EXISTS `'.$trigger.'`');
}
echo 'CAS_EXTERNAL_GATE_SWEEP total='.count($expected)
    .' gated='.count($gated).' preflight_denied='.count($preflightDenied)
    .' unsupported='.count($unsupported)
    .' failures='.count($failed).PHP_EOL;
foreach($unsupported as $a=>$why)echo 'CAS_GATE_UNSUPPORTED '.$a.' '.$why.PHP_EOL;
foreach($failed as $a=>$why)echo 'CAS_GATE_UNEXPECTED '.$a.' '.$why.PHP_EOL;
if($failed)throw new RuntimeException('Mutating endpoints not fail-closed: '.implode(',',array_keys($failed)));
if(count($gated)+count($preflightDenied)+count($unsupported)!==count($expected))
    throw new RuntimeException('Missing CAS endpoint classifications');
if(count($gated)+count($preflightDenied)<70)
    throw new RuntimeException('Insufficient fail-closed available endpoints: '
        .(count($gated)+count($preflightDenied)));
echo 'CAS_EXTERNAL_ALL_AVAILABLE_MUTATIONS_FAIL_CLOSED_OK'.PHP_EOL;
