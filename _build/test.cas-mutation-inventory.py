#!/usr/bin/env python3
# MODX MCP SOURCE / ИСХОДНИК MODX MCP
# RU: Проверка «cas-mutation-inventory»; тестовый код не запускается на production.
# EN: «cas-mutation-inventory» regression test; not executed in production.
"""Fail closed when MODX MCP mutation registry changes without CAS review.

This is an INVENTORY contract, not a claim that untested actions are safe.
"""
from pathlib import Path
import json
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
TOOLS = ROOT / 'core/components/modxmcp/src/Tools'
CATALOG = ROOT / 'core/components/modxmcp/src/Registry/MutationProcessorCatalog.php'
SOURCE = ROOT / 'core/components/modxmcp/src/Tools/StateSupport.php'
MANIFEST = ROOT / '_build/cas-mutation-inventory.json'
catalog = json.loads(MANIFEST.read_text(encoding='utf-8'))
assert catalog['schema_version'] == 1
actual = {}
for path in TOOLS.glob('*Tool.php'):
    code = path.read_text()
    if not re.search(r'function\s+isMutation\s*\(\s*\)\s*\{\s*return\s+true\s*;', code):
        continue
    if path.stem == 'ProcessorMutationTool':
        continue
    match = re.search(r'function\s+name\s*\(\s*\)\s*\{\s*return\s+[\x22\x27]([a-z0-9_]+)[\x22\x27]', code)
    assert match, f'Unclassified action name in {path.name}'
    action = match.group(1)
    assert action not in actual, f'Duplicate action {action}'
    actual[action] = dict(tool=path.stem, origin='static')

pattern = (r'^\s*\x27([a-z0-9_]+)\x27\s*=>\s*array\(\x27group\x27\s*=>\s*\x27([^\x27]+)\x27,'
           r'\s*\x27processor\x27\s*=>\s*\x27([^\x27]+)\x27,'
           r'\s*\x27route\x27\s*=>\s*\x27([^\x27]+)\x27\)')
for match in re.finditer(pattern, CATALOG.read_text(), re.M):
    action, group, processor, route = match.groups()
    assert action not in actual, f'Duplicate dynamic action {action}'
    actual[action] = dict(tool='ProcessorMutationTool', origin='dynamic',
                          route=route, processor=processor)

expected = catalog['actions']
added = sorted(actual.keys() - expected.keys())
removed = sorted(expected.keys() - actual.keys())
assert not added and not removed, (
    f'CAS REVIEW REQUIRED: mutation actions added={added}; removed={removed}. '
    'Classify each action before permitting green CI.'
)
for action, value in actual.items():
    row = expected[action]
    assert all(row.get(k) == v for k, v in value.items()), (
        f'CAS REVIEW REQUIRED: route or implementing tool changed: {action}'
    )
    assert row['cas_boundary'] in (
        'database_transaction', 'conditional_resource_transaction',
        'revision_reserved_before_action'
    ), f'Missing CAS boundary classification: {action}'
    assert row['risk'] in ('critical', 'high', 'review'), action
    assert isinstance(row['verified_scenarios'], list), action
    expected_test = ('full_catalog_trigger_gate_ci'
                     if row['cas_boundary'] == 'revision_reserved_before_action'
                     else 'per_action_rollback_ci' if row['verified_scenarios']
                     else 'not_individually_exercised')
    assert row.get('revision_denial_test') == expected_test, (
        f'CAS test evidence classification mismatched for {action}')
# RU: Для новых индивидуальных доказательств нужно исполняемое CI-свидетельство.
# EN: New per-action evidence must be backed by an actual CI test and marker.
scenario = 'disposable_create_readback_revision_stale_replay'
for action in ('create_role', 'create_user_group', 'create_resource_group'):
    assert scenario in expected[action]['verified_scenarios'], action
fixture_file = ROOT / '_build/test.ci-cas-acl-create-evidence.php'
fixture = fixture_file.read_text(encoding='utf-8')
workflow = (ROOT / '.github/workflows/cas-pr46-isolated.yml').read_text(encoding='utf-8')
assert 'php _build/test.ci-cas-acl-create-evidence.php' in workflow
for action in ('create_role','create_user_group','create_resource_group'):
    assert f"'{action}'" in fixture
    assert "'CAS_CREATE_EVIDENCE_OK action='" in fixture
