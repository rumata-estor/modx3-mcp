<?php
/** Explicitly isolated CI protected-mode HTTP refusal smoke. No real site credentials. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit(2); }
$config = getenv('MODX_CONFIG_CORE');
$platform = getenv('BUILD_PLATFORM');
$site = getenv('MODX_MCP_SMOKE_SITE_URL');
if (!is_string($config) || !is_file($config) || !in_array($platform,array('modx2','modx3'),true)
    || !is_string($site) || !preg_match('~^http://127[.]0[.]0[.]1:[0-9]+$~D',$site)) {
    throw new RuntimeException('Only local disposable MODX CI instance is eligible');
}
$_SERVER['DOCUMENT_ROOT'] = getenv('MODX_DOCUMENT_ROOT');
require_once $config;
if ($platform === 'modx3') {
    require_once MODX_CORE_PATH . 'vendor/autoload.php';
    $modx = \MODX\Revolution\modX::getInstance();
    $settingClass = 'MODX\\Revolution\\modSystemSetting';
} else {
    require_once MODX_CORE_PATH . 'model/modx/modx.class.php';
    $modx = new modX();
    $settingClass = 'modSystemSetting';
}
$modx->initialize('mgr');
$setting = $modx->getObject($settingClass,array('key'=>'modxmcp.external_authorization_required'));
if (!$setting) {
    $setting = $modx->newObject($settingClass);
    $setting->fromArray(array('key'=>'modxmcp.external_authorization_required',
       'value'=>'1','xtype'=>'combo-boolean','namespace'=>'modxmcp',
       'area'=>'modxmcp:security'),'',true,true);
} else {
    $setting->set('value','1');
}
if (!$setting->save()) { throw new RuntimeException('Cannot enable throwaway CI protection'); }
$modx->getCacheManager()->refresh();
$token = (string)$modx->getOption('modxmcp.api_token');
if (!preg_match('/\A[0-9a-f]{64}\z/D',$token)) { throw new RuntimeException('Missing synthetic CI token'); }
$endpoint=rtrim($site,'/').'/assets/components/modxmcp/api.php';
function guardedPost($url,$token,array $payload) {
    $raw=json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    $ch=curl_init($url);
    curl_setopt_array($ch,array(CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$raw,
        CURLOPT_HTTPHEADER=>array('Content-Type: application/json','X-MCP-Token: '.$token),
        CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>20));
    $response=curl_exec($ch);
    $status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
    $err=curl_error($ch);
    curl_close($ch);
    if ($response===false) { throw new RuntimeException('HTTP test transport: '.$err); }
    $decoded=json_decode($response,true);
    if (!is_array($decoded)) { throw new RuntimeException('Protected mode returned non-JSON'); }
    return array($status,$decoded);
}
list($status,$data)=guardedPost($endpoint,$token,array('action'=>'get_capabilities'));
if ($status<200 || $status>=300 || empty($data['success'])) {
    throw new RuntimeException('Protected mode incorrectly denied read-only capabilities');
}
echo "PROTECTED_READ_ALLOWED\n";
foreach (array(
    array('action'=>'update_element','type'=>'chunk',
          'data'=>array('id'=>999999,'content'=>'CI-MUST-NEVER-WRITE')),
    array('action'=>'create_element','type'=>'chunk',
          'data'=>array('name'=>'ci-protected-should-not-exist','content'=>'CI-MUST-NEVER-WRITE')),
    array('action'=>'unclassified_mutation','data'=>array()),
) as $probe) {
    list($status,$data)=guardedPost($endpoint,$token,$probe);
    // An unsigned request may be denied by shape checks (400) or by the
    // private permit adapter before any transaction (server error). Either
    // must fail closed; CI separately checks that no row is ever changed.
    if ($status<400 || !empty($data['success']) || !isset($data['error'])) {
        throw new RuntimeException('Protected mode failed closed: '.(string)$probe['action'].
                                   ', HTTP '.(string)$status.
                                   ', error '.json_encode(isset($data['error']) ? $data['error'] : null));
    }
    echo 'PROTECTED_DENY_OK ' . $probe['action'] . "\n";
}
echo "CI_PROTECTED_MODE_HTTP_PASS $platform\n";
