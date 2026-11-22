<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Проверка «protected-processor-denial»; тестовый код не запускается на production.
 * EN: «protected-processor-denial» regression test; not executed in production.
 */
/** Offline authorization policy tests. No MODX, DB, HTTP or real processors. */
require_once __DIR__.'/../core/components/modxmcp/src/Security/ProcessorAccessPolicy.php';
use ModxMcp\Security\ProcessorAccessPolicy as Policy;
$checks=0;
function accepts($processor,$mode,$allowed,$privileged,$extra=array()) {
    global $checks;
    $input=array_merge(array('processor'=>$processor),$extra);
    if (Policy::authorize($input,$mode,$allowed,$privileged)!==true) {
        throw new \RuntimeException('Expected processor permit.');
    }
    $checks++;
}
function rejects($processor,$mode,$allowed,$privileged,$extra=array()) {
    global $checks;
    try {
        Policy::authorize(array_merge(array('processor'=>$processor),$extra),$mode,$allowed,$privileged);
    } catch (\RuntimeException $e) {
        if (strpos($e->getMessage(),'AUTH_DENIED:')!==0)throw $e;
        $checks++;return;
    }
    throw new \RuntimeException('Processor policy unexpectedly allowed '.$processor);
}
$allowed='resource/getlist,system/clearcache';
$privileged='security/user/create,workspace/package/install';
rejects('resource/getlist','disabled',$allowed,$privileged);
rejects('resource/getlist','false',$allowed,$privileged);
rejects('resource/getlist','',$allowed,$privileged);
rejects('resource/getlist','allowlist','','');
accepts('resource/getlist','allowlist',$allowed,$privileged);
accepts('system/clearcache','allowlist',$allowed,$privileged);
rejects('security/user/create','allowlist',$allowed,$privileged);
// RU: Даже явное разрешение security/* в обычном списке не даёт sudo.
// EN: An explicit security/* entry in the ordinary list never grants sudo.
rejects('security/user/create','allowlist','security/user/create',$privileged);
rejects('security/user/create','privileged','security/user/create','');
rejects('workspace/package/install','allowlist','workspace/package/install',$privileged);
// RU: Обычный белый список не должен открывать реальные пути MODX packages.
// EN: Ordinary allowlisting must not grant the actual MODX packages routes.
rejects('workspace/packages/install','allowlist','workspace/packages/install',$privileged);
rejects('workspace/packages/uninstall','allowlist','workspace/packages/uninstall',$privileged);
rejects('workspace/packages/rest/download','allowlist','workspace/packages/rest/download',$privileged);
accepts('workspace/packages/install','privileged',$allowed,'workspace/packages/install');
rejects('workspace/namespace/create','allowlist','workspace/namespace/create',$privileged);
accepts('security/user/create','privileged',$allowed,$privileged);
accepts('workspace/package/install','privileged',$allowed,$privileged);
// RU: Даже явно разрешённый privileged run_processor не обходит ACL locks.
// EN: Exact privileged allowlists cannot bypass dedicated group CAS locks.
foreach(array(
    'security/group/update',
    'security/group/remove',
    'security/group/user/create',
    'security/group/user/update',
    'security/group/user/remove',
    'security/user/group/create',
    'security/user/group/remove'
) as $aclProcessor) {
    rejects($aclProcessor,'privileged','',$aclProcessor);
    rejects($aclProcessor,'allowlist',$aclProcessor,'');
}
// Preserve legitimate signed user creation without membership side effects.
accepts('security/user/create','privileged',$allowed,$privileged,
    array('properties'=>array('username'=>'disposable')));
foreach(array('groups','primary_group','primarygroup','usergroup','user_group','role') as $key) {
    rejects('security/user/create','privileged',$allowed,$privileged,
        array('properties'=>array($key=>array())));
    rejects('security/user/update','privileged',$allowed,'security/user/update',
        array('properties'=>array($key=>1)));
}

accepts('resource/getlist','privileged',$allowed,$privileged);
rejects('security/user/delete','privileged',$allowed,$privileged);
rejects('system/settings/update','privileged','system/settings/update',$privileged);
rejects('system/setting/update','privileged','system/setting/update',$privileged);
// RU: Все обходные пути изменения защищённых настроек запрещены даже
// RU: при наличии привилегированной подписи и явного имени процессора.
// EN: Indirect security-setting mutation routes remain forbidden, even
// EN: with a signed privileged permit and an exact processor allowlist.
foreach (array(
    'context/setting/create','context/setting/update','context/setting/remove',
    'context/update','security/user/setting/update',
    'security/group/setting/create','security/group/setting/remove'
) as $settingProcessor) {
    rejects($settingProcessor,'privileged','',$settingProcessor);
    rejects($settingProcessor,'allowlist',$settingProcessor,'');
}
foreach (array('false',17,'',true,null) as $badProperties) {
    rejects('resource/getlist','allowlist',$allowed,$privileged,
        array('properties'=>$badProperties));
    rejects('resource/getlist','privileged',$allowed,$privileged,
        array('properties'=>$badProperties));
}
rejects('../system/clearcache','allowlist',$allowed,$privileged);
rejects('SYSTEM/clearcache','allowlist',$allowed,$privileged);
rejects('resource/getlist','allowlist',$allowed,$privileged,array('processors_path'=>'/tmp/unsafe'));
rejects('resource/getlist','privileged',$allowed,$privileged,array('processors_path'=>''));
rejects('resource/getlist','allowlist','resource/getlist, resource/delete',$privileged);
rejects('resource/getlist','allowlist','resource/getlist,resource/getlist',$privileged);
rejects('resource/getlist','allowlist','resource/*',$privileged);
rejects('resource/getlist','allowlist',$allowed,'security/user/create,security/user/create');
rejects('resource/getlist','allowlist',$allowed,'security/user/create,');
rejects('resource/getlist','allowlist',$allowed,'../unsafe');
rejects('resource/getlist','allowlist',$allowed,str_repeat('x',8200));
rejects('resource/getlist','privileged',$allowed,$privileged,array('processor'=>'resource/getlist/../delete'));
echo 'PROCESSOR_ACCESS_POLICY_OK checks='.$checks.PHP_EOL;
