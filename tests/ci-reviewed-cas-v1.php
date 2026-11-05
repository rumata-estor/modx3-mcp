<?php
/** Real MODX 2/3 DB test. Only disposable GitHub CI installations can run. */
$root=getenv('RUNNER_TEMP');
$cfg=getenv('MODX_CONFIG_CORE');
$platform=getenv('BUILD_PLATFORM');
if (getenv('GITHUB_ACTIONS')!=='true' ||
    !in_array($platform,array('modx2','modx3'),true) ||
    !is_string($root) || $root==='' || !is_string($cfg) ||
    realpath($cfg)!==realpath($root.'/modx-site/config.core.php')) {
    throw new RuntimeException('Disposable CI only: refusing site mutation');
}
require $cfg;
if ($platform==='modx3') {
    require MODX_CORE_PATH.'vendor/autoload.php';
    $modx=\MODX\Revolution\modX::getInstance();
    $major=3; $pfx='MODX\\Revolution\\';
    $site='modx3-alex-palochkin';
} else {
    require MODX_CORE_PATH.'model/modx/modx.class.php';
    $modx=new modX(); $major=2; $pfx='';
    $site='modx2-alex-palochkin';
}
$modx->initialize('mgr');
require_once __DIR__.'/../core/components/modxmcp/endpoint/reviewed-cas-processor-v1.php';
$classes=array(
 'setting'=>$pfx.'modSystemSetting',
 'resource'=>$pfx.'modResource',
 'template'=>$pfx.'modTemplate',
 'tv'=>$pfx.'modTemplateVar',
 'link'=>$pfx.'modTemplateVarTemplate',
 'user'=>$pfx.'modUser',
);
$modx->user=$modx->getObject($classes['user'],array('username'=>'testadmin'));
if (!$modx->user) { throw new RuntimeException('Test admin missing'); }
$dir=$root.'/reviewed-cas-v1-'.bin2hex(random_bytes(8));
if (!mkdir($dir,0700)) { throw new RuntimeException('Cannot create CI nonce ledger'); }
$ledger=new ModxMcpPermitNonceLedger($dir);
$settings=array();
$keys=array('modxmcp.external_authorization_required',
            'modxmcp.auto_static','modxmcp.site_revision');
