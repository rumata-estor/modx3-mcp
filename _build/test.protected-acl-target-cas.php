<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Контракт обязательного CAS-отпечатка для защищённых ACL-подобных объектов.
 * EN: Require exact server-issued target fingerprints for protected ACL writes.
 */
namespace ModxMcp\Tools {
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/StateSupport.php';
}
namespace {
class ModxMCPClientException extends \RuntimeException {}
$checks=0;
$actions=array(
    'update_user'=>'user','delete_user'=>'user',
    'update_user_group'=>'user_group','delete_user_group'=>'user_group',
    'update_role'=>'role','delete_role'=>'role',
);
foreach($actions as $action=>$type) {
    $preconditions=array('observed_site_revision'=>7,
        'strict_site_revision'=>true,'read_set'=>array());
    $bad=array(
        $preconditions,
        array_merge($preconditions,array('read_set'=>array(array(
            'role'=>'target','type'=>$type,'id'=>88,
            'fingerprint'=>str_repeat('a',64))))),
        array_merge($preconditions,array('read_set'=>array(array(
            'role'=>'target','type'=>$type,'id'=>87,'fields'=>array('name'),
            'fingerprint'=>str_repeat('a',64))))),
    );
    foreach($bad as $guard) {
        try {
            \ModxMcp\Tools\StateSupport::requireProtectedTargetReadSet(
                $action,array('id'=>87),$guard
            );
            throw new \RuntimeException('ACL_TARGET_CAS_ACCEPTED_INVALID_'.$action);
        } catch(ModxMCPClientException $e) {
            if(strpos($e->getMessage(),'CAS_REQUIRED:')!==0)throw $e;
            $checks++;
        }
    }
    $valid=$preconditions;
    $valid['read_set']=array(array('role'=>'target','type'=>$type,'id'=>87,
        'fingerprint'=>str_repeat('b',64)));
    $got=\ModxMcp\Tools\StateSupport::requireProtectedTargetReadSet(
        $action,array('id'=>87),$valid
    );
    if($got!==$valid)throw new \RuntimeException('ACL_TARGET_VALID_REJECTED_'.$action);
    $checks++;
}
echo 'PROTECTED_ACL_TARGET_CAS_CONTRACT_OK checks='.$checks.PHP_EOL;
}
