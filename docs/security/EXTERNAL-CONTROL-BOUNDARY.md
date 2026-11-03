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
