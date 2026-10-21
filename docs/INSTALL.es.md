# Instalación y conexión de MODX MCP

[English](INSTALL.md) · [Русский](INSTALL.ru.md) · [Español](INSTALL.es.md) · [Deutsch](INSTALL.de.md) · [Français](INSTALL.fr.md) · [Українська](INSTALL.uk.md) · [Қазақша](INSTALL.kk.md) · [Nederlands](INSTALL.nl.md) · [Polski](INSTALL.pl.md)

**MODX MCP 1.2.4 · MODX Revolution 2.8.x / 3.x · 193 / 192**

Elija el paquete de transporte correspondiente a la versión principal de MODX. El cliente MCP necesita Node.js 18 o posterior.

## 1. MODX 2 / MODX 3

- [Paquete para MODX 2](https://github.com/rumata-estor/modx3-mcp/releases/download/v1.2.4/modx2mcp-1.2.4-pl.transport.zip) — MODX 2.8.x
- [Paquete para MODX 3](https://github.com/rumata-estor/modx3-mcp/releases/download/v1.2.4/modx3mcp-1.2.4-pl.transport.zip) — MODX 3.x
- [SHA256SUMS](https://github.com/rumata-estor/modx3-mcp/releases/download/v1.2.4/SHA256SUMS)

## 2. Inicio rápido

1. Acceda al administrador MODX e instale el ZIP adecuado desde el gestor de paquetes.
2. Abra «Componentes → MODX MCP». Revise HTTPS, permisos y el token de API; las instalaciones nuevas comienzan desactivadas.
3. Active `modxmcp.enabled` únicamente cuando haya protegido el acceso. Guarde el token en la configuración privada del cliente MCP.

## 3. Node.js / MCP

Configure el anfitrión MCP compatible con stdio mediante el ejemplo siguiente. Sustituya el dominio y el token de ejemplo; utilice la versión publicada v1.2.4.

```json
{
  "mcpServers": {
    "modx": {
      "command": "npx",
      "args": ["-y", "github:rumata-estor/modx3-mcp#v1.2.4"],
      "env": {
        "MODX_MCP_SITE_URL": "https://example.com/assets/components/modxmcp/api.php",
        "MODX_MCP_TOKEN": "YOUR_SITE_PRIVATE_TOKEN"
      }
    }
  }
}
```

## 4. Verificar la conexión

Reinicie el cliente MCP y compruebe primero una operación de lectura, por ejemplo `modx_system_info` o `modx_project_overview`. Una respuesta GET de estado no valida el token.

## Actualización de instalaciones existentes

Las actualizaciones habituales de `modx2mcp` y `modx3mcp` conservan los ajustes y el token de API. Si su instalación MODX 2 todavía utiliza la identidad antigua `modxmcp`, haga primero una copia de seguridad de la configuración y del token antes de eliminar ese paquete.

## Solución de problemas

- `401`: `MODX_MCP_TOKEN` / `modxmcp.api_token`
- `403`: `modxmcp.enabled`, `modxmcp.require_https`, `modxmcp.allowed_ips`
- `404`: `/assets/components/modxmcp/api.php`
- `413`: `modxmcp.max_payload_bytes`
- `500`: MODX error log and `error_id`.

**Referencias técnicas completas:** [parámetros API (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/API.md) · [seguridad (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/SECURITY.md) · [desarrollo (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/DEVELOPMENT.md). Las tablas técnicas detalladas se mantienen en inglés.
