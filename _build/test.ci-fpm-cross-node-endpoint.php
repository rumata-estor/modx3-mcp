<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Только одноразовая CI: два настоящих PHP-FPM контейнера с одной БД.
 * EN: Disposable two-node PHP-FPM cross-container CAS/lock verification.
 * This is an isolated testing endpoint, NEVER included in release transport.
 */
if (getenv('MODXMCP_DISPOSABLE_CI') !== '1'
    || !is_file('/srv/shared/CI_MARKER')
    || !is_file('/srv/modx/config.core.php')
    || !in_array((string)($_SERVER['MODXMCP_NODE'] ?? ''),array('a','b'),true)
    || PHP_SAPI !== 'fpm-fcgi') {
    http_response_code(403);exit;
}
$node=(string)$_SERVER['MODXMCP_NODE'];
$input=json_decode((string)file_get_contents('php://input'),true);
if (!is_array($input) || !isset($input['op']) || !is_string($input['op'])) {
    http_response_code(400);exit;
}
require '/srv/modx/config.core.php';
$is2=!is_file(MODX_CORE_PATH.'src/Revolution/modX.php');
if($is2) {
    require_once MODX_CORE_PATH.'model/modx/modx.class.php';
    $modx=new modX();
} else {
    require_once MODX_CORE_PATH.'vendor/autoload.php';
    $modx=\MODX\Revolution\modX::getInstance();
}
$modx->initialize('mgr');
$cache='/tmp/mcp-fpm-cache-'.$node.'/';
if(!is_dir($cache)&&!mkdir($cache,0770,true)) {
    throw new RuntimeException('Could not create isolated cache');
}
$modx->setOption('cache_path',$cache);
$modx->setOption('modxmcp.disabled_groups','');
require_once MODX_CORE_PATH.'components/modxmcp/model/modxmcp.class.php';
$api=new modxMCP($modx);
$lock=\ModxMcp\Tools\StateSupport::class;
$context=(new \ModxMcp\Core\Runtime($modx,$api))->context();
$out=array('status'=>'unknown','hostname'=>gethostname(),'node'=>$node);
function fpmCasGuard($revision,$readSet=array()) {
    return array('observed_site_revision'=>(int)$revision,
        'strict_site_revision'=>true,'read_set'=>$readSet);
}
function fpmBarrier($node,$type,$timeout=20) {
    $prefix='/srv/shared/'.$type;
    file_put_contents($prefix.'-ready-'.$node,(string)getmypid());
    $end=microtime(true)+$timeout;
    while(!is_file($prefix.'-go') && microtime(true)<$end)usleep(10000);
    if(!is_file($prefix.'-go'))throw new RuntimeException('CI barrier timed out');
}
try {
    $op=$input['op'];
    if($op==='state') {
        $readSet=$input['read_set']??null;
        if($readSet!==null && !is_array($readSet))throw new RuntimeException('Bad read_set');
        $out['state']=$api->processRequest('get_site_state','',
            $readSet===null?array():array('read_set'=>$readSet));
    } elseif($op==='create') {
        if(!isset($input['revision'],$input['name']) || !is_int($input['revision'])
            || !is_string($input['name'])
            || !preg_match('/\AMCP_FPM_[A-Za-z0-9_]{8,80}\z/D',$input['name'])) {
            throw new RuntimeException('Invalid CI create request');
        }
        fpmBarrier($node,'create');
        $out['result']=$api->processRequest('create_element','',array(
            'type'=>'chunk','name'=>$input['name'].'_'.$node,
            'content'=>'fpm-node-'.$node,
            '_runtime_preconditions'=>fpmCasGuard($input['revision'])
        ));
    } elseif($op==='update') {
        if(!isset($input['revision'],$input['id'],$input['read_set'])
            || !is_int($input['revision'])||!is_int($input['id'])
            || $input['id']<1||!is_array($input['read_set'])) {
            throw new RuntimeException('Invalid CI update request');
        }
        fpmBarrier($node,'update');
        $out['result']=$api->processRequest('update_element','',array(
            'type'=>'chunk','id'=>$input['id'],
            'content'=>'fpm-changed-'.$node,
            '_runtime_preconditions'=>fpmCasGuard(
                $input['revision'],$input['read_set'])
        ));
    } elseif($op==='read') {
        $id=$input['id']??null;
        if(!is_int($id)||$id<1)throw new RuntimeException('Bad id');
        $out['result']=$api->processRequest('get_element','',array(
            'type'=>'chunk','id'=>$id
        ));
    } elseif($op==='delete') {
        $id=$input['id']??null;
        if(!is_int($id)||$id<1)throw new RuntimeException('Bad id');
        $state=$api->processRequest('get_site_state','',array());
        $out['result']=$api->processRequest('delete_element','',array(
            'type'=>'chunk','id'=>$id,
            '_runtime_preconditions'=>fpmCasGuard($state['site_revision'])
        ));
    } elseif($op==='probe') {
        $lock::withMutationLock($context,function(){});
        $out['result']='lock-acquired';
    } elseif($op==='timeout') {
        $lock::withMutationLock($context,function(){});
        $out['result']='unexpected-lock-acquired';
    } elseif($op==='hold') {
        $lock::withMutationLock($context,function()use($lock,$context){
            $lock::withRevisionTransaction($context,function()use($lock,$context){
                $lock::incrementRevision($context);
                file_put_contents('/srv/shared/hold-ready','yes');
                sleep(35);
            });
        });
        $out['result']='unexpected-commit';
    } else {
        throw new RuntimeException('Unsupported CI operation');
    }
    $out['status']='success';
} catch(Throwable $e) {
    $message=$e->getMessage();
    $out['status']=strpos($message,'STALE_STATE')!==false?'stale'
        :(strpos($message,'CAS_LOCK_UNAVAILABLE:')===0?'lock-denied'
            :(strpos($message,'MUTATION_OUTCOME_UNCERTAIN:')===0
                ?'uncertain':'error'));
    $out['error_prefix']=substr($message,0,40);
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode($out,JSON_UNESCAPED_SLASHES);
