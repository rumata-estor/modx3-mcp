<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Приёмочная проверка реальных разрешённых операций на одноразовом MODX.
 * EN: Functional acceptance of real permitted actions on disposable MODX.
 */
/**
 * RU: Запускается ТОЛЬКО в GitHub CI с временной установкой MODX 2/3.
 * Все изменения ресурсов и элементов проходят через штатный MODX MCP.
 * Исключения считаются ошибками; в finally удаляем тестовые записи через MCP.
 *
 * EN: Runs ONLY on disposable GitHub CI MODX 2/3.
 * All resource/element mutations use the guarded MODX MCP dispatcher.
 * Unexpected exceptions fail the run; fixtures are cleaned up through MCP.
 */
if (PHP_SAPI !== 'cli' || getenv('MODXMCP_DISPOSABLE_CI') !== '1'
    || strpos((string)getenv('MODX_CONFIG_CORE'), '/home/runner/') !== 0
    || !is_file((string)getenv('MODX_CONFIG_CORE'))) {
    fwrite(STDERR, "Refusing functional acceptance outside disposable GitHub CI.\n");
    exit(3);
}
require_once getenv('MODX_CONFIG_CORE');
$is2 = !is_file(MODX_CORE_PATH . 'src/Revolution/modX.php');
if ($is2) {
    require_once MODX_CORE_PATH . 'model/modx/modx.class.php';
    $modx = new modX();
} else {
    require_once MODX_CORE_PATH . 'vendor/autoload.php';
    $modx = \MODX\Revolution\modX::getInstance();
}
$modx->initialize('mgr');
require_once MODX_CORE_PATH . 'components/modxmcp/model/modxmcp.class.php';
require_once __DIR__ . '/test.cas-mcp-helper.php';
$api = new modxMCP($modx);
$checks = 0;
$resourceId = 0;
$chunkId = 0;
$userId = 0;
$groupId = 0;
$settingKey = '';
$fixture = bin2hex(random_bytes(6));

