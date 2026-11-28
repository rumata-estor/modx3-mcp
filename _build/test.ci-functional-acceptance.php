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
$dateResourceId = 0;
$chunkId = 0;
$replaceChunkId = 0;
$userId = 0;
$groupId = 0;
$roleId = 0;
$secondRoleId = 0;
$memberAdded = false;
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
        'richtext'=>1,'searchable'=>0,'cacheable'=>0,
    ));
    acceptanceCheck(is_array($created) && isset($created['id']) && (int)$created['id']>0,
        'CAS guarded resource creation');
    $resourceId=(int)$created['id'];
    $read=acceptanceRpc('get_element',array('type'=>'resource','id'=>$resourceId));
    acceptanceCheck(is_array($read) && (string)$read['content']==='BEFORE_'.$fixture,
        'readback after resource creation');
    // RU: Сохраняем значения редактора/поиска/кеша для проверки partial update.
    // EN: Capture editing/search/cache flags before a content-only update.
    foreach (array('richtext'=>1,'searchable'=>0,'cacheable'=>0) as $field=>$expected) {
        acceptanceCheck(isset($read[$field]) && (int)$read[$field]===$expected,
            'initial resource flag '.$field);
    }
    // RU: Смена Manager-формата дат не должна менять CAS-отпечаток.
    // EN: Manager date/time presentation settings cannot affect CAS.
    // RU: Замечание @AgelxNash, PR #57 (проверка без SQL и записи в сайт).
    // EN: Reported by @AgelxNash, PR #57; verify through MCP only.
    $originalDateFormat=$modx->getOption('manager_date_format');
    $originalTimeFormat=$modx->getOption('manager_time_format');
    // RU: Отдельная временная опубликованная запись имеет ненулевую дату
    // RU: publishedon; основную проверочную страницу оставляем неопубликованной.
    // EN: Separate disposable published fixture has a real publishedon;
    // EN: the primary acceptance resource remains unpublished.
    $dateResource=acceptanceRpc('create_element',array(
        'type'=>'resource','name'=>'MCP date CAS '.$fixture,
        'alias'=>'mcp-date-cas-'.$fixture,'published'=>1,'hidemenu'=>1,
        'template'=>0,'parent'=>0,'content'=>'DATE_ONLY_'.$fixture,
    ));
    $dateResourceId=(int)($dateResource['id'] ?? 0);
    acceptanceCheck($dateResourceId>0,'published date fixture created');
    $dateRead=acceptanceRpc('get_element',array(
        'type'=>'resource','id'=>$dateResourceId
    ));
    acceptanceCheck(!empty($dateRead['publishedon']),
        'published date fixture provides nonempty raw publishedon');
    $dateSnapshot=acceptanceSnapshot('resource',$dateResourceId);
    try {
        $modx->setOption('manager_date_format','d/m/Y');
        $modx->setOption('manager_time_format','g:i A');
        $formattedSnapshot=acceptanceSnapshot('resource',$dateResourceId);
        if ($dateSnapshot['read_set'][0]['fingerprint']
            !==$formattedSnapshot['read_set'][0]['fingerprint']) {
            // RU: Печатаем только отличающиеся поля своей disposable-записи.
            // EN: Diagnose only differing fields of the disposable fixture.
            $afterDateRead=acceptanceRpc('get_element',array(
                'type'=>'resource','id'=>$dateResourceId
            ));
            $differences=array();
            foreach($dateRead as $field=>$value) {
                if(!array_key_exists($field,$afterDateRead)
                    || $value !== $afterDateRead[$field]) {
                    $differences[$field]=array(
                        'before'=>$value,'after'=>$afterDateRead[$field]??null
                    );
                }
            }
            fwrite(STDERR,'RESOURCE_DATE_CAS_DIFF '
                .json_encode($differences).PHP_EOL);
        }
        acceptanceCheck(
            $dateSnapshot['read_set'][0]['fingerprint']
                ===$formattedSnapshot['read_set'][0]['fingerprint'],
            'resource CAS fingerprint independent of Manager date/time format'
        );
    } finally {
        $modx->setOption('manager_date_format',$originalDateFormat);
        $modx->setOption('manager_time_format',$originalTimeFormat);
    }
    // RU: Отсутствующий ID в bulk_resources не должен публиковать уже
    // RU: найденный ресурс и оставлять незаметный частичный результат.
    // EN: Mixed existing/missing bulk targets must reject preflight before
    // EN: publishing any valid resource; dry_run stays nonmutating.
    $nonexistentResourceId=2147483647;
    $bulkMissingDenied=false;
    try {acceptanceRpc('bulk_resources',array(
        'operation'=>'publish','ids'=>array($resourceId,$nonexistentResourceId),
    ));} catch(ModxMCPClientException $e) {
        $bulkMissingDenied=strpos($e->getMessage(),'NOT_FOUND:')!==false;
    }
    acceptanceCheck($bulkMissingDenied,
        'bulk_resources absent ID rejected before any mutation');
    $afterBulkDenied=acceptanceRpc('get_element',array(
        'type'=>'resource','id'=>$resourceId
    ));
    acceptanceCheck((int)($afterBulkDenied['published']??-1)===0
        && (string)($afterBulkDenied['content']??'')==='BEFORE_'.$fixture,
        'bulk_resources preflight left existing resource untouched');
    $bulkPreview=acceptanceRpc('bulk_resources',array(
        'operation'=>'publish','ids'=>array($resourceId,$nonexistentResourceId),
        'dry_run'=>true
    ));
    acceptanceCheck(($bulkPreview['results'][1]['status']??null)==='not_found',
        'bulk_resources dry-run still reports missing target');

    $beforeUpdate=acceptanceSnapshot('resource',$resourceId);
    acceptanceRpc('update_element',array(
        'type'=>'resource','id'=>$resourceId,'content'=>'AFTER_'.$fixture,
        '_runtime_preconditions'=>$beforeUpdate,
    ));
    $read=acceptanceRpc('get_element',array('type'=>'resource','id'=>$resourceId));
    acceptanceCheck((string)$read['content']==='AFTER_'.$fixture,
        'resource update persisted');
    // RU: Изменение только content не должно затрагивать остальные поля.
    // EN: Content-only updates must preserve all unrelated resource flags.
    foreach (array('richtext'=>1,'searchable'=>0,'cacheable'=>0) as $field=>$expected) {
        acceptanceCheck(isset($read[$field]) && (int)$read[$field]===$expected,
            'partial resource update preserved '.$field);
    }
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

    $autoStaticOriginal=$modx->getOption('modxmcp.auto_static',null,false);
    $modx->setOption('modxmcp.auto_static',true); // disposable process only
    try {
        $chunk=acceptanceRpc('create_element',array(
            'type'=>'chunk','name'=>'MCP_Acceptance_'.$fixture,'content'=>'CHUNK_'.$fixture,
            'skip_auto_static'=>true,
        ));
        $chunkId=(int)($chunk['id']??0);
        acceptanceCheck($chunkId>0,'chunk created with per-call auto_static override');
        $dynamic=acceptanceRpc('get_element',array('type'=>'chunk','id'=>$chunkId));
        acceptanceCheck(empty($dynamic['static']) && empty($dynamic['static_file']),'explicit skip kept newly created chunk dynamic');
        acceptanceRpc('update_element',array('type'=>'chunk','id'=>$chunkId,
            'content'=>'CHUNK_'.$fixture,'skip_auto_static'=>true));
        $dynamic=acceptanceRpc('get_element',array('type'=>'chunk','id'=>$chunkId));
        acceptanceCheck(empty($dynamic['static']) && empty($dynamic['static_file'])
            && $dynamic['content']==='CHUNK_'.$fixture,'explicit skip preserved dynamic chunk on update');
        acceptanceCheck((bool)$modx->getOption('modxmcp.auto_static',null,false),'site default unchanged by per-call override');
        echo 'AUTO_STATIC_NATIVE_OVERRIDE_OK'.PHP_EOL;
    } finally {
        $modx->setOption('modxmcp.auto_static',$autoStaticOriginal);
    }
    acceptanceCheck(is_array($chunk) && isset($chunk['id']) && (int)$chunk['id']>0,
        'chunk creation');
    $chunkId=(int)$chunk['id'];
    $chunkRead=acceptanceRpc('get_element',array('type'=>'chunk','id'=>$chunkId));
    acceptanceCheck(is_array($chunkRead) && (string)$chunkRead['content']==='CHUNK_'.$fixture,
        'chunk readback');

    // RU: Два одноразовых чанка: при слишком маленьком лимите поиск не
    // RU: должен поменять ни один, затем оба меняются с достаточным лимитом.
    // EN: Two disposable chunks: an undersized search limit must leave
    // EN: both unchanged; an adequate limit must update both through MCP.
    $replaceNeedle='MCP_BATCH_FIND_'.$fixture;
    acceptanceRpc('update_element',array(
        'type'=>'chunk','id'=>$chunkId,'content'=>$replaceNeedle,
    ));
    $replaceChunk=acceptanceRpc('create_element',array(
        'type'=>'chunk','name'=>'MCP_BatchSecond_'.$fixture,
        'content'=>$replaceNeedle,
    ));
    $replaceChunkId=(int)($replaceChunk['id']??0);
    acceptanceCheck($replaceChunkId>0,'second disposable batch chunk created');
    $modx->setOption('modxmcp.disabled_groups','');
    $replaceArgs=array(
        'find'=>$replaceNeedle,
        'replacement'=>'MCP_BATCH_DONE_'.$fixture,
        'types'=>array('chunk'),'case_sensitive'=>true,
        'limit'=>1,
    );
    $limitRejected=false;
    try {acceptanceRpc('replace_across',$replaceArgs);}
    catch(ModxMCPClientException $e) {
        $limitRejected=strpos($e->getMessage(),'VALIDATION_ERROR:')!==false;
    }
    acceptanceCheck($limitRejected,'replace_across incomplete selection denied');
    foreach (array($chunkId,$replaceChunkId) as $targetId) {
        $unchanged=acceptanceRpc('get_element',array(
            'type'=>'chunk','id'=>$targetId
        ));
        acceptanceCheck((string)($unchanged['content']??'')===$replaceNeedle,
            'replace_across limit rejection left both targets unchanged');
    }
    $replacePreview=acceptanceRpc('replace_across',array_merge(
        $replaceArgs,array('dry_run'=>true)
    ));
    acceptanceCheck(!empty($replacePreview['search_limit_reached']),
        'replace_across preview warns about incomplete search');
    $replaceResult=acceptanceRpc('replace_across',array_merge(
        $replaceArgs,array('limit'=>10)
    ));
    acceptanceCheck((int)($replaceResult['elements']??0)===2,
        'replace_across updated two targets through MCP');
    foreach (array($chunkId,$replaceChunkId) as $targetId) {
        $replaced=acceptanceRpc('get_element',array(
            'type'=>'chunk','id'=>$targetId
        ));
        acceptanceCheck((string)($replaced['content']??'')
            ==='MCP_BATCH_DONE_'.$fixture,
            'replace_across changed only the intended fixture content');
    }

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
    // RU: Группа пользователей не должна допускать повтор со старым CAS.
    // EN: User-group updates must reject replay of a stale CAS snapshot.
    $groupRevision=acceptanceRpc('get_site_state');
    $groupChange=array(
        'id'=>$groupId,'name'=>'MCP_AcceptanceGroupUpdated_'.$fixture,
        '_runtime_preconditions'=>array(
            'observed_site_revision'=>(int)$groupRevision['site_revision'],
            'strict_site_revision'=>true,'read_set'=>array(),
        ),
    );
    acceptanceRpc('update_user_group',$groupChange);
    $groupRead=acceptanceRpc('get_user_group',array('id'=>$groupId));
    acceptanceCheck((string)($groupRead['name'] ?? '')==='MCP_AcceptanceGroupUpdated_'.$fixture,
        'ACL user group update persisted');
    $groupStale=false;
    try {acceptanceRpc('update_user_group',$groupChange);}
    catch (ModxMCPClientException $e) {
        $groupStale=strpos($e->getMessage(),'STALE_STATE')!==false;
    }
    acceptanceCheck($groupStale,'ACL user group stale CAS denied');

    // RU: Индивидуальные интеграционные проверки ACL create/update/delete.
    // RU: Все объекты созданы в disposable MODX и имеют случайные имена.
    // EN: Individual ACL mutation checks use random disposable fixtures.
    $role=acceptanceRpc('create_role',array(
        'name'=>'MCP_AcceptanceRole_'.$fixture,'authority'=>985,
    ));
    acceptanceCheck(is_array($role) && (int)($role['id'] ?? 0)>0,
        'ACL role creation through guarded MCP');
    $roleId=(int)$role['id'];
    $roleRead=acceptanceRpc('get_role',array('id'=>$roleId));
    acceptanceCheck(is_array($roleRead)
        && (string)($roleRead['name'] ?? '')==='MCP_AcceptanceRole_'.$fixture,
        'ACL role readback after create');
    $roleRevision=acceptanceRpc('get_site_state');
    $roleUpdate=array(
        'id'=>$roleId,'name'=>'MCP_AcceptanceRoleUpdated_'.$fixture,
        'authority'=>984,
        '_runtime_preconditions'=>array(
            'observed_site_revision'=>(int)$roleRevision['site_revision'],
            'strict_site_revision'=>true,'read_set'=>array(),
        ),
    );
    acceptanceRpc('update_role',$roleUpdate);
    $roleRead=acceptanceRpc('get_role',array('id'=>$roleId));
    acceptanceCheck(is_array($roleRead)
        && (string)($roleRead['name'] ?? '')==='MCP_AcceptanceRoleUpdated_'.$fixture,
        'ACL role update persisted');
    $roleStale=false;
    try { acceptanceRpc('update_role',$roleUpdate); }
    catch (ModxMCPClientException $e) {
        $roleStale=strpos($e->getMessage(),'STALE_STATE')!==false;
    }
    acceptanceCheck($roleStale,'ACL role stale CAS denied');
    $roleRead=acceptanceRpc('get_role',array('id'=>$roleId));
    acceptanceCheck((string)($roleRead['name'] ?? '')==='MCP_AcceptanceRoleUpdated_'.$fixture,
        'stale ACL role update has no effect');
    // Role remains available for membership testing below.
    $secondRole=acceptanceRpc('create_role',array(
        'name'=>'MCP_AcceptanceMemberRole_'.$fixture,'authority'=>987,
    ));
    $secondRoleId=(int)($secondRole['id']??0);
    acceptanceCheck($secondRoleId>0,'second disposable role for member update');

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
    // RU: Безопасное обновление имени пользователя должно работать,
    // RU: а устаревшая CAS-проверка не должна менять профиль.
    // EN: Normal profile updates must work; stale CAS must not change user data.
    $userRevision=acceptanceRpc('get_site_state');
    $userChange=array(
        'id'=>$userId,'fullname'=>'MCP Updated User '.$fixture,
        '_runtime_preconditions'=>array(
            'observed_site_revision'=>(int)$userRevision['site_revision'],
            'strict_site_revision'=>true,'read_set'=>array(),
        ),
    );
    acceptanceRpc('update_user',$userChange);
    $userRead=acceptanceRpc('get_user',array('id'=>$userId));
    acceptanceCheck((string)($userRead['fullname'] ?? '')==='MCP Updated User '.$fixture,
        'ACL user profile change persisted');
    acceptanceCheck((int)($userRead['active'] ?? -1)===0,
        'ACL user activation flag preserved');
    $staleUserDenied=false;
    try {acceptanceRpc('update_user',$userChange);}
    catch (ModxMCPClientException $e) {
        $staleUserDenied=strpos($e->getMessage(),'STALE_STATE')!==false;
    }
    acceptanceCheck($staleUserDenied,'ACL user replay with stale CAS denied');

    // RU: В штатном чтении профиля не должны появляться хеши и соли.
    // EN: Normal user reads must never include password hashes or salts.
    foreach (array('password','cachepwd','salt','remote_key','sudo') as $secretField) {
        acceptanceCheck(!array_key_exists($secretField,$userRead),
            'sensitive user field hidden: '.$secretField);
    }

    // RU: Жизненный цикл членства: добавление, сверка через MCP, отказ
    // RU: по устаревшему CAS, удаление и независимое чтение результата.
    // EN: Membership lifecycle via MCP: grant, readback, stale CAS
    // EN: rejection, revoke and independent readback.
    // RU: Независимый Manager добавляет участника, не изменяя site_revision.
    // RU: MCP обязан отказать со старым отпечатком группы.
    // EN: Independent Manager membership writes must invalidate a group CAS
    // EN: snapshot without advancing the connector site revision.
    $externalBefore=acceptanceSnapshot('user_group',$groupId);
    $worker=array(PHP_BINARY,__DIR__.'/test.ci-acl-member-manager-worker.php',
        (string)$groupId,(string)$userId,(string)$roleId);
    $workerCommand=implode(' ',array_map('escapeshellarg',$worker));
    $workerOutput=array();$workerExit=1;
    exec($workerCommand.' 2>&1',$workerOutput,$workerExit);
    acceptanceCheck($workerExit===0 && strpos(implode(' ',$workerOutput),
        'DISPOSABLE_MANAGER_MEMBERSHIP_CHANGED_OK')!==false,
        'independent Manager added group member');
    $externalCurrent=acceptanceRpc('get_site_state');
    acceptanceCheck((int)$externalCurrent['site_revision']
        ===(int)$externalBefore['observed_site_revision'],
        'independent membership does not bump MCP site revision');
    $externalDenied=false;
    try {
        acceptanceRpc('update_user_group',array(
            'id'=>$groupId,'name'=>'FORBIDDEN_STALE_'.$fixture,
            '_runtime_preconditions'=>$externalBefore));
    } catch (ModxMCPClientException $e) {
        $externalDenied=strpos($e->getMessage(),'STALE_STATE')!==false;
    }
    acceptanceCheck($externalDenied,'stale group snapshot rejected Manager membership');
    $afterExternal=acceptanceRpc('get_user_group',array('id'=>$groupId));
    acceptanceCheck((string)($afterExternal['name']??'')
        !=='FORBIDDEN_STALE_'.$fixture,
        'rejected group mutation preserved Manager membership');
    acceptanceClean('remove_user_from_group',array(
        'user'=>$userId,'usergroup'=>$groupId));
    echo 'ACL_MANAGER_MEMBERSHIP_CONFLICT_DENIED_OK'.PHP_EOL;
    // RU: Аварийная проверка гонки: Manager вставляет членство, удерживая
    // RU: незавершённую транзакцию; MCP пытается обновить эту же группу.
    // EN: Probe a concurrent uncommitted Manager membership insertion. A
    // EN: successful MCP update here would demonstrate a CAS TOCTOU gap.
    // Probe every group/membership mutation against a concurrent uncommitted
    // Manager insertion in an initially empty indexed membership range.
    foreach (array('update_user_group','delete_user_group',
                   'add_user_to_group','update_group_member',
                   'remove_user_from_group') as $raceAction) {
        $readyFile=rtrim(getenv('RUNNER_TEMP'),'/').'/member-cas-race-'.bin2hex(random_bytes(5));
        $raceUser=acceptanceRpc('create_user',array(
            'username'=>'mcp_race_'.bin2hex(random_bytes(6)),
            'fullname'=>'Disposable ACL race '.$fixture,
            'email'=>'mcp-race-'.bin2hex(random_bytes(6)).'@example.invalid',
            'password'=>'CI-'.bin2hex(random_bytes(12)).'9Q!',
            'passwordnotifymethod'=>'s','active'=>0));
        $raceUserId=(int)$raceUser['id'];
        $raceBefore=acceptanceSnapshot('user_group',$groupId);
        $raceCommand=array(PHP_BINARY,__DIR__.'/test.ci-acl-member-manager-worker.php',
            (string)$groupId,(string)$raceUserId,(string)$roleId,$readyFile);
        $racePipes=array();
        $raceProcess=proc_open($raceCommand,array(
            0=>array('pipe','r'),1=>array('pipe','w'),2=>array('pipe','w')
        ),$racePipes);
        if(!is_resource($raceProcess))throw new RuntimeException('Cannot spawn Manager race');
        fclose($racePipes[0]);
        $until=microtime(true)+12;
        while(!is_file($readyFile) && microtime(true)<$until)usleep(10000);
        acceptanceCheck(is_file($readyFile),'Manager transaction ready for '.$raceAction);
        $raceArgs=array('_runtime_preconditions'=>$raceBefore);
        if ($raceAction==='update_user_group') {
            $raceArgs['id']=$groupId;
            $raceArgs['name']='FORBIDDEN_RACE_'.$fixture;
        } elseif ($raceAction==='delete_user_group') {
            $raceArgs['id']=$groupId;
        } else {
            $raceArgs['usergroup']=$groupId;
            $raceArgs['user']=$userId;
            if ($raceAction!=='remove_user_from_group') $raceArgs['role']=$roleId;
        }
        $raceDenied=false;
        try {
            acceptanceRpc($raceAction,$raceArgs);
        } catch (ModxMCPClientException $e) {
            $raceDenied=strpos($e->getMessage(),'STALE_STATE')!==false;
            if(!$raceDenied)throw $e;
        } finally {
            $raceOutput=stream_get_contents($racePipes[1]);
            $raceError=stream_get_contents($racePipes[2]);
            fclose($racePipes[1]);fclose($racePipes[2]);
            $raceExit=proc_close($raceProcess);
            @unlink($readyFile);
            acceptanceCheck($raceExit===0,'external Manager committed '.$raceAction.': '.$raceError);
        }
        if(!$raceDenied) echo 'ACL_MEMBERSHIP_TOCTOU_RACE_REPRODUCED action='.$raceAction.PHP_EOL;
        acceptanceCheck($raceDenied,'P0 TOCTOU refused '.$raceAction);
        $afterRaceGroup=acceptanceRpc('get_user_group',array('id'=>$groupId));
        acceptanceCheck((int)($afterRaceGroup['id']??0)===$groupId,
            'rejected race preserved group '.$raceAction);
        acceptanceClean('remove_user_from_group',array(
            'user'=>$raceUserId,'usergroup'=>$groupId));
        acceptanceClean('delete_user',array('id'=>$raceUserId));
        echo 'ACL_MEMBERSHIP_TOCTOU_RACE_DENIED_OK action='.$raceAction.PHP_EOL;
    }
    // Independently committed role changes and deletions must also invalidate
    // a group fingerprint, even if Manager never increments MCP site_revision.
    $managerMember=acceptanceRpc('create_user',array(
        'username'=>'mcp_manager_acl_'.bin2hex(random_bytes(5)),
        'fullname'=>'Disposable Manager overlap',
        'email'=>'mcp-manager-'.bin2hex(random_bytes(5)).'@example.invalid',
        'password'=>'CI-'.bin2hex(random_bytes(12)).'9Q!',
        'passwordnotifymethod'=>'s','active'=>0));
    $managerMemberId=(int)$managerMember['id'];
    acceptanceRpc('add_user_to_group',array(
        'user'=>$managerMemberId,'usergroup'=>$groupId,'role'=>$roleId));
    foreach(array('update_role','delete') as $managerMode) {
        $beforeManager=acceptanceSnapshot('user_group',$groupId);
        $readyManager=rtrim(getenv('RUNNER_TEMP'),'/').'/member-manager-overlap-'.bin2hex(random_bytes(5));
        $cmdManager=array(PHP_BINARY,__DIR__.'/test.ci-acl-member-manager-worker.php',
            (string)$groupId,(string)$managerMemberId,(string)$secondRoleId,
            $readyManager,$managerMode);
        $pipesManager=array();
        $procManager=proc_open($cmdManager,array(
            0=>array('pipe','r'),1=>array('pipe','w'),2=>array('pipe','w')
        ),$pipesManager);
        if(!is_resource($procManager))throw new RuntimeException('Cannot spawn role/remove Manager');
        fclose($pipesManager[0]);
        $untilManager=microtime(true)+12;
        while(!is_file($readyManager) && microtime(true)<$untilManager)usleep(10000);
        acceptanceCheck(is_file($readyManager),'Manager role/remove transaction ready');
        $deniedManager=false;
        try {
            acceptanceRpc('update_user_group',array(
                'id'=>$groupId,'name'=>'FORBIDDEN_OVERLAP_'.$fixture,
                '_runtime_preconditions'=>$beforeManager));
        } catch (ModxMCPClientException $e) {
            $deniedManager=strpos($e->getMessage(),'STALE_STATE')!==false;
            if(!$deniedManager)throw $e;
        } finally {
            $outManager=stream_get_contents($pipesManager[1]);
            $errManager=stream_get_contents($pipesManager[2]);
            fclose($pipesManager[1]);fclose($pipesManager[2]);
            $rcManager=proc_close($procManager);
            @unlink($readyManager);
            acceptanceCheck($rcManager===0,'Manager role/remove committed: '.$errManager);
        }
        acceptanceCheck($deniedManager,'MCP refused concurrent Manager '.$managerMode);
        $afterManager=acceptanceRpc('get_site_state');
        acceptanceCheck((int)$afterManager['site_revision']
            ===(int)$beforeManager['observed_site_revision'],
            'Manager '.$managerMode.' did not consume MCP revision');
        $readManager=acceptanceRpc('list_user_group_members',array(
            'usergroup'=>$groupId,'limit'=>200));
        $foundManager=false;
        foreach($readManager['results'] as $memberRow) {
            if(!is_array($memberRow))continue;
            if((int)($memberRow['id']??0)===$managerMemberId) {
                $foundManager=true;
                if($managerMode==='update_role') {
                    acceptanceCheck((int)($memberRow['role']??0)===$secondRoleId,
                        'Manager role update survived MCP conflict');
                }
            }
        }
        acceptanceCheck($foundManager===($managerMode==='update_role'),
            'independent Manager member readback '.$managerMode);
        echo 'ACL_MANAGER_MEMBERSHIP_'.strtoupper($managerMode).'_RACE_DENIED_OK'.PHP_EOL;
    }
    acceptanceClean('delete_user',array('id'=>$managerMemberId));

    $memberName='mcp_acceptance_'.$fixture;
    $membersBefore=acceptanceRpc('list_user_group_members',array(
        'usergroup'=>$groupId,'limit'=>200,
    ));
    acceptanceCheck(is_array($membersBefore)
        && isset($membersBefore['results']) && is_array($membersBefore['results']),
        'ACL membership baseline available through MCP');
    $groupMembersBefore=acceptanceSnapshot('user_group',$groupId);
    $memberSnapshot=acceptanceRpc('get_site_state');
    $addMember=array(
        'user'=>$userId,'usergroup'=>$groupId,'role'=>$roleId,
        '_runtime_preconditions'=>array(
            'observed_site_revision'=>(int)$memberSnapshot['site_revision'],
            'strict_site_revision'=>true,'read_set'=>array(),
        ),
    );
    acceptanceRpc('add_user_to_group',$addMember);
    $memberAdded=true;
    $membersAdded=acceptanceRpc('list_user_group_members',array(
        'usergroup'=>$groupId,'limit'=>200,
    ));
    $foundMember=false;
    foreach ($membersAdded['results'] as $row) {
        if (!is_array($row)) continue;
        if ((int)($row['user'] ?? 0)===$userId
            || (string)($row['username'] ?? '')===$memberName) {
            $foundMember=true;
            break;
        }
    }
    acceptanceCheck($foundMember,'ACL member grant visible through MCP');
    $groupMembersAfter=acceptanceSnapshot('user_group',$groupId);
    acceptanceCheck($groupMembersBefore['read_set'][0]['fingerprint']
        !==$groupMembersAfter['read_set'][0]['fingerprint'],
        'group target fingerprint covers membership changes');
    $memberReplayDenied=false;
    try {acceptanceRpc('add_user_to_group',$addMember);}
    catch (ModxMCPClientException $e) {
        $memberReplayDenied=strpos($e->getMessage(),'STALE_STATE')!==false;
    }
    acceptanceCheck($memberReplayDenied,'ACL membership replay with stale CAS denied');
    $membersAfterReplay=acceptanceRpc('list_user_group_members',array(
        'usergroup'=>$groupId,'limit'=>200,
    ));
    acceptanceCheck(count($membersAfterReplay['results'])===count($membersAdded['results']),
        'rejected membership replay produced no duplicate');
    echo 'CAS_USER_GROUP_ADD_MEMBER_READBACK_STALE_REPLAY_OK'.PHP_EOL;

    // RU: Меняем роль только у собственного неактивного пользователя.
    // EN: Change role only for our inactive disposable user.
    $memberUpdateState=acceptanceRpc('get_site_state');
    $memberUpdate=array('user'=>$userId,'usergroup'=>$groupId,'role'=>$secondRoleId,
        '_runtime_preconditions'=>array(
            'observed_site_revision'=>(int)$memberUpdateState['site_revision'],
            'strict_site_revision'=>true,'read_set'=>array(),
        ),
    );
    acceptanceRpc('update_group_member',$memberUpdate);
    $membersUpdated=acceptanceRpc('list_user_group_members',array(
        'usergroup'=>$groupId,'limit'=>200,
    ));
    $updatedRoleFound=false;
    foreach($membersUpdated['results'] as $memberRow){
        if(is_array($memberRow) && (int)($memberRow['id']??0)===$userId
            && (int)($memberRow['role']??0)===$secondRoleId) {
            $updatedRoleFound=true;
        }
    }
    acceptanceCheck($updatedRoleFound,'new member role independently read via MCP');
    $staleMemberUpdate=false;
    try {acceptanceRpc('update_group_member',$memberUpdate);}
    catch(ModxMCPClientException $e){
        $staleMemberUpdate=strpos($e->getMessage(),'STALE_STATE')!==false;
    }
    acceptanceCheck($staleMemberUpdate,'member role stale CAS denied');
    echo 'CAS_USER_GROUP_UPDATE_MEMBER_READBACK_STALE_REPLAY_OK'.PHP_EOL;

    // RU: Удаление фиксируется по старому CAS; повтор запрещён.
    // EN: Remove membership under one snapshot and reject stale replay.
    $memberRemoveState=acceptanceRpc('get_site_state');
    $memberRemove=array('user'=>$userId,'usergroup'=>$groupId,
        '_runtime_preconditions'=>array(
            'observed_site_revision'=>(int)$memberRemoveState['site_revision'],
            'strict_site_revision'=>true,'read_set'=>array(),
        ),
    );
    acceptanceClean('remove_user_from_group',$memberRemove);
    $memberAdded=false;
    $removeReplayDenied=false;
    try {acceptanceRpc('remove_user_from_group',$memberRemove);}
    catch(ModxMCPClientException $e){
        $removeReplayDenied=strpos($e->getMessage(),'STALE_STATE')!==false;
    }
    acceptanceCheck($removeReplayDenied,'member removal stale CAS denied');
    echo 'CAS_USER_GROUP_REMOVE_MEMBER_READBACK_STALE_REPLAY_OK'.PHP_EOL;
    $membersRemoved=acceptanceRpc('list_user_group_members',array(
        'usergroup'=>$groupId,'limit'=>200,
    ));
    acceptanceCheck(count($membersRemoved['results'])===count($membersBefore['results']),
        'ACL membership removal restored original count');
    acceptanceClean('delete_role',array('id'=>$secondRoleId));
    $secondRoleId=0;
    acceptanceClean('delete_role',array('id'=>$roleId));
    $roleId=0;

    // RU: Даже подписанная update_user не вправе назначить superuser.
    // EN: Even an authorized update_user may not modify sudo status.
    $sudoDenied=false;
    try {
        acceptanceRpc('update_user',array('id'=>$userId,'sudo'=>1));
    } catch (ModxMCPClientException $e) {
        $sudoDenied=strpos($e->getMessage(),'AUTH_DENIED:')===0;
    }
    acceptanceCheck($sudoDenied,'ACL update_user cannot change sudo flag');
    $userAfterDenied=acceptanceRpc('get_user',array('id'=>$userId));
    acceptanceCheck((int)($userAfterDenied['id'] ?? 0)===$userId,
        'user still available after blocked sudo update');
    // RU: Обход ACL-политики через скрытые группы, класс процессора и
    // RU: пароль должен быть запрещён даже при наличии update_user.
    // EN: Hidden groups, custom processor classes and password changes
    // EN: must not bypass the narrow update_user site policy.
    foreach (array(
        array('update_user',array('id'=>$userId,'groups'=>array(
            array('usergroup'=>$groupId,'role'=>1)
        ))),
        array('create_user',array(
            'username'=>'mcp_denied_'.$fixture,
            'groups'=>array(array('usergroup'=>$groupId,'role'=>1)),
        )),
        array('create_user',array(
            'username'=>'mcp_denied_'.$fixture,'class_key'=>'UntrustedUser',
        )),
        array('update_user',array(
            'id'=>$userId,'newpassword'=>'MustNotRotateThisAccount',
        )),
    ) as $attempt) {
        $privilegeDenied=false;
        try {acceptanceRpc($attempt[0],$attempt[1]);}
        catch (ModxMCPClientException $e) {
            $privilegeDenied=strpos($e->getMessage(),'AUTH_DENIED:')===0;
        }
        acceptanceCheck($privilegeDenied,
            'hidden privilege mutation blocked: '.$attempt[0]);
    }
    $membersAfterDenied=acceptanceRpc('list_user_group_members',array(
        'usergroup'=>$groupId,'limit'=>200,
    ));
    acceptanceCheck(count($membersAfterDenied['results'])===count($membersBefore['results']),
        'hidden user-group mutation did not create membership');
    $deniedUsers=acceptanceRpc('list_users',array('query'=>'mcp_denied_'.$fixture));
    $deniedUserFound=false;
    if(isset($deniedUsers['results']) && is_array($deniedUsers['results'])) {
        foreach($deniedUsers['results'] as $row) {
            if(is_array($row) && ($row['username'] ?? '')==='mcp_denied_'.$fixture)
                $deniedUserFound=true;
        }
    }
    acceptanceCheck(!$deniedUserFound,'hidden create_user route created no user');

    $settingKey='mcp_acceptance_'.$fixture;
    acceptanceRpc('create_system_setting',array('key'=>$settingKey,'value'=>'ONE'));
    $setting=acceptanceRpc('get_system_setting',array('key'=>$settingKey));
    acceptanceCheck(is_array($setting) && (string)$setting['value']==='ONE',
        'system setting create/readback');
    acceptanceRpc('update_system_setting',array('key'=>$settingKey,'value'=>'TWO'));
    $setting=acceptanceRpc('get_system_setting',array('key'=>$settingKey));
    acceptanceCheck((string)$setting['value']==='TWO','system setting update/readback');
    // RU: PR #56, @AgelxNash: PK-ключ должен переименовываться одним UPDATE,
    // RU: с CAS и независимым чтением, а не через xPDOObject::save().
    // EN: PR #56, @AgelxNash: PK rename uses one guarded UPDATE and readback.
    $originalSettingKey=$settingKey;
    $renamedSettingKey=$settingKey.'_renamed';
    $renameState=acceptanceRpc('get_site_state');
    $renameRequest=array(
        'key'=>$settingKey,'new_key'=>$renamedSettingKey,'value'=>'THREE',
        '_runtime_preconditions'=>array(
            'observed_site_revision'=>(int)$renameState['site_revision'],
            'strict_site_revision'=>true,'read_set'=>array(),
        ),
    );
    $renamed=acceptanceRpc('update_system_setting',$renameRequest);
    // RU: После успешного переименования finally удалит только новый ключ.
    // EN: Cleanup always targets the actual renamed temporary setting.
    $settingKey=$renamedSettingKey;
    acceptanceCheck(($renamed['key']??'')===$settingKey
        && ($renamed['value']??'')==='THREE',
        'setting rename returned updated key/value');
    $setting=acceptanceRpc('get_system_setting',array('key'=>$settingKey));
    acceptanceCheck(($setting['value']??'')==='THREE',
        'setting rename persisted via independent MCP readback');
    $oldStillExists=false;
    try {acceptanceRpc('get_system_setting',array('key'=>$originalSettingKey));
        $oldStillExists=true;
    } catch(ModxMCPClientException $e) {}
    acceptanceCheck(!$oldStillExists,'old system setting key removed');
    $renameReplayDenied=false;
    try {acceptanceRpc('update_system_setting',$renameRequest);}
    catch(ModxMCPClientException $e) {
        $renameReplayDenied=strpos($e->getMessage(),'STALE_STATE')!==false;
    }
    acceptanceCheck($renameReplayDenied,'stale setting rename replay denied');
    $protectedRenameDenied=false;
    try {acceptanceRpc('update_system_setting',array(
        'key'=>$settingKey,'new_key'=>' MODXMCP.api_token '
    ));} catch(ModxMCPClientException $e) {
        $protectedRenameDenied=strpos($e->getMessage(),'modxmcp.')!==false
            || strpos($e->getMessage(),'AUTH_DENIED')!==false;
    }
    acceptanceCheck($protectedRenameDenied,
        'protected modxmcp key cannot be rename target');

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
    // RU: Прямой путь MODX workspace/packages/install не должен стать
    // RU: доступным через обычный whitelist, даже при точном совпадении.
    // EN: Real MODX workspace/packages/install must remain privileged,
    // EN: even if the ordinary site allowlist names it exactly.
    $modx->setOption('modxmcp.allowed_processors','workspace/packages/install');
    $packageDenied=false;
    try {
        acceptanceRpc('run_processor',array(
            'processor'=>'workspace/packages/install',
            'properties'=>array('signature'=>'MUST_NOT_INSTALL'),
        ));
    } catch (\Throwable $e) {
        $packageDenied=strpos($e->getMessage(),'AUTH_DENIED')!==false;
    }
    acceptanceCheck($packageDenied,
        'real package install processor denied in ordinary mode');

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
    acceptanceClean('delete_element',array('type'=>'chunk','id'=>$replaceChunkId));
    $replaceChunkId=0;
    acceptanceClean('delete_element',array('type'=>'chunk','id'=>$chunkId));
    $chunkId=0;
    acceptanceClean('delete_element',array('type'=>'resource','id'=>$dateResourceId,'dry_run'=>false));
    $dateResourceId=0;
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
    if ($memberAdded && $userId>0 && $groupId>0)
        acceptanceClean('remove_user_from_group',array('user'=>$userId,'usergroup'=>$groupId));
    if ($userId>0) acceptanceClean('delete_user',array('id'=>$userId));
    if ($secondRoleId>0) acceptanceClean('delete_role',array('id'=>$secondRoleId));
    if ($roleId>0) acceptanceClean('delete_role',array('id'=>$roleId));
    if ($groupId>0) acceptanceClean('delete_user_group',array('id'=>$groupId));
    if ($replaceChunkId>0) acceptanceClean('delete_element',array(
        'type'=>'chunk','id'=>$replaceChunkId));
    if ($chunkId>0) acceptanceClean('delete_element',array('type'=>'chunk','id'=>$chunkId));
    if ($dateResourceId>0) acceptanceClean('delete_element',array(
        'type'=>'resource','id'=>$dateResourceId,'dry_run'=>false));
    if ($resourceId>0) acceptanceClean('delete_element',array(
        'type'=>'resource','id'=>$resourceId,'dry_run'=>false));
}
