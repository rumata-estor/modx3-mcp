# MODX MCP — security and trust model

**English** | [Русский](SECURITY.ru.md) · [Installation](INSTALL.md) · [Capabilities](CAPABILITIES.md) · [API](API.md)

This guide documents the trust boundary of public **MODX MCP 1.2.1**, its security settings, and important limitations. It does not describe any external agent management infrastructure.

## The key rule

**Treat the MODX MCP API token as an administrative credential.** Knowledge of the endpoint URL or documented tool names alone does not authorize a request. A valid `X-MCP-Token`, however, can enable broad site changes.

After authenticating a request, the server selects a service account with MODX **`sudo`** permissions. `modxmcp.service_user_id` can specify an active `sudo` account; at its default value of zero, the first active `sudo` user is selected. This is **not** per-external-user authorization based on the operator's individual manager permissions.

A token holder may potentially change snippets, plugins, templates, resources, settings, and other sensitive content. Editing PHP in executable MODX elements can lead to arbitrary PHP code execution with the web application process's privileges.

## PHP endpoint protections

| Control | Behavior and limits |
| --- | --- |
| Token | Requires `X-MCP-Token`; checked against the `modxmcp.api_token` setting. |
| HTTPS | `modxmcp.require_https` defaults to enabled; verify TLS termination configuration. |
| IP allowlist | `modxmcp.allowed_ips` matches `REMOTE_ADDR` against exact IPs or IPv4 CIDR ranges; empty allows any peer address. |
| Reverse proxy | `modxmcp.trust_proxy_https` is off by default; enable only behind a trusted, correctly configured reverse proxy. |
| Master switch | `modxmcp.enabled` disables authenticated API operations when off. |
| Size limit | `modxmcp.max_payload_bytes` limits incoming JSON payload sizes. |
| Processor escape hatch | `modxmcp.allow_run_processor` is off by default. |
| Capability groups | `modxmcp.disabled_groups` disables selected tool families. |

**Disabled tool families are not a complete authorization boundary.** Standard system-setting create/update/delete tools reject keys beginning with `modxmcp.`, including attempts to rename other settings to that prefix. Token rotation remains a separate privileged action. This is not a sandbox: an authenticated caller who can edit executable PHP (or invoke explicitly enabled processors) may still affect site security. Different operators require independent server-side authorization controls.

A correctly authenticated endpoint is not the same as built-in request rate limiting. If your deployment needs rate limits, enforce them at the reverse proxy or web-server layer.

## Standard Node.js MCP client vs. direct HTTP calls

The supplied Node.js client includes additional safeguards for some mutations, such as pre-change backups of certain objects, environment-variable opt-ins for risky operations, and call coordination. **Custom callers posting directly to `api.php` do not execute these client-side protections.**

The PHP server also implements locking and precondition checks for some mutations, plus audit records. These do not make direct HTTP access equivalent to going through the standard MCP client.

If you write your own HTTP integration, implement your own backups, authorization, confirmations, and recovery procedures.

## Recommended production configuration

1. Use HTTPS with a valid certificate.
2. Keep the token in protected client configuration or a secret manager. Never embed it in URLs, public code, screenshots, support tickets, or logs.
3. Restrict endpoint access to known client IPs or a secured network/proxy where practical. Verify which address PHP actually sees in `REMOTE_ADDR`.
4. Keep `modxmcp.allow_run_processor = 0` unless specifically needed.
5. Disable unused capability groups, while recognizing that this is not per-user access control.
6. Leave `modxmcp.debug` disabled in production.
7. Maintain independent, tested backups of the MODX site and database.
8. Use `dry_run` on destructive bulk tools when available; manually approve high-impact changes to code, permissions and settings.
9. Rotate the API token if compromise is suspected; promptly update every authorized client configuration.

## Secrets and private information in tool responses

`modx_list_system_settings` and `modx_get_system_setting` can return values containing **API tokens and other secrets**. Do not forward complete results into public logs, issue reports, analytics pipelines, or an external model without assessing the confidentiality risk.

File reads, error logs, user records, and ecommerce order data can also contain private or sensitive information.

## Scope of protection

Documentation cannot prevent an AI client from misunderstanding a request. A single edit to a shared snippet, plugin, or template may affect multiple pages. Any application that grants differing rights to multiple operators must enforce those rights within its trusted server boundary, not solely with prompt instructions or a hidden tool list.

If you discover a vulnerability, do not post working credentials or customer data to a public GitHub issue. Revoke exposed secrets and report enough sanitized information for maintainers to reproduce the problem safely.

## Implementation

- [PHP endpoint and token validation](https://github.com/rumata-estor/modx3-mcp/blob/main/core/components/modxmcp/endpoint/api.common.php)
- [Default settings](https://github.com/rumata-estor/modx3-mcp/blob/main/_build/data/transport.settings.php)
- [Standard MCP client](https://github.com/rumata-estor/modx3-mcp/blob/main/client/index.js)
- [Server-side action dispatcher](https://github.com/rumata-estor/modx3-mcp/blob/main/core/components/modxmcp/model/modxmcp.class.php)
