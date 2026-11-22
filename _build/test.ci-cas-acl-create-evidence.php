<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Индивидуальные create ACL: CAS, чтение, повтор, отсутствие дубликатов.
 * EN: Individual ACL create tests: CAS replay, readback and no duplicate.
 */
if (PHP_SAPI !== 'cli' || getenv('MODXMCP_DISPOSABLE_CI') !== '1'
    || strpos((string)getenv('MODX_CONFIG_CORE'), '/home/runner/') !== 0
    || !is_file((string)getenv('MODX_CONFIG_CORE'))) {
    fwrite(STDERR,'Refusing ACL evidence tests outside disposable CI.'.PHP_EOL);
    exit(3);
}
require getenv('MODX_CONFIG_CORE');
$is2=!is_file(MODX_CORE_PATH.'src/Revolution/modX.php');
if ($is2) {
    require MODX_CORE_PATH.'model/modx/modx.class.php';
    $modx=new modX();
} else {
    require MODX_CORE_PATH.'vendor/autoload.php';
    $modx=\MODX\Revolution\modX::getInstance();
}
$modx->initialize('mgr');
$modx->setOption('modxmcp.disabled_groups','');
require MODX_CORE_PATH.'components/modxmcp/model/modxmcp.class.php';
require __DIR__.'/test.cas-mcp-helper.php';
$api=new modxMCP($modx);
$checks=0;
function casCreateRpc($action,array $data=array()) {
    global $api;
    return disposableCasRpc($api,$action,$data);
}
function casCreateCheck($test,$label) {
    global $checks;
    if(!$test)throw new RuntimeException('CAS_CREATE_EVIDENCE_FAILED '.$label);
    $checks++;
}
$fixture=bin2hex(random_bytes(6));
$cases=array(
    array('create_user_group','get_user_group','delete_user_group',
        'list_user_groups','MCP_EvidenceGroup_'.$fixture,array()),
    array('create_role','get_role','delete_role',
        'list_roles','MCP_EvidenceRole_'.$fixture,array('authority'=>987)),
    array('create_resource_group','get_resource_group','delete_resource_group',
        'list_resource_groups','MCP_EvidenceResGroup_'.$fixture,array()),
    array('create_user','get_user','delete_user',
        'list_users','mcp_evidence_'.$fixture,array(
            'fullname'=>'Disposable CAS evidence',
            'email'=>'mcp-evidence-'.$fixture.'@example.invalid',
            'password'=>'CI-'.bin2hex(random_bytes(16)).'9Q!',
            'passwordnotifymethod'=>'s','active'=>0)),
);
foreach($cases as $case) {
    list($create,$read,$delete,$list,$name,$extra)=$case;
    $id=0;
    $field=$create==='create_user'?'username':'name';
    try {
        $state=casCreateRpc('get_site_state');
        $guard=array('observed_site_revision'=>(int)$state['site_revision'],
            'strict_site_revision'=>true,'read_set'=>array());
        $data=array_merge(array($field=>$name),$extra);
        $data['_runtime_preconditions']=$guard;
        $created=casCreateRpc($create,$data);
        $id=(int)($created['id']??0);
        casCreateCheck($id>0,$create.' returned an id');
        $actual=casCreateRpc($read,array('id'=>$id));
        casCreateCheck(is_array($actual)
            && (int)($actual['id']??0)===$id
            && (string)($actual[$field]??'')===$name,
            $create.' independent readback');
        if ($create==='create_user') {
            casCreateCheck((int)($actual['active']??-1)===0,
                'created user remains inactive');
            foreach(array('password','salt','cachepwd','remote_key','sudo') as $secret) {
                casCreateCheck(!array_key_exists($secret,$actual),
                    'create_user readback masks '.$secret);
            }
        }
        $revision=casCreateRpc('get_site_state');
        casCreateCheck((int)$revision['site_revision']
            ===(int)$state['site_revision']+1,
            $create.' increments exactly one CAS revision');
        $denied=false;
        try {casCreateRpc($create,$data);}
        catch(ModxMCPClientException $e) {
            $denied=strpos($e->getMessage(),'STALE_STATE')!==false;
        }
        casCreateCheck($denied,$create.' stale replay denied');
        $revision=casCreateRpc('get_site_state');
        casCreateCheck((int)$revision['site_revision']
            ===(int)$state['site_revision']+1,
            $create.' stale replay did not consume revision');
        $rows=casCreateRpc($list,array('query'=>$name,'limit'=>100));
        casCreateCheck(isset($rows['results']) && is_array($rows['results']),
            $create.' list result available');
        $matching=0;
        foreach($rows['results'] as $row) {
            if(is_array($row) && (string)($row[$field]??'')===$name)
                $matching++;
        }
        casCreateCheck($matching===1,$create.' stale replay created no duplicate');
        $readAfter=casCreateRpc($read,array('id'=>$id));
        casCreateCheck((int)($readAfter['id']??0)===$id,
            $create.' readback still exists after denied replay');
        echo 'CAS_CREATE_EVIDENCE_OK action='.$create.PHP_EOL;
    }finally {
        if($id>0)casCreateRpc($delete,array('id'=>$id));
    }
}
echo 'CAS_CREATE_EVIDENCE_MATRIX_OK checks='.$checks
    .' platform='.($is2?'modx2':'modx3').PHP_EOL;
