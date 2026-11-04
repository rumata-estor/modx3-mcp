# Nonstatic chunk CAS: go/no-go for MODX 2 and MODX 3

**Status: NO-GO for live mutations.** Source-only test primitives are not an API feature. This note intentionally avoids approving any deployment or changing either test site.

## What is actually proven

- Signed CAS-permit v2 wire, site/audience/body/baseline hashes, nonce replay denial and cross-language Unicode test vectors pass PHP GitHub CI on the development branch.
- `ModxMcpChunkCas::compareAndSwap()` emits one conditional SQL UPDATE for a nonstatic chunk, comparing `snippet` bytes under `BINARY`, with an exact positive ID and a one-row-result requirement. Mock SQL tests pass. No disposable real MySQL/MariaDB engine verification has yet been recorded.
- A separate SiteAgent Executor gateway is active in `deny-only` mode; a root-mediated six-role Unix-socket matrix passed. Both CMS-side and client-side protected writes are still blocked.

## What remains unsafe

1. A bare SQL UPDATE bypasses `modChunk::save()` events (`OnChunkBeforeSave`, `OnChunkSave`) and the manager update processor's `OnChunkFormSave` event. Plugins may depend on these paths or modify the chunk before saving. Manually fabricating events is not equivalent to running processors.
2. Existing `ElementUpdateTool` invokes a MODX update processor inside a transaction, then optionally converts to static storage with `modxmcp.auto_static`, refreshes cache, updates revision and audit. Direct SQL CAS bypasses that workflow. An independently verified `auto_static=false` setting is necessary but not sufficient.
3. An external file lock (`flock`) does not prevent writes from MODX Manager, another plugin, or a different PHP process. A plain read before `runProcessor` is not a CAS. `SELECT ... FOR UPDATE` plus processor invocation is only a candidate approach: first verify transaction nesting, connection identity, plugins and rollback semantics separately on MODX 2.8.x and 3.x.
4. Current nonce-ledger file `fsync` does not prove directory-entry durability after an abrupt host crash. Test restart/power-failure and establish a durable reservation strategy.
5. Agent still has legacy unrestricted token access on both test sites (`ready=false` from root effective-UID audit). Restricting writes in one HTTP endpoint does not close SSH/DB, filesystem or legacy API bypass routes.

## Release gate before a single disposable test write

Use disposable nonstatic chunks on isolated MODX 2 and MODX 3 databases, no production sites. Demonstrate case- and whitespace-sensitive CAS against the actual table/collation, concurrent manager/process updates, plugin events, revision/audit/cache correctness, failure rollback, and nonce persistence. Validate both original and resulting `snippet` values independently; repeat with `modxmcp.auto_static` enabled and require denial before permit consumption. Audit all write routes and remove ordinary Agent access to unrestricted credentials only under a separately authorized, reversible cutover. The entire gate must pass before wiring `chunk-cas-pilot.php` into a live endpoint or removing either CAS interlock.

Sources: `core/components/modxmcp/src/Tools/ElementUpdateTool.php`, `ElementMutationSupport.php`, MODX documentation for `modChunk::save`, `OnChunkFormSave`, and cache manager refresh behavior.
