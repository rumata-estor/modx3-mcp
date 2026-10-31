# MODX MCP — administración de MODX con asistentes de IA

[English](README.md) · [Русский](README.ru.md) · [Español](README.es.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Українська](README.uk.md) · [Қазақша](README.kk.md) · [Nederlands](README.nl.md) · [Polski](README.pl.md)

**MODX MCP 1.2.5 · MODX Revolution 2.8.x / 3.x · 199 / 198**

MODX MCP conecta asistentes de IA con sitios MODX mediante Model Context Protocol. El componente PHP expone una API HTTP autenticada y el cliente local Node.js la ofrece a las aplicaciones compatibles a través de stdio. No se necesita un servidor de orquestación adicional.


## ¿A quién va dirigido?

Para desarrolladores, agencias y administradores que necesitan gestionar sitios MODX de forma controlada con IA.


## Funciones y herramientas

- Buscar, leer y modificar recursos, plantillas, chunks, snippets, plugins y TVs.
- Administrar categorías de elementos MODX: crear, renombrar, mover, eliminar con seguridad y asignar a elementos.
- Examinar dependencias, referencias rotas y código posiblemente sin utilizar mediante el grafo.
- Trabajar con ajustes del sistema, fuentes multimedia, paquetes y complementos compatibles.
- Proteger los cambios con condiciones previas, simulaciones y copias de seguridad.

## Inicio rápido

Elija el paquete de transporte correspondiente a la versión principal de MODX. El cliente MCP necesita Node.js 18 o posterior.

- **MODX 2.8.x:** [Paquete para MODX 2](https://github.com/rumata-estor/modx3-mcp/releases/download/v1.2.4/modx2mcp-1.2.4-pl.transport.zip)
- **MODX 3.x:** [Paquete para MODX 3](https://github.com/rumata-estor/modx3-mcp/releases/download/v1.2.4/modx3mcp-1.2.4-pl.transport.zip)
- [GitHub v1.2.4](https://github.com/rumata-estor/modx3-mcp/releases/tag/v1.2.4) · [SHA256SUMS](https://github.com/rumata-estor/modx3-mcp/releases/download/v1.2.4/SHA256SUMS)

> Los enlaces de descarga anteriores corresponden a 1.2.4, la última versión publicada. Los ZIP 1.2.5 siguen pendientes de compilación.

Acceda al administrador MODX e instale el ZIP adecuado desde el gestor de paquetes.

Abra «Componentes → MODX MCP». Revise HTTPS, permisos y el token de API; las instalaciones nuevas comienzan desactivadas.

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

Active `modxmcp.enabled` únicamente cuando haya protegido el acceso. Guarde el token en la configuración privada del cliente MCP.

## Seguridad y modelo de confianza

**El token de API es una credencial administrativa.** Las solicitudes autenticadas se ejecutan con un usuario de servicio MODX dotado de sudo. Un token válido puede permitir cambios amplios, incluido código PHP ejecutable.

**Importante:** desactivar grupos no sustituye la autorización del servidor. Los clientes HTTP personalizados tampoco heredan automáticamente las protecciones del cliente oficial Node.js.

## Documentación

[Instalación](docs/INSTALL.es.md) · [Seguridad](docs/SECURITY.es.md) · [Ajustes](docs/SETTINGS.es.md) · [Funciones](docs/CAPABILITIES.es.md) · [API](docs/API.es.md)

**Referencias técnicas completas:** [parámetros API (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/API.md) · [seguridad (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/SECURITY.md) · [desarrollo (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/DEVELOPMENT.md). Las tablas técnicas detalladas se mantienen en inglés.
