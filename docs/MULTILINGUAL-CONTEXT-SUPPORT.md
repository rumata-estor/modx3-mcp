# Multilingual context support — development backlog

Scope: MODX MCP support for managing multilingual sites in one MODX installation. This branch is not a release or deployment. Existing security controls and uncertain-mutation handling must be preserved.

## Resource lifecycle

- [ ] Verify create/update of resources in a selected context on MODX 2 and 3, with explicit parent, publication state and read-back.
- [ ] Verify the existing richtext, searchable and cacheable support end to end, including the client contract. Extend the client/server contract for URI override and content type where missing. Validate allowed fields and values; never silently discard requested resource properties.
- [ ] Support duplicating resources into an explicit target context and parent without altering the source. Test TV values, templates, descendants, URI collisions, unpublished defaults and source-to-target ID mapping. Preserve authorization and mutation outcome handling.
- [ ] Verify context CRUD and context-setting CRUD, including isolation between contexts and appropriate failure behavior.

## ClientConfig

- [ ] Verify context-specific list/get/update, including empty overrides, unchanged base values, and isolation of other contexts.
- [ ] Verify rejection of nonexistent context keys before any write. Preserve the existing payload validation and uncertain-outcome contract; do not promise rollback where atomicity has not been established.

## Integration

- [ ] Coordinate the safe Site Agent routes in the matching feature branch of site-agent-runtime.
- [ ] Run isolated MODX 2/3 integration tests and security regression tests before packaging. Reconcile the current security/release line before any merge or deployment.

Translation content and Babel translation relationships are editorial decisions outside this automation scope.

## First implementation: explicit-context resource creation

- Added the missing create/update input-schema fields, server URI/content type forwarding and validation, and a server-issued `resource_context_fields_v1` contract in site state.
- Resource create no longer resolves the new title into an existing resource ID. Site Agent checks the exact destination and verifies the result by its returned ID.
- Offline validation passes. Real MODX 2/3 integration (native processors, fixed URI/content type, cross-context same title, partial update and failure paths) is a separate CI release gate.
- Cross-context duplication and generic name-based lookup disambiguation remain open. No release or installation is implied by this branch.
