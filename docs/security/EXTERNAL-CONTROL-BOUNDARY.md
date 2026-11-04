# Independent Site Agent authorization — protected-mode staging

Status: **development-only fail-closed boundary**, not a connected authorization service. Applies to MODX 2 and MODX 3 code paths in this branch only. Do not deploy or enable on production sites.

## Approved direction

Site Agent Task Control is the **sole authority** for authenticated human approvals, task states, plan fingerprint and consumed operation permits. MODX MCP must check an authenticated decision immediately before actual write; it must never substitute an MCP bearer token, CLI flag or request field for human approval.

The control plane currently exposes a local Unix socket and OS peer-UID security. PHP running on a remote MODX host **cannot** connect directly to that local socket. A separate trusted bridge/executor identity and a mutually authenticated, versioned remote protocol will be required. Neither webhook data nor a caller-supplied JSON `approved=true` flag is sufficient.

## Current safe staging step

When the MODX system setting `modxmcp.external_authorization_required` is **true**, both the MODX 2 and MODX 3 `processRequest` dispatchers call `assertExternalAuthorizationBoundary` **before** service-user selection, modular dispatch and legacy fallback.

- A small, explicit set of read-only actions remains callable.
- **All writes, unknown actions, and unclassified actions are denied**, even if the API bearer token is valid, until a real external permit verifier is built.
- The check cannot be bypassed by a `dry_run` or `--yes` flag because it does not trust client-provided authorization.
- When the setting is absent or false, existing behavior is unchanged. This is **not** a production-ready security default; direct legacy writes remain possible in that mode.

The denylist is deliberately *not* used; the read-only allowlist is conservative, so some legitimate read capabilities may also be blocked until classified. The development setting must not be enabled as a supposed complete integration.

## Cross-system permit contract to implement next

A verified request must identify the target `site_id`, `contour`, `task_id`, frozen `plan_digest`, ordered `operation_id`, exact MODX `action`, target and canonical parameter digest, expiry and an idempotency key. The trusted bridge must authenticate the Executor identity without giving the agent its signing secret. The controller must atomically consume a permit; a second request or different action/parameters/site/contour must fail closed.

A viable design requires a well-defined **cross-language canonical serialization** (Python and PHP), replay protection at the actual MODX write boundary, and authenticated responses. A remote permit cannot be carried as a self-declared user string, nor can its status be inferred from HTTP 200 alone. Unknown or interrupted outcomes must be reconciled before reuse; do not automatically retry.

A new permit bridge **must not** allow the site PHP runtime to create approvals or change the Task Control policy. Existing legacy and modular processors need the same enforcement, including file operations and any alternate endpoints. Locking/CAS and MODX ACLs remain separate mandatory checks.

## Offline regression

```bash
php tests/test-external-authorization-boundary.php modx2
php tests/test-external-authorization-boundary.php modx3
```

The tests exercise explicit read actions, mutation rejections and unknown-action rejection. They do not simulate a real MODX installation, a live HTTP call or a real Site Agent permit. CI is configured for the feature branch.

## Release blockers

1. Authenticated trusted bridge and independent permit verification integrated with the controller.
2. Exact mutation scope across all 199 modular actions and legacy fallbacks, with auditable read/write classification.
3. Restricted service identity and OS isolation; direct API token cannot bypass the authorized Executor.
4. One-use, crash/replay-safe write permits with end-to-end outcome reporting and rollback.
5. Isolated MODX 2 and MODX 3 integration tests, including rejected direct bearer-token writes and simulated bridge outage.

**No changes to production sites, server configuration, version numbers, main or develop.**

## Signed transport primitives (development only)

- `endpoint/permit-wire.php`: strict ASCII field order `SITEAGENT-MODX-PERMIT-V1` with HMAC-SHA256, shared with the Python `siteagent/permit_wire.py` implementation. Signed fields: version, audience, site, contour, task, immutable plan digest, operation ID, action, target digest, parameter digest, raw request digest, nonce, expiry.
- `endpoint/permit-nonce-ledger.php`: atomic create-exclusive consumption of a 128-bit nonce in a private directory, with path/permission checks. It does not yet provide a fully durable commit protocol, distributed lock or robust crash recovery.
- `tests/test-permit-wire.php` uses a shared fixed vector with Python and rejects altered scope. `tests/test-permit-nonce-ledger.php` checks replay, malformed nonce and unsafe ledger directory. Both run offline in CI.

