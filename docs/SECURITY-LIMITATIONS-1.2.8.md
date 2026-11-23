# MODX MCP 1.2.8 Beta — security limitations

> **We do not guarantee the mathematical absence of every conflict; we guarantee predictable system behavior and honest disclosure of its limitations.**

This document records the **agreed Beta safety boundary** and the procedure for handling uncertain mutations. It is not a certification that all security risks have been eliminated. Passing a specific integration test is not a universal guarantee for every addon, driver, or hosting environment.

## Scope and trust boundary

MODX MCP performs application operations using **native MODX objects, processors, and Media Sources**. The three P1 mitigations (TV updates, media overwrites, access policies) introduce no new direct SQL writers, table locks, or direct media-file writes that bypass MODX. The project already has revision/CAS coordination infrastructure; that infrastructure must not be represented as control over every third-party process.

MODX Manager, plugins, other integrations and external file editors are not required to acquire the MCP lock. A time-of-check/time-of-use (TOCTOU) window remains between the last observation and mutation. **This is a documented Beta limitation, not an implicit atomicity guarantee.**

## P1 mitigations

| Scenario | MCP behavior | Residual limitation |
| --- | --- | --- |
| **Resource TV update** | Mandatory CAS, exact `expected_tvs` raw values, and another native MODX TV read immediately before each `setTVValue()`. A mismatch raises `STALE_STATE`. | An outside writer can still change the TV after the last comparison. This is not a universal lock. |
| **Media-file overwrite** | Require `expected_sha256` from a complete verified read. Before mutation and immediately before `updateObject()`, compare that hash with content read via the native `Media Source::getObjectContents()` API. Reject missing or changed data. | A hash check is not an atomic compare-and-swap on remote/object storage. Some custom drivers cannot provide verifiable content, and another writer can race after the final check. |
| **Access policy / policy-template update or delete** | Require a complete, server-issued target `read_set` fingerprint from `get_site_state`. Template fingerprints also include related permissions available through native MODX models. Reject unavailable or stale snapshots. | This does not make concurrent changes to all ACL entities atomic. Outside changes may occur after the last snapshot. |

A media hash is returned by `modx_read_media_source_file` only for a complete response that can be verified against the native Media Source content. **Do not manufacture `expected_sha256` from guessed or truncated data.** A missing hash means automatic overwrite is not supported in that situation.

For access policies, use `get_site_state` with a `read_set` target selector such as `{"role":"target","type":"access_policy","id":123}` or `{"role":"target","type":"access_policy_template","id":123}`. Forward the server-issued fingerprint and revision in `_runtime_preconditions`; never fabricate hashes client-side.

## Stop and report

- **`CAS_REQUIRED`** — no required snapshot; mutation has not begun. Obtain fresh state and prepare a new request.
- **`STALE_STATE`** — a conflicting edit was detected; stop before overwriting, compare both versions, and obtain a new decision.
- **`MANUAL_REVIEW_REQUIRED`** — a safe automatic check is unavailable; stop before the hazardous write. Inspect the object in MODX Manager. Do not disable the guard to force a retry.
- **`MUTATION_OUTCOME_UNCERTAIN`** — an external effect might already have happened. **Do not automatically retry or run compensating rollback.** Read back actual state and obtain an explicit decision.

Report `outcome=not_applied` only when a pre-effect refusal is confirmed; use `outcome=unknown` when the outcome cannot be verified. Do not expose secrets, internal SQL, or media content in diagnostics.

## Beta release decision

The goal is to materially reduce the risk of lost updates, catch common conflicts and **honestly expose the remaining risk**, not implement a universal distributed transaction system around MODX. Critical environments still require independent backups and human approval of high-impact edits.

**Release gate:** MODX 2/3 integration and diagnostic regressions must pass, and residual TOCTOU limitations must be documented. Green tests do not transform best-effort checks into a mathematical guarantee.

This describes the **1.2.8 Beta candidate**. It does not imply publication in a stable release.
