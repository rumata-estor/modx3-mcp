# Paquete para MODX 2 — MODX MCP 1.2.4

[English](modx2mcp-1.2.4.md) · [Русский](modx2mcp-1.2.4.ru.md) · [Español](modx2mcp-1.2.4.es.md) · [Deutsch](modx2mcp-1.2.4.de.md) · [Français](modx2mcp-1.2.4.fr.md) · [Українська](modx2mcp-1.2.4.uk.md) · [Қазақша](modx2mcp-1.2.4.kk.md) · [Nederlands](modx2mcp-1.2.4.nl.md) · [Polski](modx2mcp-1.2.4.pl.md)

[Instalación](../INSTALL.es.md) · [Seguridad](../SECURITY.es.md) · [Ajustes](../SETTINGS.es.md) · [GitHub v1.2.4](https://github.com/rumata-estor/modx3-mcp/releases/tag/v1.2.4)

**MODX Revolution 2.8.x · 1.2.4-pl**

MODX MCP conecta asistentes de IA con sitios MODX mediante Model Context Protocol. El componente PHP expone una API HTTP autenticada y el cliente local Node.js la ofrece a las aplicaciones compatibles a través de stdio. No se necesita un servidor de orquestación adicional.

## Descargar

[modx2mcp-1.2.4-pl.transport.zip](https://github.com/rumata-estor/modx3-mcp/releases/download/v1.2.4/modx2mcp-1.2.4-pl.transport.zip) · [SHA256SUMS](https://github.com/rumata-estor/modx3-mcp/releases/download/v1.2.4/SHA256SUMS)

## Inicio rápido

1. Acceda al administrador MODX e instale el ZIP adecuado desde el gestor de paquetes.
2. Abra «Componentes → MODX MCP». Revise HTTPS, permisos y el token de API; las instalaciones nuevas comienzan desactivadas.
3. Active `modxmcp.enabled` únicamente cuando haya protegido el acceso. Guarde el token en la configuración privada del cliente MCP.

## Actualización de instalaciones existentes

Las actualizaciones habituales de `modx2mcp` y `modx3mcp` conservan los ajustes y el token de API. Si su instalación MODX 2 todavía utiliza la identidad antigua `modxmcp`, haga primero una copia de seguridad de la configuración y del token antes de eliminar ese paquete.

## Seguridad y modelo de confianza

**El token de API es una credencial administrativa.** Las solicitudes autenticadas se ejecutan con un usuario de servicio MODX dotado de sudo. Un token válido puede permitir cambios amplios, incluido código PHP ejecutable.

**Referencias técnicas completas:** [parámetros API (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/API.md) · [seguridad (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/SECURITY.md) · [desarrollo (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/DEVELOPMENT.md). Las tablas técnicas detalladas se mantienen en inglés.
