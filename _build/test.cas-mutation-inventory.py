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
