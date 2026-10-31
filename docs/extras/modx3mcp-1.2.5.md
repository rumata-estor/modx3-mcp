# MODX MCP (MODX 3) — MODX Extras release 1.2.5-pl

[English](modx3mcp-1.2.5.md) · [Русский](modx3mcp-1.2.5.ru.md) · [Español](modx3mcp-1.2.5.es.md) · [Deutsch](modx3mcp-1.2.5.de.md) · [Français](modx3mcp-1.2.5.fr.md) · [Українська](modx3mcp-1.2.5.uk.md) · [Қазақша](modx3mcp-1.2.5.kk.md) · [Nederlands](modx3mcp-1.2.5.nl.md) · [Polski](modx3mcp-1.2.5.pl.md)

**English** | [Русский](modx3mcp-1.2.5.ru.md)

- **Extra name:** MODX MCP
- **Package identity:** MODX3MCP
- **Version:** 1.2.5-pl
- **Supported MODX:** Revolution 3.x (not MODX 2)
- **Package:** `modx3mcp-1.2.5-pl.transport.zip`
- **License:** MIT
- **Documentation:** [Installation guide](../INSTALL.md) · [Full documentation](../../README.md)
**Status:** 1.2.5 transport ZIP and checksums have **not yet been published**. [Last published transport ZIPs (1.2.4)](https://github.com/rumata-estor/modx3-mcp/releases/tag/v1.2.4)

## About MODX MCP

MODX MCP connects compatible AI assistants to MODX Revolution 3.x through a controlled HTTP API and a local Model Context Protocol (MCP) client. The shared server contract provides **199 server actions, including 198 public MCP tools**: resources, chunks, snippets, templates, TV values, system settings, ClientConfig, dependency graph analysis, change preconditions, backups and audit capabilities. This transport ZIP is **for MODX 3 only**; MODX 2 uses the separate MODX2MCP package.

## Install or update

1. Install `modx3mcp-1.2.5-pl.transport.zip` from MODX 3 Package Management, or upgrade the installed `modx3mcp` package through the same mechanism. Normal upgrades preserve the existing `modxmcp.*` settings and API token.
2. Fresh installations start **disabled by default**. Review security settings, then enable the endpoint in the component settings. Obtain the token through an authorized Manager account.
3. Install Node.js **18+** on the machine running your MCP-compatible application. Configure its MCP client to launch `npx` with `github:rumata-estor/modx3-mcp#v1.2.4`.
4. Set `MODX_MCP_SITE_URL=https://your-site.example/assets/components/modxmcp/api.php` and `MODX_MCP_TOKEN` to your **site-specific secret**. Use HTTPS.
5. Restart the MCP client and confirm an authenticated read-only request before enabling any write operations.

See [complete installation instructions](../INSTALL.md), [security guidance](../SECURITY.md) and [release validation](../VALIDATION.md). `GET` returning the component version does **not** prove MCP token authentication.

## What's new in 1.2.5-pl

This bugfix release adds full **MODX element-category (modCategory) management** through six dedicated MCP tools: modx_list_categories, modx_get_category, modx_create_category, modx_update_category, modx_delete_category and modx_assign_element_category.

Categories can be created, renamed, nested, moved and reordered. Assignments work for chunks, snippets, templates, TVs and plugins **without rewriting element code**. Before deletion, the server checks child categories, assigned elements and property sets. Circular moves and accidental deletion of nonempty categories are rejected.

The MCP contract now exposes **199 server actions / 198 public tools**. These operations work in MODX Revolution 2.8.x and 3.x. Standard resources use their own parent hierarchy; miniShop2 category behavior is unchanged.

**Upgrade:** existing settings and API tokens are preserved by the standard MODX package upgrade. No credential rotation or data migration is required.

**Support and source:** [GitHub issues](https://github.com/rumata-estor/modx3-mcp/issues) · [current source](https://github.com/rumata-estor/modx3-mcp/tree/main).
