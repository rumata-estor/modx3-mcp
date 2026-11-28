#!/usr/bin/env python3
# MODX MCP SOURCE / ИСХОДНИК MODX MCP
# RU: Проверка «cas-client-contract»; тестовый код не запускается на production.
# EN: «cas-client-contract» regression test; not executed in production.
"""Ensure MCP tool-list schemas include strict CAS on all mutable actions."""
import json
from pathlib import Path
import re

ROOT=Path(__file__).resolve().parents[1]
manifest=json.loads((ROOT/'_build/cas-mutation-inventory.json').read_text())
client=(ROOT/'client/index.js').read_text()
start='const CAS_MUTATION_ACTIONS = new Set(['
assert client.count(start)==1
body=client.split(start,1)[1].split(']);',1)[0]
names=re.findall(r'"([a-z0-9_]+)"',body)
assert len(names)==len(set(names)), 'duplicate mutation actions in client'
assert set(names)==set(manifest['actions']), (
    f'CAS client/server mutation mismatch: '
    f'client_only={sorted(set(names)-set(manifest["actions"]))}; '
    f'server_only={sorted(set(manifest["actions"])-set(names))}')
checks=(
    'schema.required = [...new Set([...(schema.required || []), "_runtime_preconditions"])]',
    'schema.properties = {',
    'observed_site_revision: { type: "integer", minimum: 0 }',
    'strict_site_revision: { type: "boolean", const: true }',
    'read_set: { type: "array"',
    'CAS_MUTATION_ACTIONS.has(name.replace(/^modx_/, ""))',
    'required: [...CAS_PRECONDITIONS_SCHEMA.required, "expected_tvs"]',
    'throw new Error("CAS_REQUIRED:',
)
for check in checks:
    assert check in client, f'CAS client schema safety contract missing: {check}'
for rel in ('core/components/modxmcp/src/Core/Connector.php',):
    code=(ROOT/rel).read_text()
    assert 'StateSupport::requireMutationPreconditions' in code
    assert "'CAS_RUNTIME_REQUIRED:" in code
print('CAS_CLIENT_SERVER_MANDATORY_SCHEMA_PARITY_OK actions='+str(len(names)))
