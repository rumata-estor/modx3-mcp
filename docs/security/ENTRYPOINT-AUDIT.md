# MODX MCP — verified entrypoint and privilege map (source audit)

Status: source inspection only, 2026-11-03. No running website, installation or live permissions tested. This is not a claim of exploitability or a completed security patch.

## Confirmed entrypoints and trust boundary

| Route | Observed authorization / execution | P0 gap | Required negative test |
| --- | --- | --- | --- |
| REST `endpoint/api.common.php` | Reads `modxmcp.api_token` and compares the single `x-mcp-token` using `hash_equals`; checks configured IPs and request size | API bearer authentication does not identify the human-approved task, object or one-use operation | With a valid site token but without an independent permit, a mutation must be refused |
| `model/modxmcp.class.php::processRequest` | Resolves configured service user; with default ID 0 selects the first active `sudo` user. Explicit service user is also required to be `sudo` | A broad MODX account is used for processor execution and cannot by itself enforce per-operation human scope | A restricted service account must execute allowed operations but be unable to escalate or mutate protected settings |
| Modular registry dispatch | Migrated tools dispatch via `ModxMcp\\Core\\Runtime`; mutation tools run under `StateSupport::withMutationLock` and optional preconditions | Lock/CAS does not independently authenticate approval; must be checked at actual write | Rejected, expired or consumed permit must block a modular mutation even when the bearer token and state precondition are valid |
| Legacy action registry / element processor fallback | `processRequest` dispatches non-migrated tools to legacy paths; modular construction exceptions leave `modularRuntime=null` | Comment explicitly states fail-open fallback. An unavailable security gate must not silently switch to less protected writes | With modular runtime unavailable, every protected mutation must remain denied |
| Raw processor capability | `run_processor` appears in the legacy registry; hardening backlog records its default-off setting | All equivalent dynamic processor routes and add-on processors require evaluation, not only the named action | A general processor must not invoke a mutation that bypasses the common permit check |
| PHP snippets/plugins and file operations | Processor and element paths may save executable code | A permitted content write may trigger execution, activate code or change protected files indirectly | Distinguish save from activation and deny unauthorized executable side effects |

## Architectural decision boundary

The Site Agent control plane owns authenticated human approvals, task lifecycle, ordered operations and one-use permits. MODX MCP must independently enforce a server-side scoped decision at the final mutation boundary. The existing single site bearer token is *not* that decision. In particular, disabling only Site Agent CLI commands or hiding tools in a bridge leaves a direct REST route.

Do not implement a second competing approval ledger in PHP. First define and test a shared, versioned permit contract (site, contour, task, immutable plan digest, exact action/target/parameter digest, operation id, expiry, single-use and failure semantics), then enforce it on BOTH modular and legacy execution paths. Unknown actions default to deny. Preserve existing tools for legitimate authorized use, including MODX 2 and 3, while denying unauthenticated writes.

## Source references

- `core/components/modxmcp/endpoint/api.common.php`, API authentication near lines 111–125.
- `core/components/modxmcp/model/modxmcp.class.php`, constructor and legacy fallback near lines 35–49; service user near lines 52–88; `processRequest` near lines 90–185.
- `docs/security/HARDENING-BACKLOG.md` and `docs/security/SITE-AGENT-INTEGRATION-REQUIREMENTS.md`.

## Validation still outstanding

- Map every mutation entrypoint and classify action permissions against actual MODX 2 / MODX 3 processors.
- Verify the HTTP endpoint-to-dispatch chain and legacy behavior under failure.
- Exercise negative tests with a direct valid-token client, failed/expired permit, fallback and concurrent writes in an isolated test environment.
- Demonstrate a safe restricted service account without changing production service accounts.
