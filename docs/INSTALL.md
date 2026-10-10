# Installing and connecting MODX MCP

**English** | [Русский](INSTALL.ru.md) · [Capabilities](CAPABILITIES.md) · [API reference](API.md) · [Security](SECURITY.md)

MODX MCP lets a standard MCP-compatible AI client work with **MODX Revolution 2.8.x and 3.x**. A regular installation requires a MODX transport package, **Node.js 18+** on the machine running your MCP client, and an application capable of starting **stdio** MCP servers.

**No separate orchestration server is necessary.** The MODX component hosts an HTTP API on your site; the local `modx-mcp` process exposes this API to MCP clients via `stdio`. The PHP endpoint is not itself a direct MCP stdio server.

## 1. Choose the MODX package

- **MODX 2.8.x:** `modx2mcp-1.2.0-pl.transport.zip`.
- **MODX 3.x:** `modx3mcp-1.2.0-pl.transport.zip`.

Both variants use the same public tool contract and Node.js client. Download the appropriate package from [GitHub Releases](https://github.com/rumata-estor/modx3-mcp/releases) or a supported MODX package provider.

**Existing MODX 2 installations:** when migrating from the legacy `modxmcp` package identity to `modx2mcp`, a clean uninstall of the old package followed by installation of `modx2mcp` is recommended. A clean uninstall removes old `modxmcp.*` settings and the API token. Preserve relevant configuration as needed and update your client credentials afterwards. Subsequent normal `modx2mcp` upgrades preserve the current token.

## 2. Install the transport package

1. Sign in to the MODX manager using an account allowed to install packages.
2. Open Package Management, upload the transport ZIP for your MODX major version, and install it.
3. Open the MODX MCP component or System Settings with namespace `modxmcp`.
4. Check `modxmcp.api_token` and network restrictions. Fresh installs start with `modxmcp.enabled = 0`; enable the component manually after securing access.
5. Copy the token into your **MCP client configuration**. Treat it as a secret, never as a public example.

The installer generates a cryptographically random token if the setting is empty. An ordinary upgrade preserves an existing token.

## 3. Locate the site endpoint

The standard URL is:

```text
https://example.com/assets/components/modxmcp/api.php
```

Use your own domain; custom deployments may change the actual path. **HTTPS** is recommended and is required by default.

`GET` returns a limited, unauthenticated component/version health response. Administrative requests use JSON `POST` with `X-MCP-Token`.

## 4. Configure your MCP host

On the machine running your AI application, install **Node.js 18+** with npm/`npx`. Add the MCP server to your host’s configuration (the location and supported configuration shape depend on your application):

```json
{
  "mcpServers": {
    "modx": {
      "command": "npx",
      "args": [
        "-y",
        "github:rumata-estor/modx3-mcp#v1.2.0"
      ],
      "env": {
        "MODX_MCP_SITE_URL": "https://example.com/assets/components/modxmcp/api.php",
        "MODX_MCP_TOKEN": "YOUR_SITE_PRIVATE_TOKEN"
      }
    }
  }
}
```

`MODX_MCP_SITE_URL` is the PHP HTTP API endpoint. `MODX_MCP_TOKEN` is the token for this specific site. Pin the **published release tag** on production systems instead of tracking `main`. Ensure that your host supports launching a local MCP server over `stdio`.

## 5. Confirm connectivity

Restart the MCP integration or refresh the host’s servers. Look for the `modx` server and tools prefixed `modx_`. Start with a read-only request, such as:

```text
Show the MODX version and an overview of the site structure. Make no changes.
```

This can use `modx_system_info` and `modx_project_overview`. Your host may show fewer than 191 tools, depending on the server build, disabled groups, and installed addons.

## 6. Security settings to review

| Setting | Meaning |
| --- | --- |
| `modxmcp.enabled` | Enable or disable the API. |
| `modxmcp.api_token` | Secret API credential. |
| `modxmcp.require_https` | Require HTTPS; enabled by default. |
| `modxmcp.allowed_ips` | Optional allowlist of IPs and IPv4 CIDR ranges; blank allows any source. |
| `modxmcp.trust_proxy_https` | Trust the reverse-proxy HTTPS header only behind a properly configured proxy. |
| `modxmcp.disabled_groups` | Disable some tool families; **not a security role boundary**. |
| `modxmcp.allow_run_processor` | Allow direct MODX processor invocation; disabled by default. |
| `modxmcp.max_payload_bytes` | Request size limit. |
| `modxmcp.debug` | Error details; keep disabled on production. |

**Important:** requests are executed as a MODX service user with `sudo` privileges. Exposure of a valid token may enable administrative changes and modification of executable PHP in elements. Read the [security guide](SECURITY.md).

## Troubleshooting

| Symptom | Check |
| --- | --- |
| MCP server cannot start | Node.js 18+, `npx`, package resolution and host configuration. |
| `401 Unauthorized` | `MODX_MCP_TOKEN` must match `modxmcp.api_token`; check token rotation. |
| `403` | Component enabled setting, HTTPS, reverse proxy configuration and allowed IP. |
| `404` | Endpoint path, component installation and web-server routing. |
| `413` | Request exceeded `modxmcp.max_payload_bytes`. |
| Tool not available | Client/server versions, disabled groups, required addon installed. |
| `500` | MODX error logs and `error_id`; avoid showing debug messages in public. |

Do not put API tokens into query strings or issue reports. Custom HTTP clients must send `X-MCP-Token` over TLS.

## Next steps

- [Capabilities](CAPABILITIES.md) — what MODX MCP can do.
- [API reference](API.md) — tools and input parameters.
- [Security](SECURITY.md) — risks and defensive configuration.
- [Project README](../README.md) — project overview.
- [Validation](VALIDATION.md) — compatibility test notes.