assert 'stale replay denied' in fixture
assert 'stale replay created no duplicate' in fixture

# RU: Доказываем наличие исполняемых и активированных ACL-сценариев.
# EN: Per-action grant/update claims require active executable integration tests.
acl_scenarios = {
    'grant_context_access': ('context-access', 'grant_readback_stale_replay'),
    'update_context_access': ('context-access', 'update_readback_stale_replay'),
    'grant_resourcegroup_access': ('resourcegroup-access', 'grant_readback_stale_replay'),
    'update_resourcegroup_access': ('resourcegroup-access', 'update_readback_stale_replay'),
}
ci_workflow = (ROOT / '.github/workflows/cas-pr46-isolated.yml').read_text()
for action, (suite, suffix) in acl_scenarios.items():
    scenario_name = 'disposable_' + ('context_acl_' if suite=='context-access'
                                  else 'resourcegroup_acl_') + suffix
    assert scenario_name in expected[action]['verified_scenarios'], action
    filename = '_build/test.ci-acl-'+suite+'.php'
    assert 'php '+filename in ci_workflow
    content = (ROOT / filename).read_text()
    assert "'"+action+"'" in content
    assert 'CAS_EVIDENCE_'+action.upper()+'_READBACK_STALE_REPLAY_OK' in content
    assert 'stale replay did not duplicate grant' in content
    assert 'rejected update preserved final authority' in content

# RU: Revoke подтверждается дополнительным отрицательным replay тестом.
# EN: Revoke evidence must include explicit stale replay and readback checks.
for action,suite,prefix in (
    ('revoke_context_access','context-access','context_acl'),
    ('revoke_resourcegroup_access','resourcegroup-access','resourcegroup_acl'),
):
    assert 'disposable_'+prefix+'_revoke_readback_stale_replay' in expected[action]['verified_scenarios']
    source_name = '_build/test.ci-acl-'+suite+'.php'
    source = (ROOT / source_name).read_text()
    assert 'php '+source_name in ci_workflow
    assert 'CAS_EVIDENCE_'+action.upper()+'_READBACK_STALE_REPLAY_OK' in source
    assert 'revoke stale CAS replay denied' in source
    assert 'rejected revoke did not advance revision' in source

# RU: Новый сценарий create_user обязан присутствовать в настоящей CI-матрице.
# EN: Track the exact inactive-user create/replay evidence, not generic safety.
user_scenario = 'disposable_inactive_user_create_readback_revision_stale_replay_secret_fields'
assert user_scenario in expected['create_user']['verified_scenarios']
user_fixture = (ROOT / '_build/test.ci-cas-acl-create-evidence.php').read_text()
assert 'php _build/test.ci-cas-acl-create-evidence.php' in ci_workflow
assert "'create_user'" in user_fixture
assert "'active'=>0" in user_fixture
assert 'created user remains inactive' in user_fixture
assert 'create_user readback masks ' in user_fixture
assert 'stale replay did not consume revision' in user_fixture
assert 'stale replay created no duplicate' in user_fixture

# RU: Проверенные сценарии политики не являются проверкой выдачи полномочий.
# EN: Unassigned policy lifecycle proves no permissions-granting behavior.
policy_test = (ROOT / '_build/test.ci-cas-policy-evidence.php').read_text()
assert 'php _build/test.ci-cas-policy-evidence.php' in ci_workflow
policy_evidence = {
 'create_access_policy': ('disposable_unassigned_policy_create_readback_revision_stale_replay',
                          'CAS_POLICY_CREATE_EVIDENCE_OK'),
 'update_access_policy': ('disposable_unassigned_policy_description_update_readback_stale_replay',
                          'CAS_POLICY_UPDATE_EVIDENCE_OK'),
 'delete_access_policy': ('disposable_unassigned_policy_delete_readback_stale_replay',
                          'CAS_POLICY_DELETE_EVIDENCE_OK'),
}
for action,(scenario,marker) in policy_evidence.items():
    assert scenario in expected[action]['verified_scenarios']
    assert "'"+action+"'" in policy_test
    assert marker in policy_test
