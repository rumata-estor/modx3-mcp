# MODX MCP system settings

[English](SETTINGS.md) · [Русский](SETTINGS.ru.md) · [Español](SETTINGS.es.md) · [Deutsch](SETTINGS.de.md) · [Français](SETTINGS.fr.md) · [Українська](SETTINGS.uk.md) · [Қазақша](SETTINGS.kk.md) · [Nederlands](SETTINGS.nl.md) · [Polski](SETTINGS.pl.md) · [Installation](INSTALL.md) · [Security](SECURITY.md)

For **MODX MCP 1.2.4** on MODX 2.8.x and 3.x. This document covers all **16 system settings** defined by both transport packages. Defaults come from `_build/data/transport.settings.php`; behavior has been checked against the PHP implementation.

In MODX manager: **System → System Settings**, namespace `modxmcp`. **Components → MODX MCP** also allows changing Audit log, Auto-static, capability groups and safely viewing/copying the API token. Change other options under System Settings. Refresh MODX caches when required and refresh the MCP client's capabilities after changes.

**Security:** operations authorized by the token run under a MODX **sudo service user**. Keep the token secret. Capability toggles **do not establish a permission boundary**.

## Connection and identity

| Setting | Default | Values and behavior |
| --- | --- | --- |
| `modxmcp.enabled` | `0` | `0`: API POST actions are rejected. `1`: API enabled. The limited `GET` health/version endpoint still works with `0` when HTTPS conditions are met. Enable only after access controls are ready. |
| `modxmcp.api_token` | Empty package value; a random token is generated at installation **if missing** | Secret string sent in the `X-MCP-Token` header. Missing/wrong tokens return `401`. Normal upgrades preserve the token. Managers with `settings` permission can use **Show** / **Copy**. Rotating a token invalidates existing client credentials. |
| `modxmcp.service_user_id` | `0` | `0` selects the first-by-ID **active sudo MODX user**; a positive ID requires an existing active sudo account. For predictable behavior choose a dedicated service account. Not the external MCP client's identity. |

## Network and security

| Setting | Default | Values and behavior |
| --- | --- | --- |
| `modxmcp.require_https` | `1` | `0`/`1`. Requires HTTPS, including for the `GET` health response, or returns `403`. Keep `1` on deployed sites. |
| `modxmcp.trust_proxy_https` | `0` | `0`/`1`. If enabled, `X-Forwarded-Proto: https` is accepted as HTTPS proof. Only enable behind a trusted reverse proxy that overwrites client-supplied headers. |
| `modxmcp.allowed_ips` | Empty | Comma-separated exact IP addresses and **IPv4 CIDR**, e.g. `203.0.113.5,198.51.100.0/24`. Blank allows any source with a valid token. Checks `REMOTE_ADDR`, **not** `X-Forwarded-For`. Exact IPv6 matches work; IPv6 CIDR does not. Incorrect values cause `403`. |
| `modxmcp.disabled_groups` | `versionx,virtualpage,minishop2,migx,access,property_sets,contexts,package_management,namespaces,lexicon` | Comma-separated disabled groups: `versionx`, `virtualpage`, `minishop2`, `migx`, `clientconfig`, `access`, `property_sets`, `contexts`, `package_management`, `namespaces`, `lexicon`. Empty enables all otherwise supported groups. **Not a security role boundary**; core actions may still modify a site. |
| `modxmcp.allow_run_processor` | `0` | `0`/`1`. Allows the general `run_processor` operation, including custom MODX processors. It increases the scope of administrator operations: keep disabled unless specifically needed. |
| `modxmcp.allow_root_filesystem_read` | `0` | `0`/`1`. Allows browsing and reading the root `Filesystem` media source. May expose broader server files. Keep disabled unless needed. |
| `modxmcp.component_code_roots` | `core/components,assets/components` | Comma-separated component source directories, relative to `base_path` or absolute. Supports `{core_path}`, `{assets_path}`, `{base_path}` placeholders. Constrains **component code inspection only**, not every MCP filesystem action. Avoid expanding to the server filesystem root. |

## Limits

| Setting | Default | Values and behavior |
| --- | --- | --- |
| `modxmcp.max_payload_bytes` | `1048576` (1 MiB) | Maximum HTTP API POST JSON request-body size in bytes; excess returns `413`. Values `<=0` remove this check. Keep a positive upper bound. |
| `modxmcp.max_read_bytes` | `262144` (256 KiB) | Maximum returned chunk when reading files; `<=0` falls back to 262144. **Not** a limit on entire file size: reads can use `offset` and `bytes`/`length`. |

## Behavior and diagnostics

| Setting | Default | Values and behavior |
| --- | --- | --- |
| `modxmcp.auto_static` | `0` | `0`/`1`. If enabled, MCP-created/updated chunks, snippets, templates and plugins become static files under `core/elements/`. Requires appropriate write permissions, backups and deployment practices. |
| `modxmcp.audit_log` | `1` | `0`/`1`. Logs supported mutation actions to MODX logs and `core/components/modxmcp/logs/audit.log`. **Not** an exhaustive record of every request. Keep enabled for production with access and size controls. |
| `modxmcp.debug` | `0` | `0`/`1`. If enabled, detailed internal exceptions may appear in HTTP error responses. When disabled, callers receive a generic error and `error_id`. Keep `0` in production. |

## Core path

| Setting | Default | Values and behavior |
| --- | --- | --- |
| `modxmcp.core_path` | `{core_path}components/modxmcp/` | Component core directory (also accepts `[[++core_path]]`). Used by the HTTP API for loading the implementation and version. Change only for verified nonstandard installations; incorrect values break the API. |

## Safe first connection

1. Install the transport matching your MODX major version. Fresh installs start with `modxmcp.enabled=0`; the token is generated automatically, and existing tokens survive routine upgrades.
2. Configure HTTPS, optionally IP restrictions, reverse proxy settings, and `service_user_id`.
3. Use **Copy** in the manager dashboard to put the API token in your MCP client's secret storage. Do not disclose it.
4. Enable only required capabilities. Leave `allow_run_processor` and `allow_root_filesystem_read` disabled unless justified.
5. Enable the API and verify an **authenticated read-only action**. A `GET` version/health response does **not** prove that the client token is valid.
