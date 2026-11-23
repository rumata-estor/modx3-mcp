<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Старая ACL-группа и членство должны пережить transport update + индекс.
 * EN: Pre-upgrade ACL membership must survive true transport update and index migration.
 */
if (PHP_SAPI !== 'cli' || getenv('MODXMCP_DISPOSABLE_CI') !== '1'
    || strpos((string)getenv('MODX_CONFIG_CORE'), '/home/runner/') !== 0
    || strpos((string)getenv('RUNNER_TEMP'), '/home/runner/') !== 0
    || !is_file((string)getenv('MODX_CONFIG_CORE'))) {
    fwrite(STDERR, "Refusing historical ACL migration test outside disposable CI.\n");
    exit(3);
}
$phase = isset($argv[1]) ? (string)$argv[1] : '';
if (!in_array($phase, array('before', 'after'), true)) exit(3);
require getenv('MODX_CONFIG_CORE');
$is2 = !is_file(MODX_CORE_PATH . 'src/Revolution/modX.php');
if ($is2) {
    require MODX_CORE_PATH . 'model/modx/modx.class.php';
    $modx = new modX();
    $groupClass = 'modUserGroup';
    $roleClass = 'modUserGroupRole';
    $memberClass = 'modUserGroupMember';
    $userClass = 'modUser';
} else {
    require MODX_CORE_PATH . 'vendor/autoload.php';
    $modx = \MODX\Revolution\modX::getInstance();
    $groupClass = 'MODX\\Revolution\\modUserGroup';
    $roleClass = 'MODX\\Revolution\\modUserGroupRole';
    $memberClass = 'MODX\\Revolution\\modUserGroupMember';
    $userClass = 'MODX\\Revolution\\modUser';
}
$modx->initialize('mgr');
$manifest = getenv('RUNNER_TEMP') . '/modxmcp-historical-acl.json';
function oldAclCheck($condition, $message) {
    if (!$condition) throw new RuntimeException('HISTORICAL_ACL_UPGRADE_FAIL ' . $message);
}
$schema = (string)$modx->query('SELECT DATABASE()')->fetchColumn();
oldAclCheck($schema === 'modx_build', 'not a disposable database');
$table = $modx->getTableName($memberClass);
oldAclCheck((bool)preg_match('/\A(?:`[A-Za-z0-9_]+`|[A-Za-z0-9_]+)\z/D', $table), 'unsafe table name');
function oldAclRowCount($modx, $table) {
    $query = $modx->query('SELECT COUNT(*) FROM ' . $table);
    oldAclCheck((bool)$query, 'membership count query failed');
    return (int)$query->fetchColumn();
}
if ($phase === 'before') {
    $admin = $modx->getObject($userClass, 1);
    oldAclCheck($admin !== null && $admin !== false, 'historical admin account missing');
    $tag = 'MCP_Historical_ACL_' . bin2hex(random_bytes(6));
    $role = $modx->newObject($roleClass);
    oldAclCheck((bool)$role, 'cannot create role');
    $role->set('name', $tag . '_role');
    $role->set('authority', 9999);
    oldAclCheck($role->save(), 'cannot save historical role');
    $group = $modx->newObject($groupClass);
    oldAclCheck((bool)$group, 'cannot create group');
    $group->set('name', $tag . '_group');
    oldAclCheck($group->save(), 'cannot save historical group');
    $member = $modx->newObject($memberClass);
    oldAclCheck((bool)$member, 'cannot create membership');
    $member->set('user_group', (int)$group->get('id'));
    $member->set('member', (int)$admin->get('id'));
    $member->set('role', (int)$role->get('id'));
    $member->set('rank', 0);
    oldAclCheck($member->save(), 'cannot save historical membership');
    $saved = array(
        'group_id' => (int)$group->get('id'),
        'group_name' => (string)$group->get('name'),
        'member_id' => (int)$member->get('id'),
        'user_id' => (int)$member->get('member'),
        'role_id' => (int)$member->get('role'),
        'rank' => (int)$member->get('rank'),
        'membership_count' => oldAclRowCount($modx, $table),
    );
    oldAclCheck($saved['group_id'] > 0 && $saved['member_id'] > 0
        && $saved['role_id'] > 0, 'invalid fixture identifiers');
    oldAclCheck(file_put_contents($manifest, json_encode($saved)) !== false,
        'cannot save fixture');
    echo 'HISTORICAL_ACL_PRE127_FIXTURE_OK platform='
        . ($is2 ? 'modx2' : 'modx3') . PHP_EOL;
    exit(0);
}
oldAclCheck(is_file($manifest), 'no previous release fixture');
$saved = json_decode(file_get_contents($manifest), true);
oldAclCheck(is_array($saved), 'invalid previous release manifest');
$group = $modx->getObject($groupClass, (int)$saved['group_id']);
$member = $modx->getObject($memberClass, (int)$saved['member_id']);
oldAclCheck($group && $member, 'old ACL group/member missing after upgrade');
oldAclCheck((string)$group->get('name') === $saved['group_name'], 'group name modified');
oldAclCheck((int)$member->get('user_group') === (int)$saved['group_id'], 'group id modified');
oldAclCheck((int)$member->get('member') === (int)$saved['user_id'], 'user id modified');
oldAclCheck((int)$member->get('role') === (int)$saved['role_id'], 'role modified');
oldAclCheck((int)$member->get('rank') === (int)$saved['rank'], 'rank modified');
oldAclCheck(oldAclRowCount($modx, $table) === (int)$saved['membership_count'],
    'membership row count modified');
$index = $modx->query('SHOW INDEX FROM ' . $table);
oldAclCheck((bool)$index, 'cannot inspect migrated indexes');
$ready = false;
foreach ($index->fetchAll(PDO::FETCH_ASSOC) as $entry) {
    if ($entry['Column_name'] === 'user_group'
        && (int)$entry['Seq_in_index'] === 1) $ready = true;
}
oldAclCheck($ready, 'ACL membership index absent after explicit migration');
echo 'HISTORICAL_ACL_MIGRATION_DATA_UNCHANGED_OK platform='
    . ($is2 ? 'modx2' : 'modx3')
    . ' preserved_members=' . $saved['membership_count'] . PHP_EOL;