assert 'stale policy update replay denied' in policy_test
assert 'stale policy delete replay denied' in policy_test
assert 'stale replay produced no duplicate policy' in policy_test

# RU: Сценарии шаблонов policy требуют существующей группы и настоящего CI.
# EN: Policy-template evidence must use an existing group and executable CI.
template_test = (ROOT / '_build/test.ci-cas-policy-template-evidence.php').read_text()
assert 'php _build/test.ci-cas-policy-template-evidence.php' in ci_workflow
assert "'template_group'=>$groupId" in template_test
for action,scenario in {
    'create_access_policy_template': 'disposable_grouped_policy_template_create_readback_stale_replay',
    'update_access_policy_template': 'disposable_grouped_policy_template_description_update_readback_stale_replay',
    'delete_access_policy_template': 'disposable_grouped_policy_template_delete_readback_stale_replay',
}.items():
    assert scenario in expected[action]['verified_scenarios']
    assert "'"+action+"'" in template_test
    label=action.split('_')[0].upper()
    assert 'CAS_POLICY_TEMPLATE_'+label+'_EVIDENCE_OK' in template_test
assert 'stale replay produced no duplicate policy' in template_test
assert 'stale policy update replay denied' in template_test
assert 'stale policy delete replay denied' in template_test

# RU: Контекстные CAS-сценарии связаны с запущенной disposable матрицей.
# EN: Bind context lifecycle evidence to exercised disposal-only CI steps.
context_test_name = '_build/test.ci-acl-context-defaults.php'
context_test = (ROOT / context_test_name).read_text()
assert 'php '+context_test_name in ci_workflow
context_cases = {
 'create_context': ('disposable_context_create_readback_stale_replay_no_anonymous',
                    'CAS_CONTEXT_CREATE_READBACK_STALE_REPLAY_OK'),
 'update_context': ('disposable_context_name_update_readback_stale_replay_no_anonymous',
                    'CAS_CONTEXT_UPDATE_READBACK_STALE_REPLAY_OK'),
 'delete_context': ('disposable_context_delete_readback_stale_replay',
                    'CAS_CONTEXT_DELETE_READBACK_STALE_REPLAY_OK'),
 'create_context_setting': ('disposable_context_setting_create_readback_stale_replay',
                            'CAS_CONTEXT_SETTING_CREATE_READBACK_STALE_REPLAY_OK'),
 'update_context_setting': ('disposable_context_setting_update_readback_stale_replay',
                            'CAS_CONTEXT_SETTING_UPDATE_READBACK_STALE_REPLAY_OK'),
 'delete_context_setting': ('disposable_context_setting_delete_readback_stale_replay',
                            'CAS_CONTEXT_SETTING_DELETE_READBACK_STALE_REPLAY_OK'),
}
for action,(scenario,marker) in context_cases.items():
    assert scenario in expected[action]['verified_scenarios']
    assert "'"+action+"'" in context_test
    assert marker in context_test
assert 'stale CAS replay denied' in context_test
assert 'no anonymous access granted by default' in context_test
assert 'updated context name persisted through MCP list' in context_test
assert 'context setting updated via MCP' in context_test
assert 'deleted context absent from MCP context list' in context_test

# RU: Для наборов свойств есть настоящий CI-сценарий, не просто запись в реестре.
# EN: Require executable unassigned property-set evidence, not a bare claim.
property_test = (ROOT / '_build/test.ci-cas-propertyset-evidence.php').read_text()
assert 'php _build/test.ci-cas-propertyset-evidence.php' in ci_workflow
for action,scenario,marker in (
 ('create_property_set','disposable_unassigned_property_set_create_readback_stale_replay','CREATE'),
 ('update_property_set','disposable_unassigned_property_set_description_update_readback_stale_replay','UPDATE'),
 ('delete_property_set','disposable_unassigned_property_set_delete_readback_stale_replay','DELETE'),
):
    assert scenario in expected[action]['verified_scenarios']
    assert "'"+action+"'" in property_test
    assert 'CAS_PROPERTY_SET_'+marker+'_READBACK_STALE_REPLAY_OK' in property_test
assert 'no unexpected property set assignment' in property_test
assert 'stale CAS replay denied' in property_test

