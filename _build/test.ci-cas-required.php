<?php
/**
 * Mandatory CAS negative-test matrix, MODX2/3 disposable installations only.
 * All client/server requests use modxMCP::processRequest. No direct SQL or
 * direct MODX resource mutation. All denied requests must leave site unchanged.
 */
if (PHP_SAPI !== 'cli' || getenv('MODXMCP_DISPOSABLE_CI') !== '1'
    || strpos((string)getenv('MODX_CONFIG_CORE'),'/home/runner/')!==0
    || !is_file((string)getenv('MODX_CONFIG_CORE'))) {
    fwrite(STDERR,"Refusing mandatory CAS test outside disposable CI.\n");exit(3);
}
require_once getenv('MODX_CONFIG_CORE');
$is2 = !is_file(MODX_CORE_PATH.'src/Revolution/modX.php');
if ($is2) {
    require_once MODX_CORE_PATH.'model/modx/modx.class.php';
    $modx = new modX();
} else {
    require_once MODX_CORE_PATH.'vendor/autoload.php';
    $modx = \MODX\Revolution\modX::getInstance();
}
$modx->initialize('mgr');
$modx->setOption('modxmcp.disabled_groups','');
require_once MODX_CORE_PATH.'components/modxmcp/model/modxmcp.class.php';
$api = new modxMCP($modx);
$inventory=json_decode(file_get_contents(__DIR__.'/cas-mutation-inventory.json'),true);
if (count($inventory['actions'])!==116)throw new RuntimeException('Missing 116 tool entries');
$state=$api->processRequest('get_site_state','',array());
$revision=(int)$state['site_revision'];
$checks=0;
foreach(array_keys($inventory['actions']) as $action) {
    try { $api->processRequest($action,'',array()); }
    catch(\ModxMCPClientException $e) {
        if(strpos($e->getMessage(),'CAS_REQUIRED:')!==0) {
            throw new RuntimeException('Unexpected denial for '.$action.': '.$e->getMessage());
        }
        $checks++;continue;
    }
    throw new RuntimeException('Mutation without CAS unexpectedly succeeded: '.$action);
}
if((int)$api->processRequest('get_site_state','',array())['site_revision']!==$revision)
    throw new RuntimeException('Revision changed after denied requests');

$invalidGuards=array(
    array(),
    array('observed_site_revision'=>$revision,'strict_site_revision'=>true),
    array('observed_site_revision'=>$revision,'strict_site_revision'=>false,'read_set'=>array()),
    array('observed_site_revision'=>$revision,'strict_site_revision'=>1,'read_set'=>array()),
    array('observed_site_revision'=>-1,'strict_site_revision'=>true,'read_set'=>array()),
    array('observed_site_revision'=>$revision,'strict_site_revision'=>true,'read_set'=>array(
        array('type'=>'resource','id'=>1,'fingerprint'=>'invalid')
    )),
);
foreach($invalidGuards as $guard){
    try{$api->processRequest('create_element','',array(
        'type'=>'resource','name'=>'CAS negative fixture '.bin2hex(random_bytes(5)),
        'published'=>0,'hidemenu'=>1,'template'=>0,'parent'=>0,
        '_runtime_preconditions'=>$guard
    ));}
    catch(\ModxMCPClientException $e){
        if(strpos($e->getMessage(),'CAS_REQUIRED:')!==0)throw $e;
        $checks++;continue;
    }
    throw new RuntimeException('Malformed CAS guard accepted');
}
// Nonexistent revision must fail before any write, regardless of payload.
$name='CAS stale fixture '.bin2hex(random_bytes(5));
try{
    $api->processRequest('create_element','',array(
        'type'=>'resource','name'=>$name,
        'alias'=>'cas-stale-'.bin2hex(random_bytes(5)),
        'published'=>0,'hidemenu'=>1,'template'=>0,'parent'=>0,
        '_runtime_preconditions'=>array(
            'observed_site_revision'=>$revision+2,
            'strict_site_revision'=>true,'read_set'=>array()
        ),
    ));
    throw new RuntimeException('Stale CAS mutation unexpectedly succeeded');
}catch(\ModxMCPClientException $e){
    if(strpos($e->getMessage(),'STALE_STATE')===false)throw $e;
    $checks++;
}
$listed=$api->processRequest('list_elements','',array(
    'type'=>'resource','query'=>$name,'limit'=>25
));
foreach($listed as $row){
    if (isset($row['name']) && $row['name']===$name) {
        throw new RuntimeException('Stale CAS unexpectedly created a resource');
    }
}
if((int)$api->processRequest('get_site_state','',array())['site_revision']!==$revision)
    throw new RuntimeException('Stale rejected mutation incremented site revision');
$checks++;

// A broken modular runtime must not silently switch to the old unguarded
// processor dispatcher. This is the specific bypass we are removing.
$ref = new ReflectionProperty('modxMCP','modularRuntime');
$ref->setAccessible(true);
$saved = $ref->getValue($api);
if (!$saved)throw new RuntimeException('Modular runtime missing in disposable test');
$ref->setValue($api,null);
try {
    $api->processRequest('create_element','',array(
        'type'=>'resource','name'=>'CAS fail closed no runtime',
        '_runtime_preconditions'=>array('observed_site_revision'=>$revision,
            'strict_site_revision'=>true,'read_set'=>array()),
    ));
    throw new RuntimeException('Legacy fallback accepted mutation');
} catch(\ModxMCPClientException $e) {
    if(strpos($e->getMessage(),'CAS_RUNTIME_REQUIRED')!==0)throw $e;
    $checks++;
} finally {
    $ref->setValue($api,$saved);
}
echo 'CAS_REQUIRED_ALL_MUTATIONS_FAIL_CLOSED_OK actions=116 checks='.$checks.PHP_EOL;
