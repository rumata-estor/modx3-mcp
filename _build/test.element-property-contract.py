from pathlib import Path

root = Path(__file__).resolve().parents[1]
tool = (root/'core/components/modxmcp/src/Tools/ElementPropertyUpdateTool.php').read_text()
client = (root/'client/index.js').read_text()
runtime = (root/'core/components/modxmcp/src/Core/Runtime.php').read_text()
bridges = [
    root/'core/components/modxmcp/model/modxmcp.class.php',
    root/'core/components/modxmcp/legacy/modx2/modxmcp.class.php',
]
for needle in (
    "function name() { return 'update_element_property'; }",
    "array_key_exists('expected_value', $data)",
    "StateSupport::assertPreconditions",
    "STALE_STATE: property value changed since read",
    "ElementMutationSupport::filterData",
    "$update['properties'] = $properties",
    "ElementMutationSupport::transaction",
    "Property verification mismatch",
):
    assert needle in tool, needle
for needle in (
    'name: "modx_update_element_property"',
    'autoBackupElement(args.type, args, name)',
    'action: "update_element_property"',
):
    assert needle in client, needle
assert 'new ElementPropertyUpdateTool()' in runtime
for bridge in bridges:
    t=bridge.read_text()
    assert "'update_element_property' => array('element' => true)" in t
    assert "'update_element_property'" in t[t.index('$elementBridgeActions = array('):t.index('$runtimePreconditions = null;')]
print('ELEMENT_PROPERTY_CONTRACT_OK')
