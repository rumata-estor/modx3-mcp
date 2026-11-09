# MODX2MCP — MODX Extras release 1.2.5-pl

[English](modx2mcp-1.2.5.md) · [Русский](modx2mcp-1.2.5.ru.md) · [Español](modx2mcp-1.2.5.es.md) · [Deutsch](modx2mcp-1.2.5.de.md) · [Français](modx2mcp-1.2.5.fr.md) · [Українська](modx2mcp-1.2.5.uk.md) · [Қазақша](modx2mcp-1.2.5.kk.md) · [Nederlands](modx2mcp-1.2.5.nl.md) · [Polski](modx2mcp-1.2.5.pl.md)

**English** | [Русский](modx2mcp-1.2.5.ru.md)

- **Extra name:** MODX2MCP
- **Version:** 1.2.5-pl
- **Supported MODX:** Revolution 2.8.x (not MODX 3)
- **Package:** `modx2mcp-1.2.5-pl.transport.zip`
- **License:** MIT
- **Documentation:** [Installation guide](../INSTALL.md) · [Full documentation](../../README.md)
**Status:** 1.2.5 transport ZIP and checksums have **not yet been published**. [Last published transport ZIPs (1.2.4)](https://github.com/rumata-estor/modx3-mcp/releases/tag/v1.2.4)

## About MODX2MCP

MODX MCP connects compatible AI assistants to MODX Revolution 2.8.x through a controlled HTTP API and a local Model Context Protocol (MCP) client. The shared MODX 2/3 server contract has **199 server actions, including 198 public MCP tools**: resource, element, TV, system settings and ClientConfig operations; dependency graph analysis; change preconditions; backups and audit features. The client also supports MODX 3, but **this transport ZIP is MODX 2 only**.

## Install or update

1. Use MODX 2.8.x Package Management to install `modx2mcp-1.2.5-pl.transport.zip`. Do not install the MODX 3 package on MODX 2.
2. **Migrating from the older MODX 2 package named `modxmcp`:** fully uninstall that old package first, then install `modx2mcp`. These are separate package identities and must not coexist. A full uninstall removes the previous `modxmcp.*` settings and API token, so back up configuration and update client credentials as needed.
3. **Upgrading an existing `modx2mcp` installation:** use a normal package upgrade. Existing settings and API token are preserved.
4. On a fresh installation, the MCP endpoint is **disabled by default**. Review security settings and enable it deliberately using the component settings. Obtain the token through an authorized Manager account.
5. Install Node.js **18+** on the machine running your MCP-compatible application and configure `npx` to use `github:rumata-estor/modx3-mcp#v1.2.4`.
6. Set `MODX_MCP_SITE_URL=https://your-site.example/assets/components/modxmcp/api.php` and `MODX_MCP_TOKEN` to your **site-specific secret**. Use HTTPS. Restart the MCP client and verify a read-only operation before enabling writes.

See [complete installation instructions](../INSTALL.md), [security guidance](../SECURITY.md) and [release validation](../VALIDATION.md). A public `GET` response from the endpoint is **not** proof of token authentication.

## What's new in 1.2.5-pl

This bugfix release adds full **MODX element-category (modCategory) management** through six dedicated MCP tools: modx_list_categories, modx_get_category, modx_create_category, modx_update_category, modx_delete_category and modx_assign_element_category.

Categories can be created, renamed, nested, moved and reordered. Assignments work for chunks, snippets, templates, TVs and plugins **without rewriting element code**. Before deletion, the server checks child categories, assigned elements and property sets. Circular moves and accidental deletion of nonempty categories are rejected.

The MCP contract now exposes **199 server actions / 198 public tools**. These operations work in MODX Revolution 2.8.x and 3.x. Standard resources use their own parent hierarchy; miniShop2 category behavior is unchanged.

**Upgrade:** existing settings and API tokens are preserved by the standard MODX package upgrade. No credential rotation or data migration is required.

**Support and source:** [GitHub issues](https://github.com/rumata-estor/modx3-mcp/issues) · [current source](https://github.com/rumata-estor/modx3-mcp/tree/main).