# RU: CAS-признаки namespace требуют реального disposable-теста.
# EN: Namespace per-action evidence requires an executable lifecycle suite.
namespace_test = (ROOT / '_build/test.ci-cas-namespace-evidence.php').read_text()
assert 'php _build/test.ci-cas-namespace-evidence.php' in ci_workflow
for action,scenario,verb in (
 ('create_namespace','disposable_namespace_metadata_create_readback_stale_replay','CREATE'),
 ('update_namespace','disposable_namespace_metadata_update_readback_stale_replay','UPDATE'),
 ('delete_namespace','disposable_namespace_metadata_delete_readback_stale_replay','DELETE'),
):
    assert scenario in expected[action]['verified_scenarios']
    assert "'"+action+"'" in namespace_test
    assert 'CAS_NAMESPACE_'+verb+'_READBACK_STALE_REPLAY_OK' in namespace_test
assert 'stale replay did not increment revision' in namespace_test
assert 'namespace removed' in namespace_test

# RU: Индивидуальный контракт media source без реальных файловых операций.
# EN: Verify metadata-only source lifecycle CI evidence, not filesystem safety.
media_test = (ROOT / '_build/test.ci-cas-media-source-evidence.php').read_text()
assert 'php _build/test.ci-cas-media-source-evidence.php' in ci_workflow
for action,scenario,verb in (
 ('create_media_source','disposable_media_source_definition_create_readback_stale_replay','CREATE'),
 ('update_media_source','disposable_media_source_definition_description_update_readback_stale_replay','UPDATE'),
 ('delete_media_source','disposable_media_source_definition_delete_readback_stale_replay','DELETE'),
):
    assert scenario in expected[action]['verified_scenarios']
    assert "'"+action+"'" in media_test
    assert 'CAS_MEDIA_SOURCE_'+verb+'_READBACK_STALE_REPLAY_OK' in media_test
assert "'/home/runner/'" in media_test
assert 'stale replay did not increment revision' in media_test

# RU: Назначение property set только своему одноразовому чанку.
# EN: Bind per-action evidence to disposable element assignment CI.
assign_test = (ROOT / '_build/test.ci-cas-propertyset-assignment.php').read_text()
assert 'php _build/test.ci-cas-propertyset-assignment.php' in ci_workflow
for action,scenario,marker in (
 ('assign_property_set','disposable_chunk_property_set_assign_readback_stale_replay','ASSIGN'),
 ('unassign_property_set','disposable_chunk_property_set_unassign_readback_stale_replay','UNASSIGN'),
):
    assert scenario in expected[action]['verified_scenarios']
    assert "'"+action+"'" in assign_test
    assert 'CAS_PROPERTY_SET_'+marker+'_READBACK_STALE_REPLAY_OK' in assign_test
assert "'type'=>'chunk'" in assign_test
assert 'stale replay did not advance revision' in assign_test
assert 'stale assignment produced no duplicate' in assign_test

# RU: Критические CRUD группы ресурсов без ACL/членства.
# EN: Require executable unassigned resource-group rename/delete CAS evidence.
resourcegroup_test = (ROOT / '_build/test.ci-cas-resourcegroup-update-delete.php').read_text()
assert 'php _build/test.ci-cas-resourcegroup-update-delete.php' in ci_workflow
for action,scenario,marker in (
 ('update_resource_group','disposable_unassigned_resource_group_rename_readback_stale_replay','UPDATE'),
 ('delete_resource_group','disposable_unassigned_resource_group_delete_readback_stale_replay','DELETE'),
):
    assert scenario in expected[action]['verified_scenarios']
    assert "'"+action+"'" in resourcegroup_test
    assert 'CAS_RESOURCE_GROUP_'+marker+'_READBACK_STALE_REPLAY_OK' in resourcegroup_test
assert 'stale CAS did not advance revision' in resourcegroup_test
assert 'resource group rename persisted' in resourcegroup_test

