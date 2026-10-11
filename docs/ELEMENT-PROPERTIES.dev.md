# Element default properties: unreleased development capability

Purpose: update a **single existing default property** of a MODX snippet,
chunk, plugin or template, without rewriting its source code.

MCP command: modx_update_element_property

Required payload: type, id (or name), property_name, expected_value, value.
Expected value is compared with the current property under the site mutation
lock. Mismatches fail with STALE_STATE. Existing property definitions, all
other properties and PHP code are retained. Standard MODX update processors
execute form-save events and permissions, and the result is read back before
the operation returns success.

Client backs up the full element before mutations. Site Agent Runtime adds
modx-safe update-element-property, which first creates its own backup,
verifies the expected previous value, calls the MCP and reads the new value.
Use its --expected-value-file and --value-file arguments; do not include
secrets in command-line arguments. Restore only the changed property from
the snapshot with the same CAS operation in the opposite direction.

This operation edits element *default* properties, not named Property Sets
or parameters supplied in individual snippet calls.

Until installed and tested on a disposable/test MODX instance, this is
source code under development, not an available feature or stable release.