function acceptanceRpc($action, array $data = array())
{
    global $api;
    return disposableCasRpc($api, $action, $data);
}
function acceptanceCheck($condition, $message)
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException('FUNCTIONAL_ACCEPTANCE_FAILED: ' . $message);
    }
    $checks++;
}
function acceptanceSnapshot($type, $id)
{
    $state = acceptanceRpc('get_site_state', array(
        'read_set'=>array(array('role'=>'target','type'=>$type,'id'=>$id))
    ));
    acceptanceCheck(isset($state['read_set'][0]['fingerprint'])
        && preg_match('/\A[a-f0-9]{64}\z/D',(string)$state['read_set'][0]['fingerprint']),
        'server-issued target fingerprint');
    return array(
        'observed_site_revision'=>(int)$state['site_revision'],
        'strict_site_revision'=>true,
        'read_set'=>$state['read_set'],
    );
}
function acceptanceClean($action, array $data)
{
    try { acceptanceRpc($action,$data); }
    catch (\Throwable $e) {
        fwrite(STDERR,'FUNCTIONAL_ACCEPTANCE_CLEANUP_FAILED '.$action
            .': '.$e->getMessage().PHP_EOL);
        throw $e;
    }
}
try {
    $state = acceptanceRpc('get_site_state');
    $capabilities = acceptanceRpc('get_capabilities');
    acceptanceCheck(!empty($state['cas_required'])
        && (int)$state['precondition_version']===2,
        'server advertises mandatory CAS v2');
    acceptanceCheck(is_array($capabilities) && !empty($capabilities['features']['cas_required']),
        'capabilities advertise mandatory CAS');
    acceptanceCheck(is_array(acceptanceRpc('list_resources',array('limit'=>3))),
        'resource listing works');
    acceptanceCheck(is_array(acceptanceRpc('list_system_settings',array('namespace'=>'modxmcp'))),
        'system setting listing works');
    // RU: Этот сценарий запускается ПОСЛЕ защищённых тестов, которые меняют
    // RU: настройки в disposable БД. Здесь проверяем существование и
    // RU: корректность значений, а не первоначальные значения установщика.
    // EN: This scenario runs AFTER security tests that modify disposable
    // EN: settings. Validate the schema, not installer defaults here.
    $installedMode=acceptanceRpc('get_system_setting',array(
        'key'=>'modxmcp.processor_access_mode'));
    $installedAllowed=acceptanceRpc('get_system_setting',array(
        'key'=>'modxmcp.allowed_processors'));
    $installedPrivileged=acceptanceRpc('get_system_setting',array(
        'key'=>'modxmcp.privileged_processors'));
    acceptanceCheck(is_array($installedMode)
        && in_array((string)$installedMode['value'],
            array('disabled','allowlist','privileged'),true)
        && is_array($installedAllowed)
        && is_string($installedAllowed['value'])
        && is_array($installedPrivileged)
        && is_string($installedPrivileged['value']),
        'processor policy settings remain available after security tests');

    // RU: Выполняем только скрытые неопубликованные тестовые записи.
    // EN: Create hidden, unpublished fixtures; never modify existing content.
    $created=acceptanceRpc('create_element',array(
        'type'=>'resource','name'=>'MCP acceptance '.$fixture,
        'alias'=>'mcp-acceptance-'.$fixture, 'content'=>'BEFORE_'.$fixture,
        'published'=>0,'hidemenu'=>1,'template'=>0,'parent'=>0,
    ));
    acceptanceCheck(is_array($created) && isset($created['id']) && (int)$created['id']>0,
        'CAS guarded resource creation');
    $resourceId=(int)$created['id'];
    $read=acceptanceRpc('get_element',array('type'=>'resource','id'=>$resourceId));
    acceptanceCheck(is_array($read) && (string)$read['content']==='BEFORE_'.$fixture,
        'readback after resource creation');
    $beforeUpdate=acceptanceSnapshot('resource',$resourceId);
    acceptanceRpc('update_element',array(
        'type'=>'resource','id'=>$resourceId,'content'=>'AFTER_'.$fixture,
        '_runtime_preconditions'=>$beforeUpdate,
    ));
    $read=acceptanceRpc('get_element',array('type'=>'resource','id'=>$resourceId));
    acceptanceCheck((string)$read['content']==='AFTER_'.$fixture,
        'resource update persisted');
    $staleDenied=false;
    try {
        acceptanceRpc('update_element',array(
            'type'=>'resource','id'=>$resourceId,'content'=>'MUST_NOT_APPEAR',
            '_runtime_preconditions'=>$beforeUpdate,
        ));
    } catch (ModxMCPClientException $e) {
        $staleDenied=strpos($e->getMessage(),'STALE_STATE')!==false;
    }
    acceptanceCheck($staleDenied,'stale revision rejected after positive update');
    $read=acceptanceRpc('get_element',array('type'=>'resource','id'=>$resourceId));
    acceptanceCheck((string)$read['content']==='AFTER_'.$fixture,
        'stale mutation did not change resource');

    $chunk=acceptanceRpc('create_element',array(
        'type'=>'chunk','name'=>'MCP_Acceptance_'.$fixture,'content'=>'CHUNK_'.$fixture,
    ));
    acceptanceCheck(is_array($chunk) && isset($chunk['id']) && (int)$chunk['id']>0,
        'chunk creation');
    $chunkId=(int)$chunk['id'];
    $chunkRead=acceptanceRpc('get_element',array('type'=>'chunk','id'=>$chunkId));
    acceptanceCheck(is_array($chunkRead) && (string)$chunkRead['content']==='CHUNK_'.$fixture,
        'chunk readback');

    // RU: Группа ACL по умолчанию выключена. Включаем только внутри
    // RU: disposable PHP-процесса и проверяем реальные права и операции.
    // EN: Enable the ACL capability solely in disposable PHP process memory.
    $modx->setOption('modxmcp.disabled_groups','');
    $group=acceptanceRpc('create_user_group',array(
        'name'=>'MCP_AcceptanceGroup_'.$fixture,
    ));
    acceptanceCheck(is_array($group) && isset($group['id']) && (int)$group['id']>0,
        'user group creation via guarded MCP');
    $groupId=(int)$group['id'];
    $groupRead=acceptanceRpc('get_user_group',array('id'=>$groupId));
    acceptanceCheck(is_array($groupRead) && (int)$groupRead['id']===$groupId,
        'user group readback');
    try {
        $user=acceptanceRpc('create_user',array(
            'username'=>'mcp_acceptance_'.$fixture,
            'fullname'=>'MODX MCP disposable acceptance',
            'email'=>'mcp-acceptance-'.$fixture.'@example.invalid',
            'password'=>'CI-Temp-'.bin2hex(random_bytes(12)).'9Q!',
            'passwordnotifymethod'=>'s',
            'active'=>0,
        ));
    } catch (ModxMCPClientException $e) {
        // RU: При неопределённом результате ищем созданного пользователя
        // RU: только через MCP, сохраняем ID для безопасной очистки.
        // EN: On uncertain outcome, locate the created user through MCP
        // EN: and retain the ID for safe fixture cleanup; do not retry.
        if (strpos($e->getMessage(),'MUTATION_OUTCOME_UNCERTAIN')!==0)throw $e;
        $list=acceptanceRpc('list_users',array('query'=>'mcp_acceptance_'.$fixture));
        if (is_array($list) && isset($list['results']) && is_array($list['results'])) {
            foreach ($list['results'] as $row) {
                if (is_array($row) && isset($row['username'], $row['id'])
                    && $row['username']==='mcp_acceptance_'.$fixture) {
                    $userId=(int)$row['id'];
                    break;
                }
            }
        }
        throw new RuntimeException(
            'FUNCTIONAL_ACCEPTANCE_FAILED: create_user reported an uncertain outcome; '
            . 'MCP readback indicates '.($userId>0?'user exists':'user not found')
            . '. Operation must not be repeated blindly.'
        );
    }
    acceptanceCheck(is_array($user) && isset($user['id']) && (int)$user['id']>0,
        'inactive disposable user creation via guarded MCP');
    $userId=(int)$user['id'];
    $userRead=acceptanceRpc('get_user',array('id'=>$userId));
    acceptanceCheck(is_array($userRead) && (string)$userRead['username']==='mcp_acceptance_'.$fixture,
        'inactive user readback');
    // RU: В штатном чтении профиля не должны появляться хеши и соли.
    // EN: Normal user reads must never include password hashes or salts.
    foreach (array('password','cachepwd','salt','remote_key','sudo') as $secretField) {
        acceptanceCheck(!array_key_exists($secretField,$userRead),
            'sensitive user field hidden: '.$secretField);
    }

    $settingKey='mcp_acceptance_'.$fixture;
    acceptanceRpc('create_system_setting',array('key'=>$settingKey,'value'=>'ONE'));
    $setting=acceptanceRpc('get_system_setting',array('key'=>$settingKey));
    acceptanceCheck(is_array($setting) && (string)$setting['value']==='ONE',
        'system setting create/readback');
    acceptanceRpc('update_system_setting',array('key'=>$settingKey,'value'=>'TWO'));
    $setting=acceptanceRpc('get_system_setting',array('key'=>$settingKey));
    acceptanceCheck((string)$setting['value']==='TWO','system setting update/readback');

    // RU: Главная галочка активируется только в памяти disposable процесса.
    // EN: Enable master switch only in this disposable process; never persist it.
    $modx->setOption('modxmcp.allow_run_processor',true);
    $modx->setOption('modxmcp.processor_access_mode','allowlist');
    $modx->setOption('modxmcp.allowed_processors','resource/getlist');
    $modx->setOption('modxmcp.privileged_processors','');
    $processor=acceptanceRpc('run_processor',array(
        'processor'=>'resource/getlist','properties'=>array('limit'=>1),
    ));
    acceptanceCheck(is_array($processor),'allowlisted resource/getlist executes');

    // RU: Попытка запустить чувствительный процессор через обычный список
    // RU: должна завершиться отказом до вызова процессора.
    // EN: An ordinary allowlist must reject security/user/create before dispatch.
    $modx->setOption('modxmcp.allowed_processors',
        'resource/getlist,security/user/create');
    $denied=false;
    try {
        acceptanceRpc('run_processor',array(
            'processor'=>'security/user/create',
            'properties'=>array('username'=>'DENIED_'.$fixture),
        ));
    } catch (\Throwable $e) {
        $denied=strpos($e->getMessage(),'AUTH_DENIED')!==false;
    }
    acceptanceCheck($denied,'sensitive processor denied in ordinary mode');

    // RU: После испытаний возвращаем состояние переключателей текущего процесса.
    // EN: Restore in-process toggles before cleanup.
    $modx->setOption('modxmcp.allow_run_processor',false);
    $modx->setOption('modxmcp.processor_access_mode','disabled');

    acceptanceClean('delete_system_setting',array('key'=>$settingKey));
    $settingKey='';
    acceptanceClean('delete_user',array('id'=>$userId));
    $userId=0;
    acceptanceClean('delete_user_group',array('id'=>$groupId));
    $groupId=0;
    acceptanceClean('delete_element',array('type'=>'chunk','id'=>$chunkId));
    $chunkId=0;
    acceptanceClean('delete_element',array('type'=>'resource','id'=>$resourceId,'dry_run'=>false));
    $read=acceptanceRpc('get_element',array('type'=>'resource','id'=>$resourceId));
    acceptanceCheck(!empty($read['deleted']),'resource soft-delete persisted');
    $resourceId=0;
    echo 'MODXMCP_DISPOSABLE_FUNCTIONAL_ACCEPTANCE_OK platform='
        .($is2?'modx2':'modx3').' checks='.$checks.PHP_EOL;
} finally {
    // RU: Не скрываем ошибки очистки: тестовая установка одноразовая.
    // EN: Do not suppress cleanup errors; the entire installation is disposable.
    if ($settingKey!=='') acceptanceClean('delete_system_setting',array('key'=>$settingKey));
    if ($userId>0) acceptanceClean('delete_user',array('id'=>$userId));
    if ($groupId>0) acceptanceClean('delete_user_group',array('id'=>$groupId));
    if ($chunkId>0) acceptanceClean('delete_element',array('type'=>'chunk','id'=>$chunkId));
    if ($resourceId>0) acceptanceClean('delete_element',array(
        'type'=>'resource','id'=>$resourceId,'dry_run'=>false));
}
