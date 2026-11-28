# Migration 1.2.7 -> 1.2.8

Tool names, read parameters and the HTTP contract are unchanged; the write model changed.

## Main change: mutations now require CAS

All 78 mutation tools require CAS preconditions: read `get_site_state` -> `site_revision`,
then send `observed_site_revision` + `strict_site_revision: true` + a `read_set` of
sha256 fingerprints (up to 32 entries). Without them a mutation returns `CAS_REQUIRED`.
A 1.2.7 client loses write access immediately after the upgrade — **upgrade the client
and the server together**.

## Also changed

| 1.2.7 | 1.2.8 |
|---|---|
| `run_processor` accepted any processor with `allow_run_processor=1` | settings processors always denied; security/workspace/package/namespace require privileged + external Ed25519 permit; `processors_path` denied |
| `update_media_file` overwrote files | `expected_sha256` is mandatory |
| membership/ACL mutations worked on any DB engine | require InnoDB + `REPEATABLE READ` + transaction + index |
| legacy dispatcher fallback on runtime failure | fail-closed `CAS_RUNTIME_REQUIRED`, including MODX 2 |
| log tools with a large `limit` | capped at 1000 lines and 4 MiB per response |
| reading secret settings | `[REDACTED]` by key name (password/token/secret/api_key, `modxmcp.*`) |

## Upgrade checklist

1. Check `system_info -> modxmcp_version` and `get_site_state -> cas_required`.
2. Teach your client the CAS loop (state -> fingerprints -> mutation with preconditions).
3. Verify InnoDB and REPEATABLE READ for membership mutations.
4. Ensure `modxmcp.external_authorization_required` exists and equals `0` or `1`.
5. Move blind media/static writes to `expected_sha256`/CAS.

## Beta limitations

- Strict static IO mode requires `/usr/bin/python3`; `portable` is the default.
- The branch is marked "NOT a release" in the CHANGELOG; do not install on production.
