# MODX MCP (MODX 3) — MODX Extras release 1.2.4-pl

**English** | [Русский](modx3mcp-1.2.4.ru.md)

- **Extra name:** MODX MCP
- **Package identity:** MODX3MCP
- **Version:** 1.2.4-pl
- **Supported MODX:** Revolution 3.x (not MODX 2)
- **Package:** `modx3mcp-1.2.4-pl.transport.zip`
- **License:** MIT
- **Documentation:** [Installation guide](../INSTALL.md) · [Full documentation](../../README.md)
**Release package:** [Download from GitHub Release v1.2.4](https://github.com/rumata-estor/modx3-mcp/releases/download/v1.2.4/modx3mcp-1.2.4-pl.transport.zip) · [SHA-256 checksums](https://github.com/rumata-estor/modx3-mcp/releases/download/v1.2.4/SHA256SUMS)

## About MODX MCP

MODX MCP connects compatible AI assistants to MODX Revolution 3.x through a controlled HTTP API and a local Model Context Protocol (MCP) client. The shared server contract provides **193 server actions, including 192 public MCP tools**: resources, chunks, snippets, templates, TV values, system settings, ClientConfig, dependency graph analysis, change preconditions, backups and audit capabilities. This transport ZIP is **for MODX 3 only**; MODX 2 uses the separate MODX2MCP package.

## Install or update

1. Install `modx3mcp-1.2.4-pl.transport.zip` from MODX 3 Package Management, or upgrade the installed `modx3mcp` package through the same mechanism. Normal upgrades preserve the existing `modxmcp.*` settings and API token.
2. Fresh installations start **disabled by default**. Review security settings, then enable the endpoint in the component settings. Obtain the token through an authorized Manager account.
3. Install Node.js **18+** on the machine running your MCP-compatible application. Configure its MCP client to launch `npx` with `github:rumata-estor/modx3-mcp#v1.2.4`.
4. Set `MODX_MCP_SITE_URL=https://your-site.example/assets/components/modxmcp/api.php` and `MODX_MCP_TOKEN` to your **site-specific secret**. Use HTTPS.
5. Restart the MCP client and confirm an authenticated read-only request before enabling any write operations.

See [complete installation instructions](../INSTALL.md), [security guidance](../SECURITY.md) and [release validation](../VALIDATION.md). `GET` returning the component version does **not** prove MCP token authentication.

## What's new in 1.2.4-pl

Complete manager dashboard and dependency graph localization in **nine languages**: Russian (`ru`), English (`en`), Spanish (`es`), German (`de`), French (`fr`), Ukrainian (`uk`), Kazakh Cyrillic (`kk`), Dutch (`nl`) and Polish (`pl`). All 166 manager strings, including token management, capability labels, graph controls, filters and diagnostics, are translated.

The MCP API remains unchanged at **193 server actions / 192 public tools**. No automatic API token rotation occurs; normal upgrades preserve existing settings and token.

**Support and source:** [GitHub issues](https://github.com/rumata-estor/modx3-mcp/issues) · [Release v1.2.4](https://github.com/rumata-estor/modx3-mcp/releases/tag/v1.2.4). Derived from the original [modxMCP](https://github.com/dampilov94/mcp-component) project.