# RU: Для lexicon set/revert нужен проверенный disposable-цикл MCP.
# EN: Track only observed lexicon set/revert through isolated MCP readbacks.
lexicon_evidence = (ROOT / '_build/test.ci-cas-lexicon-evidence.php').read_text()
lexicon_reader = (ROOT / '_build/test.ci-cas-lexicon-readback.php').read_text()
assert 'php _build/test.ci-cas-lexicon-evidence.php' in ci_workflow
for action,scenario,verb in (
 ('set_lexicon_entry','disposable_namespace_lexicon_set_readback_stale_replay','SET'),
 ('revert_lexicon_entry','disposable_namespace_lexicon_revert_readback_stale_replay','REVERT'),
):
    assert scenario in expected[action]['verified_scenarios']
    assert "'"+action+"'" in lexicon_evidence
    assert 'CAS_LEXICON_'+verb+'_READBACK_STALE_REPLAY_OK' in lexicon_evidence
assert 'test.ci-cas-lexicon-readback.php' in lexicon_evidence
assert "processRequest('list_lexicon_entries'" in lexicon_reader
assert 'stale replay consumed no revision' in lexicon_evidence

# RU: Member CAS подтверждается только на временных пользователе/группе/ролях.
# EN: Guard individual member evidence with executed disposable CRUD steps.
member_test=(ROOT / '_build/test.ci-functional-acceptance.php').read_text()
assert 'php _build/test.ci-functional-acceptance.php' in ci_workflow
for action,scenario,marker in (
 ('add_user_to_group','disposable_inactive_user_group_membership_add_readback_stale_replay','ADD'),
 ('update_group_member','disposable_inactive_user_group_role_update_readback_stale_replay','UPDATE'),
 ('remove_user_from_group','disposable_inactive_user_group_membership_remove_readback_stale_replay','REMOVE'),
):
    assert scenario in expected[action]['verified_scenarios']
    assert "'"+action+"'" in member_test
    assert 'CAS_USER_GROUP_'+marker+'_MEMBER_READBACK_STALE_REPLAY_OK' in member_test
assert "'active'=>0" in member_test or "'active'=>0," in member_test
assert 'member role stale CAS denied' in member_test
assert 'member removal stale CAS denied' in member_test

# RU: Только одноразовые группы/ресурсы; формат ID проверяется MODX2/3 CI.
# EN: Confirm real disposable resource-group link and replay on both core APIs.
resource_link_test=(ROOT / '_build/test.ci-cas-resource-group-membership.php').read_text()
assert 'php _build/test.ci-cas-resource-group-membership.php' in ci_workflow
for action,scenario,marker in (
 ('assign_resource_to_group','disposable_unpublished_resource_group_assign_readback_stale_replay','ASSIGN'),
 ('remove_resource_from_group','disposable_unpublished_resource_group_remove_readback_stale_replay','REMOVE'),
):
    assert scenario in expected[action]['verified_scenarios']
    assert "'"+action+"'" in resource_link_test
    assert 'CAS_RESOURCE_GROUP_'+marker+'_RESOURCE_STALE_READBACK_OK' in resource_link_test
assert 'list_resource_group_resources' in resource_link_test
assert 'stale replay did not consume site revision' in resource_link_test
assert "'published'=>0" in resource_link_test

# RU: ClientConfig подтверждён только на установке addon при PHP 8.2.
# EN: Do not imply all 12 jobs exercised real ClientConfig 2.5.0 addon.
clientconfig_addon_test=(ROOT / '_build/test.ci-clientconfig-installed.php').read_text()
assert 'php _build/test.ci-clientconfig-installed.php' in ci_workflow
assert "if: matrix.php_version == '8.2'" in ci_workflow
for action,scenario,marker in (
 ('clientconfig_create_setting','pinned_clientconfig25_php82_create_readback_stale_replay','CREATE'),
 ('clientconfig_update_setting','pinned_clientconfig25_php82_update_readback_stale_replay','UPDATE'),
 ('clientconfig_delete_setting','pinned_clientconfig25_php82_delete_readback_stale_replay','DELETE'),
):
    assert scenario in expected[action]['verified_scenarios']
    assert "'"+action+"'" in clientconfig_addon_test
    assert 'CAS_CLIENTCONFIG_'+marker+'_READBACK_STALE_REPLAY_OK' in clientconfig_addon_test
assert 'stale replay consumed no CAS revision' in clientconfig_addon_test
assert 'clientconfig.context_aware' in clientconfig_addon_test

