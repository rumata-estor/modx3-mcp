# Nonstatic chunk CAS: go/no-go for MODX 2 and MODX 3

**Status: NO-GO for live mutations.** Source-only test primitives are not an API feature. This note intentionally avoids approving any deployment or changing either test site.

## What is actually proven

- Signed CAS-permit v2 wire, site/audience/body/baseline hashes, nonce replay denial and cross-language Unicode test vectors pass PHP GitHub CI on the development branch.
- `ModxMcpChunkCas::compareAndSwap()` emits one conditional SQL UPDATE for a nonstatic chunk, comparing `snippet` bytes under `BINARY`, with an exact positive ID and a one-row-result requirement. Mock SQL tests and real MariaDB/InnoDB tests now pass on disposable official MODX 2.8.9 and MODX 3.2.4 CI installations (`673da83`, run `41857072331`). One concurrent writer wins and the stale writer is rejected.
- A separate SiteAgent Executor gateway is active in `deny-only` mode; a root-mediated six-role Unix-socket matrix passed. Both CMS-side and client-side protected writes are still blocked.

## What remains unsafe

1. A bare SQL UPDATE bypasses `modChunk::save()` events (`OnChunkBeforeSave`, `OnChunkSave`) and the manager update processor's `OnChunkFormSave` event. Plugins may depend on these paths or modify the chunk before saving. Manually fabricating events is not equivalent to running processors.
2. Existing `ElementUpdateTool` invokes a MODX update processor inside a transaction, then optionally converts to static storage with `modxmcp.auto_static`, refreshes cache, updates revision and audit. Direct SQL CAS bypasses that workflow. An independently verified `auto_static=false` setting is necessary but not sufficient.
3. An external file lock (`flock`) does not prevent writes from MODX Manager, another plugin, or a different PHP process. A plain read before `runProcessor` is not a CAS. `SELECT ... FOR UPDATE` plus processor invocation is only a candidate approach: first verify transaction nesting, connection identity, plugins and rollback semantics separately on MODX 2.8.x and 3.x.
4. The original file nonce ledger does not prove directory-entry durability after a power failure. A new *unconnected* InnoDB nonce ledger was tested in CI on both platforms (`0433d48`, run `41860416172`); provisioning, backup/restart and operational isolation are still unverified on either live test host.
5. Agent still has legacy unrestricted token access on both test sites (`ready=false` from root effective-UID audit). Restricting writes in one HTTP endpoint does not close SSH/DB, filesystem or legacy API bypass routes.

## November 4, 2026: incremental executable-source results (not a live release)

- A Controller v2 CAS one-use issuer and Executor verifier/readback flow now pass offline tests, including concurrency, frozen-HTTP byte matching, MAC claim shape, stale rejection, unknown outcomes and replay denial (`site-agent-runtime` commits `d947cd7`, `717bc94`; both CI successful). The actual service remains deny-only. These tests simulate the MODX HTTP write.
- `tests/ci-modx-chunk-processor-lock.php` proves in throwaway MODX 2/3 that an actual chunk update processor can run while an InnoDB row lock is held and its mutation rolls back in the same xPDO transaction (`4006b7d`, run `41859842295`, both jobs successful).
- A NEW source-only `chunk-cas-processor-v2.php` joins a signed baseline permit, committed InnoDB nonce, row-lock, normal chunk update processor, exact postcondition, transactional site revision and InnoDB audit, then cache refresh. It is NOT registered as an HTTP route. `tests/ci-protected-cas-processor-v2.php` checks an isolated CMS fixture. Passing this CI fixture would not by itself prove plugin-side-effect safety, complete cache invalidation, or a live HTTP path.
- The first integrated run `41861603739` revealed that MODX core cache `refresh()` returned `false` on both throwaway hosts *after* the database transaction had committed. The code marks this outcome unknown, never retries the permit, and the newer CI fixture separately verifies the committed row/audit/revision. This is a distinct cache-invalidation investigation, NOT a successful full end-to-end test.
- The v2 permit issuer is enabled only by an offline-test constructor argument. The real SiteAgent Controller and remote Executor remain default-deny; existing MODX HTTP endpoint still rejects protected writes.

## Release gate before a single disposable test write

Use disposable nonstatic chunks on isolated MODX 2 and MODX 3 databases, no production sites. Demonstrate case- and whitespace-sensitive CAS against the actual table/collation, concurrent manager/process updates, plugin events, revision/audit/cache correctness, failure rollback, and nonce persistence. Validate both original and resulting `snippet` values independently; repeat with `modxmcp.auto_static` enabled and require denial before permit consumption. Audit all write routes and remove ordinary Agent access to unrestricted credentials only under a separately authorized, reversible cutover. The entire gate must pass before wiring `chunk-cas-pilot.php` into a live endpoint or removing either CAS interlock.

Sources: `core/components/modxmcp/src/Tools/ElementUpdateTool.php`, `ElementMutationSupport.php`, MODX documentation for `modChunk::save`, `OnChunkFormSave`, and cache manager refresh behavior.
