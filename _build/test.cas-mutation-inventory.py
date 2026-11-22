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
for action in ('create_role', 'create_user_group'):
    assert scenario in expected[action]['verified_scenarios'], action
fixture_file = ROOT / '_build/test.ci-cas-acl-create-evidence.php'
fixture = fixture_file.read_text(encoding='utf-8')
workflow = (ROOT / '.github/workflows/cas-pr46-isolated.yml').read_text(encoding='utf-8')
assert 'php _build/test.ci-cas-acl-create-evidence.php' in workflow
for action in ('create_role','create_user_group'):
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

unverified = [a for a,row in expected.items() if not row['verified_scenarios']]
print(f'CAS_INVENTORY_COVERAGE_OK total={len(expected)} '
      f'static={sum(x["origin"]=="static" for x in expected.values())} '
      f'dynamic={sum(x["origin"]=="dynamic" for x in expected.values())} '
      f'atomic={len(database)} conditional={len(conditional)} '
      f'external={sum(x["cas_boundary"]=="revision_reserved_before_action" for x in expected.values())}')
print(f'CAS_INVENTORY_UNVERIFIED_ACTIONS={len(unverified)} '
      f'(NOT PROVEN SAFE): {", ".join(unverified[:12])} ...')