# RU: Восстановление собственной страницы подтверждено на MODX 2/3.
# EN: Undelete inventory requires executable isolated MCP lifecycle tests.
undelete_test=(ROOT / '_build/test.ci-cas-undelete-resource.php').read_text()
assert 'php _build/test.ci-cas-undelete-resource.php' in ci_workflow
assert 'disposable_unpublished_resource_undelete_readback_stale_replay' in expected['undelete_resource']['verified_scenarios']
assert "'undelete_resource'" in undelete_test
assert 'CAS_UNDELETE_RESOURCE_READBACK_STALE_REPLAY_OK' in undelete_test
assert 'restored resource remains unpublished' in undelete_test
assert 'stale undelete replay denied' in undelete_test

# RU: Дубликат чанка доказывает только chunk, не plugin/snippet/TV.
# EN: Per-action duplicate proof is limited to a non-executable chunk fixture.
duplicate_test=(ROOT / '_build/test.ci-cas-duplicate-elements.php').read_text()
assert 'php _build/test.ci-cas-duplicate-elements.php' in ci_workflow
assert 'disposable_chunk_duplicate_independent_readback_stale_replay' in expected['duplicate_element']['verified_scenarios']
assert 'CAS_DUPLICATE_ELEMENT_CHUNK_READBACK_STALE_REPLAY_OK' in duplicate_test
assert "'duplicate_element'" in duplicate_test
assert "'type'=>'chunk'" in duplicate_test
assert 'old CAS consumed no revision' in duplicate_test

# RU: Переупорядочение подтверждается чтением двух независимых ресурсов.
# EN: Per-action reorder evidence requires executable disposable MCP tests.
reorder_test=(ROOT / '_build/test.ci-cas-reorder-resources.php').read_text()
assert 'php _build/test.ci-cas-reorder-resources.php' in ci_workflow
assert 'disposable_two_resource_menuindex_reorder_readback_stale_replay' in expected['reorder_resources']['verified_scenarios']
assert "'reorder_resources'" in reorder_test
assert 'CAS_REORDER_RESOURCES_READBACK_STALE_REPLAY_OK' in reorder_test
assert 'reorder per-item success' in reorder_test
assert 'stale reorder replay rejected' in reorder_test
assert 'reorder did not publish fixture' in reorder_test

# RU: URI CAS подтверждён на одноразовом MODX.
# EN: Require executable URI refresh evidence in the CI matrix.
uri_test=(ROOT / '_build/test.ci-cas-refresh-uris.php').read_text()
assert 'php _build/test.ci-cas-refresh-uris.php' in ci_workflow
assert 'disposable_unpublished_resource_refresh_uris_readback_stale_replay' in expected['refresh_uris']['verified_scenarios']
assert 'CAS_REFRESH_URIS_READBACK_STALE_REPLAY_OK' in uri_test
assert 'stale URI refresh replay denied' in uri_test

# RU: Копирование ресурсa только изолированным CI.
# EN: Resource duplication evidence must have executable MCP readback tests.
resource_copy_test=(ROOT / '_build/test.ci-cas-duplicate-resource.php').read_text()
assert 'php _build/test.ci-cas-duplicate-resource.php' in ci_workflow
assert 'disposable_unpublished_resource_copy_readback_stale_replay' in expected['duplicate_resource']['verified_scenarios']
assert 'CAS_DUPLICATE_RESOURCE_READBACK_STALE_REPLAY_OK' in resource_copy_test
assert 'stale duplicate replay denied' in resource_copy_test
assert 'stale replay did not duplicate resource twice' in resource_copy_test

# RU: Очистка TV допускает только одну изолированную фикстуру.
# EN: Require disposable clear-value test with dry-run and stale CAS check.
tv_clear_test=(ROOT / '_build/test.ci-cas-clear-tv-values.php').read_text()
assert 'php _build/test.ci-cas-clear-tv-values.php' in ci_workflow
assert 'disposable_single_tv_value_preview_confirm_readback_stale_replay' in expected['clear_tv_values']['verified_scenarios']
assert 'CAS_CLEAR_TV_VALUES_READBACK_STALE_REPLAY_OK' in tv_clear_test
assert 'dry-run did not remove row' in tv_clear_test
assert 'exactly one row removed' in tv_clear_test
assert 'stale TV clear replay denied' in tv_clear_test

