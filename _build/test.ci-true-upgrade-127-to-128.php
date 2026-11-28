<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Тест реального обновления 1.2.7 → 1.2.8 на одноразовом MODX.
 * EN: Disposable true transport-package upgrade 1.2.7 -> staged 1.2.8.
 */
if(PHP_SAPI!=='cli' || getenv('MODXMCP_DISPOSABLE_CI')!=='1'
 || strpos((string)getenv('MODX_CONFIG_CORE'),'/home/runner/')!==0
 || !is_file((string)getenv('MODX_CONFIG_CORE'))
 || strpos((string)getenv('RUNNER_TEMP'),'/home/runner/')!==0) {
    fwrite(STDERR,"Refusing package upgrade test outside disposable CI.\n");
    exit(3);
}
$mode=isset($argv[1])?(string)$argv[1]:'';
if(!in_array($mode,array('before','after'),true))exit(4);
require getenv('MODX_CONFIG_CORE');
$is2=!is_file(MODX_CORE_PATH.'src/Revolution/modX.php');
if($is2){
    require_once MODX_CORE_PATH.'model/modx/modx.class.php';
    $modx=new modX();$settingClass='modSystemSetting';
}else{
    require_once MODX_CORE_PATH.'vendor/autoload.php';
    $modx=\MODX\Revolution\modX::getInstance();
    $settingClass='MODX\Revolution\modSystemSetting';
}
$modx->initialize('mgr');
require_once MODX_CORE_PATH.'components/modxmcp/model/modxmcp.class.php';
$api=new modxMCP($modx);
$manifest=getenv('RUNNER_TEMP').'/modxmcp-upgrade-state.json';
function upgradeCheck($condition,$message) {
    if(!$condition)throw new RuntimeException('UPGRADE_127_128_FAILED '.$message);
}
function upgradeSetting($modx,$cls,$key) {
    $setting=$modx->getObject($cls,array('key'=>$key));
    upgradeCheck($setting!==null&&$setting!==false,'missing system setting '.$key);
    return $setting;
}
function upgradeRpc($api,$action,$data=array()) {
    return $api->processRequest($action,'',$data);
}
function upgradeGuard($api) {
    $state=upgradeRpc($api,'get_site_state');
    return array('observed_site_revision'=>(int)$state['site_revision'],
        'strict_site_revision'=>true,'read_set'=>array());
}
if($mode==='before'){
    upgradeCheck(modxMCP::VERSION==='1.2.7','historical installed release version');
    $token=(string)upgradeSetting($modx,$settingClass,'modxmcp.api_token')->get('value');
    upgradeCheck(strlen($token)===64,'token exists in published 1.2.7 install');
    $setting=upgradeSetting($modx,$settingClass,'modxmcp.max_read_bytes');
    $setting->set('value','131072');
    upgradeCheck($setting->save(),'cannot persist administrator override');
    $enabled=(string)upgradeSetting($modx,$settingClass,'modxmcp.enabled')->get('value');
    $saved=array('token_sha256'=>hash('sha256',$token),
        'max_read_bytes'=>'131072','enabled'=>$enabled);
    file_put_contents($manifest,json_encode($saved));
    echo 'UPGRADE_127_BASELINE_PUBLISHED_PACKAGE_OK'.PHP_EOL;
    exit(0);
}
$expectedVersion = getenv('RELEASE_VERSION');
// Historical upgrade workflows predate release-version.json.
if ($expectedVersion === false) { $expectedVersion = '1.2.8'; }
upgradeCheck(is_string($expectedVersion) && $expectedVersion !== '' && modxMCP::VERSION === $expectedVersion, 'new installed package version');
$saved=json_decode(file_get_contents($manifest),true);
upgradeCheck(is_array($saved),'upgrade baseline available');
$token=(string)upgradeSetting($modx,$settingClass,'modxmcp.api_token')->get('value');
upgradeCheck(hash_equals($saved['token_sha256'],hash('sha256',$token)),
    'token retained byte-for-byte');
upgradeCheck((string)upgradeSetting($modx,$settingClass,'modxmcp.max_read_bytes')
    ->get('value')===$saved['max_read_bytes'],'administrator read limit preserved');
upgradeCheck((string)upgradeSetting($modx,$settingClass,'modxmcp.allowed_processors')
    ->get('value')==='', 'new 1.2.8 processor allowlist installed disabled by default');
upgradeCheck((string)upgradeSetting($modx,$settingClass,'modxmcp.enabled')
    ->get('value')===$saved['enabled'],'enabled flag preserved');
echo 'UPGRADE_128_TOKEN_SETTINGS_PRESERVED_OK'.PHP_EOL;
$state=upgradeRpc($api,'get_site_state');
$features=upgradeRpc($api,'get_capabilities');
upgradeCheck(is_array($state)&&!empty($state['cas_required'])
    && (int)$state['precondition_version']===2
    && is_array($features),'read-only MCP API and CAS contract survived upgrade');
upgradeCheck(is_array(upgradeRpc($api,'list_resources',array('limit'=>2))),
    'legacy read-only resource enumeration preserved');
echo 'UPGRADE_128_LEGACY_READS_OK'.PHP_EOL;
$before=(int)$state['site_revision'];$name='MCP_Upgrade_'.bin2hex(random_bytes(6));
$oldClientDenied=false;
try{upgradeRpc($api,'create_element',array(
    'type'=>'chunk','name'=>$name,'content'=>'DISALLOWED_NO_CAS'
));}catch(ModxMCPClientException $e){
    $oldClientDenied=strpos($e->getMessage(),'CAS_REQUIRED')!==false;
}
upgradeCheck($oldClientDenied,'legacy mutation without CAS must be explicitly refused');
upgradeCheck((int)upgradeRpc($api,'get_site_state')['site_revision']===$before,
    'denied old client must not consume site revision');
echo 'UPGRADE_128_UNSAFE_LEGACY_WRITE_REFUSED_OK'.PHP_EOL;
$id=0;
try {
    $created=upgradeRpc($api,'create_element',array(
        'type'=>'chunk','name'=>$name,'content'=>'UPGRADED_SAFE_WRITE',
        '_runtime_preconditions'=>upgradeGuard($api)
    ));
    $id=(int)($created['id']??0);
    upgradeCheck($id>0,'new CAS client created its isolated chunk');
    $read=upgradeRpc($api,'get_element',array('type'=>'chunk','id'=>$id));
    upgradeCheck(is_array($read)
        && (string)($read['snippet']??($read['content']??''))
        ==='UPGRADED_SAFE_WRITE','independent readback after upgrade');
    $updated=upgradeRpc($api,'update_element',array(
        'type'=>'chunk','id'=>$id,'content'=>'UPGRADED_SAFE_UPDATE',
        '_runtime_preconditions'=>upgradeGuard($api)
    ));
    $read=upgradeRpc($api,'get_element',array('type'=>'chunk','id'=>$id));
    upgradeCheck(is_array($read)
        && (string)($read['snippet']??($read['content']??''))
        ==='UPGRADED_SAFE_UPDATE','independent CAS update readback');
    echo 'UPGRADE_128_NEW_CAS_MUTATION_READBACK_OK'.PHP_EOL;
} finally {
    if($id>0) {
        upgradeRpc($api,'delete_element',array(
            'type'=>'chunk','id'=>$id,'_runtime_preconditions'=>upgradeGuard($api)
        ));
    }
}
echo 'UPGRADE_127_TO_128_TRUE_TRANSPORT_OK platform='
    .($is2?'modx2':'modx3').PHP_EOL;
