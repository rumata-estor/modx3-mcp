<?php
/**
 * Disposable CI setup ONLY. Configures the protected mode via the MODX
 * administrative setting model, never from an MCP business mutation.
 * Never run on a real site. The original 1.2.7 API token is preserved.
 */
if (PHP_SAPI!=='cli' || getenv('MODXMCP_DISPOSABLE_CI')!=='1'
    || strpos((string)getenv('MODX_CONFIG_CORE'),'/home/runner/')!==0
    || !is_file((string)getenv('MODX_CONFIG_CORE')) || $argc!==4)exit(3);
$operation=$argv[1];$pubFile=$argv[2];$tokenFile=$argv[3];
if (!in_array($operation,array('enable','disable'),true)
    || strpos((string)$pubFile,'/home/runner/')!==0
    || strpos((string)$tokenFile,'/home/runner/')!==0)exit(3);
require_once getenv('MODX_CONFIG_CORE');
$is2=!is_file(MODX_CORE_PATH.'src/Revolution/modX.php');
if($is2){require_once MODX_CORE_PATH.'model/modx/modx.class.php';$modx=new modX();}
else{require_once MODX_CORE_PATH.'vendor/autoload.php';$modx=\MODX\Revolution\modX::getInstance();}
$modx->initialize('mgr');
$cls=$is2?'modSystemSetting':'MODX\\Revolution\\modSystemSetting';
function setting($key,$value) {
    global $modx,$cls;
    $object=$modx->getObject($cls,array('key'=>$key));
    if(!$object)throw new RuntimeException('Required security setting missing: '.$key);
    $object->set('value',(string)$value);
    if(!$object->save())throw new RuntimeException('Disposable security setting failed');
}
if($operation==='enable'){
    $publicKey=trim((string)file_get_contents($pubFile));
    if(!preg_match('/\A[0-9a-f]{64}\z/D',$publicKey))
        throw new RuntimeException('Invalid CI public key');
    $token=$modx->getObject($cls,array('key'=>'modxmcp.api_token'));
    if(!$token || !preg_match('/\A[0-9a-f]{64}\z/D',(string)$token->get('value')))
        throw new RuntimeException('Disposable MCP token missing');
    file_put_contents($tokenFile,(string)$token->get('value'));
    chmod($tokenFile,0600);
    setting('modxmcp.permit_public_key',$publicKey);
    setting('modxmcp.permit_audience','ci-protected');
    setting('modxmcp.permit_site_id','ci-http-'.($is2?'modx2':'modx3'));
    setting('modxmcp.permit_contour','test');
    setting('modxmcp.permit_allowed_actions',
        'create_element,update_element,delete_element');
    setting('modxmcp.enabled','1');
    setting('modxmcp.external_authorization_required','1');
    $manager=$modx->getCacheManager();
    if($manager)$manager->refresh();
    echo 'PROTECTED_HTTP_DISPOSABLE_POLICY_ENABLED'.PHP_EOL;
}else{
    setting('modxmcp.external_authorization_required','0');
    setting('modxmcp.enabled','0');
    if (is_file($tokenFile))@unlink($tokenFile);
    $manager=$modx->getCacheManager();
    if($manager)$manager->refresh();
    echo 'PROTECTED_HTTP_DISPOSABLE_POLICY_DISABLED'.PHP_EOL;
}