# RU: Замена строк чанка имеет независимое CAS-доказательство.
# EN: Editable chunk line mutation must have executable CAS and expect tests.
line_test=(ROOT / '_build/test.ci-cas-edit-element-lines.php').read_text()
assert 'php _build/test.ci-cas-edit-element-lines.php' in ci_workflow
assert 'disposable_chunk_exact_line_edit_readback_expect_guard_stale_replay' in expected['edit_element_lines']['verified_scenarios']
assert 'CAS_EDIT_ELEMENT_LINES_READBACK_STALE_REPLAY_OK' in line_test
assert 'stale line edit replay denied' in line_test
assert 'mismatched expected source denied' in line_test
assert 'denied edit left content unchanged' in line_test

# RU: Массовая мутация доказана лишь для двух одноразовых ресурсов.
# EN: Bulk publish proof requires isolated two-resource dry-run and replay.
bulk_test=(ROOT / '_build/test.ci-cas-bulk-resources.php').read_text()
assert 'php _build/test.ci-cas-bulk-resources.php' in ci_workflow
assert 'disposable_two_resource_bulk_publish_preview_readback_stale_replay' in expected['bulk_resources']['verified_scenarios']
assert 'CAS_BULK_RESOURCES_READBACK_STALE_REPLAY_OK' in bulk_test
assert 'dry-run preserved unpublished state' in bulk_test
assert 'published state independently confirmed' in bulk_test
assert 'stale bulk action denied' in bulk_test

# RU: Массовая замена проверяется только на уникальном тестовом маркере.
# EN: Replace-across coverage needs an executable isolated MCP readback.
replace_test=(ROOT / '_build/test.ci-cas-replace-across.php').read_text()
assert 'php _build/test.ci-cas-replace-across.php' in ci_workflow
assert 'disposable_unique_chunk_replace_across_dry_run_readback_stale_replay' in expected['replace_across']['verified_scenarios']
assert 'CAS_REPLACE_ACROSS_READBACK_STALE_REPLAY_OK' in replace_test
assert 'dry-run did not modify content' in replace_test
assert 'stale replace-across replay denied' in replace_test

# RU: make_static подтверждён только на собственном TV в disposable MODX 2/3.
# EN: The static-file CAS evidence is limited to a disposable TV conversion.
static_tv_test=(ROOT / '_build/test.ci-static-tv-fields.php').read_text()
assert 'php _build/test.ci-static-tv-fields.php' in ci_workflow
assert 'disposable_static_tv_file_bytes_readback_revision_stale_replay' in expected['make_static']['verified_scenarios']
assert "'make_static',$staticRequest" in static_tv_test
assert 'CAS_MAKE_STATIC_TV_READBACK_STALE_REPLAY_OK' in static_tv_test
assert 'stale make_static replay preserved static file bytes' in static_tv_test
assert 'make_static consumes exactly one site revision' in static_tv_test

# RU: Очистка кэша подтверждена на одноразовом нативном cache key.
# EN: Track only a disposable key removal and stale replay, not all partitions.
clear_cache_test=(ROOT / '_build/test.ci-cas-clear-cache.php').read_text()
assert 'php _build/test.ci-cas-clear-cache.php' in ci_workflow
assert 'disposable_native_cache_key_clear_readback_revision_stale_replay' in expected['clear_cache']['verified_scenarios']
assert "'clear_cache',$request" in clear_cache_test
assert 'CAS_CLEAR_CACHE_DISPOSABLE_KEY_STALE_REPLAY_OK' in clear_cache_test
assert 'disposable cache key removed by MCP clear' in clear_cache_test
assert 'stale clear_cache CAS replay denied' in clear_cache_test

# RU: Подтверждение flush_permissions требует штатного результата MODX.
# EN: Permission-cache flush evidence is limited to native successful flush.
flush_test=(ROOT / '_build/test.ci-cas-flush-permissions.php').read_text()
assert 'php _build/test.ci-cas-flush-permissions.php' in ci_workflow
assert 'disposable_native_permission_cache_flush_success_revision_stale_replay' in expected['flush_permissions']['verified_scenarios']
assert "'flush_permissions',$request" in flush_test
assert 'CAS_FLUSH_PERMISSIONS_NATIVE_STALE_REPLAY_OK' in flush_test
assert 'native MODX permissions cache manager confirms successful flush' in flush_test
assert 'flush_permissions old CAS denied' in flush_test