**Neither helper is called by the live endpoint. The external_authorization_required mode still denies all writes.** This is intentional: connecting these utilities before an isolated issuer, verified audience and endpoint-bound request digest exist would produce a false approval path.

Outstanding before real writes: authenticated issuer/service identity, protected per-site key provisioning/rotation, expiry and clock handling, request-body canonical binding, site/contour binding from trusted configuration rather than user input, atomic nonce consumption at the actual mutation boundary, verified outcome/unknown reconciliation and fail-closed fallback for both MODX platforms.

## HTTP adapter pilot (branch-only, not deployed)

The shared MODX 2/3 HTTP endpoint now has a restricted opt-in signed write path when `modxmcp.external_authorization_required=true`. After API bearer authentication, it distinguishes explicitly listed reads from a **small classified mutation allowlist**. For classified writes only, it loads `endpoint/protected-api.php`, verifies an `X-SiteAgent-Permit` header (base64 JSON envelope) with an HMAC key read from a private file outside the document root, checks a configured audience, site ID and contour, matches the exact SHA-256 of the original POST body, verifies expiry within 60 seconds and consumes a unique nonce before dispatch. A validated decision object is required by both MODX 2 and MODX 3 dispatchers. Unknown actions and `run_processor` stay forbidden even with a MAC. The ordinary agent client does not issue tokens.

Required private settings in a **future isolated test**: `modxmcp.permit_audience`, `modxmcp.permit_site_id`, `modxmcp.permit_contour`, `modxmcp.permit_key_file` (absolute protected key file containing exactly 64 lowercase hex characters), `modxmcp.permit_nonce_directory` (pre-created private directory). No values, keys or directories have been installed.

`client/permit-transport.js` is an **unwired trusted-Executor helper** that serializes the request exactly once, computes its digest and forwards those same bytes with the signed permit. It is not available to an ordinary MCP agent tool call. A privileged executor must prepare the byte-identical body *before* obtaining the Task Control signature, and must never allow the agent to specify a permit, issuer key or target URL.

**Still not production-safe:** the nonce ledger has no audited durable recovery after OS crashes, the remote permit is not yet routed from isolated Executor through the normal SiteAgent mutation workflows, the PHP interpreter may still be broadly privileged, and the full action/capability matrix has not been tested. A mere valid HMAC is not proof the deployment enforces permissions end-to-end. Do not turn on the setting on a working website.

## Nonce persistence hardening (development branch, 2026-11-04)

The protected nonce-ledger now **fails closed** if PHP `fsync()` is unavailable or reports failure: a mere successful buffered file write is not evidence of a durable single-use reservation. The offline PHP regression also checks replay rejection after recreating the ledger object, cross-site/contour misuse, stale MAC and tampered signatures. These PHP regressions must run in GitHub Actions because the SiteAgent host currently lacks PHP CLI. **Important:** file `fsync()` alone does not establish directory-entry durability across abrupt host power loss; this still requires a vetted durability strategy and crash/restart validation on both actual MODX hosting environments before enabling authorized writes. No live endpoint changes in this commit.

## Pilot mutation scope narrowed (2026-11-04)

The protected MODX 2/3 HTTP API and both internal dispatcher variants now enforce the same initial operation allowlist as SiteAgent Executor: **only** `update_element`, element type `chunk`, data with exactly integer positive `id` and string `content`. Extra keys, other content types, all other actions, and legacy mutation routes remain disallowed in protected mode. HTTP checks are applied before an independent nonce is consumed; the dispatcher repeats the same check before any service-user lookup or modular execution. The ordinary, explicitly unprotected MCP mode is unchanged. This narrows reachability but **does not** solve compare-and-swap or bypass through other privileged credentials, and neither platform has been installed or mutated for this pilot. PHP tests are delegated to CI because the current server lacks PHP CLI.
