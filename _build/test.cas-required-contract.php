<?php
/**
 * Mandatory, strict CAS precondition contract before ANY mutation.
 * No MODX, database or production access. Standalone PHP 7.4+.
 */
class ModxMCPClientException extends Exception {}
require_once __DIR__ . '/../core/components/modxmcp/src/Tools/StateSupport.php';
use ModxMcp\Tools\StateSupport;
$checks=0;
function allowCas($action,$payload) {
    global $checks;
    StateSupport::requireMutationPreconditions($action,$payload);
    $checks++;
}
function denyCas($action,$payload,$reason) {
    global $checks;
    try { StateSupport::requireMutationPreconditions($action,$payload); }
    catch(ModxMCPClientException $e) {
        if (strpos($e->getMessage(),'CAS_REQUIRED')===false
            || strpos($e->getMessage(),$reason)===false) throw $e;
        $checks++; return;
    }
    throw new RuntimeException('Missing CAS refusal: '.$reason);
}
$valid=array(
    'observed_site_revision'=>12,
    'strict_site_revision'=>true,
    'read_set'=>array()
);
allowCas('create_element',$valid);
allowCas('delete_media_file',$valid);
allowCas('run_processor',$valid);
allowCas('delete_user',$valid);
allowCas('update_resource_tvs',$valid+array('expected_tvs'=>array('tvname'=>'@INHERIT 0')));
denyCas('delete_element',null,'requires observed_site_revision');
denyCas('delete_element',array(),'requires observed_site_revision');
denyCas('delete_element',array(
    'observed_site_revision'=>12, 'strict_site_revision'=>true),'requires observed_site_revision');
denyCas('delete_element',array(
    'observed_site_revision'=>12,'strict_site_revision'=>false,'read_set'=>array()),
    'strict_site_revision=true');
denyCas('delete_element',array(
    'observed_site_revision'=>12,'strict_site_revision'=>1,'read_set'=>array()),
    'strict_site_revision=true');
denyCas('delete_element',array(
    'observed_site_revision'=>-1,'strict_site_revision'=>true,'read_set'=>array()),
    'nonnegative integer');
denyCas('delete_element',array(
    'observed_site_revision'=>'12x','strict_site_revision'=>true,'read_set'=>array()),
    'nonnegative integer');
denyCas('delete_element',array(
    'observed_site_revision'=>12,'strict_site_revision'=>true,'read_set'=>array(123)),
    'malformed read_set');
denyCas('delete_element',array(
    'observed_site_revision'=>12,'strict_site_revision'=>true,'read_set'=>array(array(
        'type'=>'resource','id'=>5,'fingerprint'=>'bogus'
    ))),'malformed read_set');
denyCas('update_resource_tvs',$valid,'requires raw expected_tvs');
$read=$valid;
$read['read_set']=array(array('role'=>'target','type'=>'resource','id'=>13,
    'fingerprint'=>str_repeat('a',64)));
allowCas('update_element',$read);
echo 'CAS_MANDATORY_CONTRACT_UNIT_OK checks='.$checks.PHP_EOL;