# RU: Прямой native seed допустим только для одноразовой фикстуры CI,
# RU: сама мутация свойства выполняется исключительно через MCP.
# EN: Native seeding is limited to the isolated test fixture only.
element_property_test=(ROOT / '_build/test.ci-cas-element-property.php').read_text()
assert 'php _build/test.ci-cas-element-property.php' in ci_workflow
assert 'disposable_chunk_single_property_expected_value_and_site_revision_cas' in expected['update_element_property']['verified_scenarios']
assert "'update_element_property',$request" in element_property_test
assert 'CAS_ELEMENT_PROPERTY_UPDATE_READBACK_STALE_REPLAY_OK' in element_property_test
assert 'unrelated property preserved' in element_property_test
assert 'per-property expected_value mismatch rejected' in element_property_test

# RU: delete_provider через MCP; создание фикстуры только внутри CI.
# EN: Deletion tested via MCP; fixture provider is natively seeded in CI only.
provider_delete_test=(ROOT / '_build/test.ci-cas-delete-provider.php').read_text()
assert 'php _build/test.ci-cas-delete-provider.php' in ci_workflow
assert 'disposable_unassigned_provider_native_seed_delete_readback_stale_replay' in expected['delete_provider']['verified_scenarios']
assert "'delete_provider',$request" in provider_delete_test
assert 'CAS_DELETE_PROVIDER_DISPOSABLE_STALE_REPLAY_OK' in provider_delete_test
assert 'provider no longer appears in MCP list' in provider_delete_test
assert 'stale delete_provider replay rejected' in provider_delete_test

# Detect accidental mismatch between runtime's ATOMIC policy and reviewed inventory.
state = SOURCE.read_text()
chunk = state.split('public static function supportsAtomicRevision', 1)[1]
chunk = chunk.split('return false;', 1)[0]
supported = set(re.findall(r"'([a-z_]+)'", chunk.split('return true;', 1)[0]))
database = {a for a,row in expected.items()
            if row['cas_boundary']=='database_transaction'}
assert supported == database, (
    f'CAS atomic runtime policy does not match inventory! '
    f'code-only={sorted(supported-database)}; '
    f'manifest-only={sorted(database-supported)}'
)
conditional = {a for a,row in expected.items()
               if row['cas_boundary']=='conditional_resource_transaction'}
assert conditional == {'create_element','update_element','delete_element'}
assert '&& empty($data[\'static\'])' in chunk
assert '&& empty($data[\'static_file\'])' in chunk

high_impact = {'install_package','uninstall_package','run_processor','regenerate_token',
               'empty_recycle_bin','create_user','delete_user'}
assert all(a in expected and expected[a]['risk']=='critical' for a in high_impact)
assert all(expected[a]['cas_boundary']=='revision_reserved_before_action'
           for a in high_impact)

# RU: Публикуемый отчёт не должен отставать от машинного реестра.
# EN: Keep the human-readable evidence tracker identical to the manifest.
import subprocess
tracker = subprocess.check_output(
    [sys.executable,str(ROOT / '_build/render.cas-evidence-tracker.py')],
    text=True
)
assert (ROOT / 'docs/CAS-EVIDENCE-TRACKER-1.2.8.ru.md').read_text() == tracker, (
    'CAS evidence tracker is stale; regenerate using render.cas-evidence-tracker.py'
)

unverified = [a for a,row in expected.items() if not row['verified_scenarios']]
print(f'CAS_INVENTORY_COVERAGE_OK total={len(expected)} '
      f'static={sum(x["origin"]=="static" for x in expected.values())} '
      f'dynamic={sum(x["origin"]=="dynamic" for x in expected.values())} '
      f'atomic={len(database)} conditional={len(conditional)} '
      f'external={sum(x["cas_boundary"]=="revision_reserved_before_action" for x in expected.values())}')
print(f'CAS_INVENTORY_UNVERIFIED_ACTIONS={len(unverified)} '
      f'(NOT PROVEN SAFE): {", ".join(unverified[:12])} ...')