foreach ($keys as $settingKey) {
    $row=$modx->getObject($classes['setting'],array('key'=>$settingKey));
    $settings[$settingKey]=$row ? array('exists'=>true,'value'=>(string)$row->get('value'))
                                : array('exists'=>false);
}
$set=function($key,$value) use ($modx,$classes) {
    $row=$modx->getObject($classes['setting'],array('key'=>$key));
    if (!$row) {
        $row=$modx->newObject($classes['setting']);
        $row->fromArray(array('key'=>$key,'value'=>$value,'xtype'=>'textfield',
            'namespace'=>'modxmcp','area'=>'modxmcp:runtime'),'',true,true);
    } else { $row->set('value',$value); }
    if (!$row->save()) { throw new RuntimeException('CI setting save failed'); }
};
$trusted=array('site_id'=>$site,'contour'=>'test','audience'=>'ci-reviewed-cas');
$key=str_repeat('R',32);
$nonce=0;
$make=function($action,$kind,array $data) use ($trusted,$key,&$nonce) {
    $raw=json_encode(array('action'=>$action,'type'=>$kind,'data'=>$data),
        JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|
        JSON_UNESCAPED_LINE_TERMINATORS);
    $claims=array(
        'version'=>'1','audience'=>$trusted['audience'],
        'site_id'=>$trusted['site_id'],'contour'=>'test',
        'task_id'=>'TASK-20261105-211500-abcd',
        'plan_digest'=>str_repeat('a',64),
        'operation_id'=>'op-'.(++$nonce),'action'=>$action,
        'target_digest'=>str_repeat('b',64),
        'parameters_digest'=>str_repeat('c',64),
        'request_digest'=>hash('sha256',$raw),
        'nonce'=>bin2hex(random_bytes(16)),
        'expires_at'=>(string)(time()+40)
    );
    return array($raw,array('claims'=>$claims,
        'mac'=>ModxMcpPermitWire::sign($claims,$key)));
};
$run=function($entry) use ($modx,$trusted,$key,$ledger) {
    return ModxMcpReviewedCasV1::executeUnconnected(
        $modx,$entry[1],$entry[0],$trusted,$key,$ledger);
};
$deny=function($fn,$label) {
    try { $fn(); } catch (Exception $e) { return; }
    throw new RuntimeException('Unsafe CAS allowed '.$label);
};
$created=array();
$unique='CAS_TEST_'.bin2hex(random_bytes(5));
try {
    $set('modxmcp.external_authorization_required','1');
    $set('modxmcp.auto_static','0');
    $set('modxmcp.site_revision','0');
    $modx->getCacheManager()->refresh();
    if (!function_exists('fsync')) {
        $fixture=$make('create_element','resource',array(
            'pagetitle'=>$unique,'content'=>'Protected','published'=>0,
            '_cas_expected'=>array('absent'=>true)));
        $deny(function() use ($run,$fixture) { $run($fixture); },
              'PHP runtime without durable nonce');
        if ($modx->getObject($classes['resource'],array('pagetitle'=>$unique))) {
            throw new RuntimeException('PHP 7.4 without fsync performed a write');
        }
        echo "REVIEWED_CAS_PHP74_DENY_ONLY_OK $platform\n";
    } else {
        $newPage=$make('create_element','resource',array(
            'pagetitle'=>$unique,'content'=>'BEFORE','published'=>0,
            'template'=>0,'parent'=>0,'alias'=>strtolower($unique),
            '_cas_expected'=>array('absent'=>true)));
        $result=$run($newPage);
        $page=$modx->getObject($classes['resource'],array('pagetitle'=>$unique));
        if (!$page || (int)$page->get('id')!==(int)$result['id']) {
            throw new RuntimeException('Page create not persisted');
        }
        $created['resource']=(int)$page->get('id');
        $deny(function() use ($run,$newPage) { $run($newPage); },'nonce replay');
        $changed=$make('update_element','resource',array(
            'id'=>$created['resource'],'content'=>'AFTER',
            '_cas_expected'=>array('fields'=>array('content'=>'WRONG'))));
        $deny(function() use ($run,$changed) { $run($changed); },
              'stale resource content');
        $page=$modx->getObject($classes['resource'],$created['resource']);
        if ((string)$page->get('content')!=='BEFORE') {
            throw new RuntimeException('Stale CAS changed page');
        }
        $changed=$make('update_element','resource',array(
            'id'=>$created['resource'],'content'=>'AFTER',
            '_cas_expected'=>array('fields'=>array('content'=>'BEFORE'))));
        $run($changed);
        $page=$modx->getObject($classes['resource'],$created['resource']);
        if ((string)$page->get('content')!=='AFTER') {
            throw new RuntimeException('Signed update did not persist');
        }
        $templateName=$unique.'_template';
        $templateReq=$make('create_element','template',array(
            'templatename'=>$templateName,'content'=>'[[*content]]',
            '_cas_expected'=>array('absent'=>true)));
        $run($templateReq);
        $template=$modx->getObject($classes['template'],
                                   array('templatename'=>$templateName));
        if (!$template) { throw new RuntimeException('Template create missing'); }
        $created['template']=(int)$template->get('id');
        $tvName=$unique.'_tv';
        $tvReq=$make('create_element','tv',array(
            'name'=>$tvName,'caption'=>'Тестовое поле','type'=>'text',
            'templates'=>array($created['template']),
            '_cas_expected'=>array('absent'=>true)));
        $run($tvReq);
        $tv=$modx->getObject($classes['tv'],array('name'=>$tvName));
        if (!$tv) { throw new RuntimeException('TV create missing'); }
        $created['tv']=(int)$tv->get('id');
        $relation=$modx->getObject($classes['link'],array(
            'tmplvarid'=>$created['tv'],'templateid'=>$created['template']));
        if (!$relation) { throw new RuntimeException('TV not assigned to template'); }
        $attach=$make('update_element','resource',array(
            'id'=>$created['resource'],'template'=>$created['template'],
            '_cas_expected'=>array('fields'=>array('template'=>0))));
        $run($attach);
        $values=$make('update_resource_tvs','resource',array(
            'resource_id'=>$created['resource'],
            'tvs'=>array($tvName=>'Заполнено'),
            '_cas_expected'=>array('tvs'=>array($tvName=>''))));
        $run($values);
        $page=$modx->getObject($classes['resource'],$created['resource']);
        if ((string)$page->getTVValue($tvName)!=='Заполнено') {
            throw new RuntimeException('Protected TV value not persisted');
        }
        echo "REVIEWED_CAS_REAL_MODX_PAGE_TEMPLATE_TV_OK $platform\n";
    }
} finally {
    if (isset($created['resource'])) {
        $modx->removeObject($classes['resource'],array('id'=>$created['resource']));
    }
    if (isset($created['tv'])) {
        $modx->removeObject($classes['tv'],array('id'=>$created['tv']));
    }
    if (isset($created['template'])) {
        $modx->removeObject($classes['template'],array('id'=>$created['template']));
    }
    foreach ($settings as $key=>$prior) {
        if ($prior['exists']) {
            $set($key,$prior['value']);
        } else {
            $modx->removeObject($classes['setting'],array('key'=>$key));
        }
    }
    $modx->getCacheManager()->refresh();
    foreach (glob($dir.'/*.used') as $file) { unlink($file); }
    rmdir($dir);
}
