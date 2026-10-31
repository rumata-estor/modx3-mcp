# Paquete para MODX 3 — MODX MCP 1.2.5

[English](modx3mcp-1.2.5.md) · [Русский](modx3mcp-1.2.5.ru.md) · [Español](modx3mcp-1.2.5.es.md) · [Deutsch](modx3mcp-1.2.5.de.md) · [Français](modx3mcp-1.2.5.fr.md) · [Українська](modx3mcp-1.2.5.uk.md) · [Қазақша](modx3mcp-1.2.5.kk.md) · [Nederlands](modx3mcp-1.2.5.nl.md) · [Polski](modx3mcp-1.2.5.pl.md)

[Instalación](../INSTALL.es.md) · [Seguridad](../SECURITY.es.md) · [Ajustes](../SETTINGS.es.md) · [GitHub source](https://github.com/rumata-estor/modx3-mcp/tree/main)

**MODX Revolution 3.x · 1.2.5-pl**

MODX MCP conecta asistentes de IA con sitios MODX mediante Model Context Protocol. El componente PHP expone una API HTTP autenticada y el cliente local Node.js la ofrece a las aplicaciones compatibles a través de stdio. No se necesita un servidor de orquestación adicional.

## Descargar

**Estado:** los ZIP de transporte 1.2.5 y las sumas SHA-256 todavía no se han publicado. [Última versión disponible: 1.2.4](https://github.com/rumata-estor/modx3-mcp/releases/tag/v1.2.4)

## Inicio rápido

1. Acceda al administrador MODX e instale el ZIP adecuado desde el gestor de paquetes.
2. Abra «Componentes → MODX MCP». Revise HTTPS, permisos y el token de API; las instalaciones nuevas comienzan desactivadas.
3. Active `modxmcp.enabled` únicamente cuando haya protegido el acceso. Guarde el token en la configuración privada del cliente MCP.

## Actualización de instalaciones existentes

Las actualizaciones habituales de `modx2mcp` y `modx3mcp` conservan los ajustes y el token de API. Si su instalación MODX 2 todavía utiliza la identidad antigua `modxmcp`, haga primero una copia de seguridad de la configuración y del token antes de eliminar ese paquete.

## Seguridad y modelo de confianza

**El token de API es una credencial administrativa.** Las solicitudes autenticadas se ejecutan con un usuario de servicio MODX dotado de sudo. Un token válido puede permitir cambios amplios, incluido código PHP ejecutable.

**Referencias técnicas completas:** [parámetros API (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/API.md) · [seguridad (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/SECURITY.md) · [desarrollo (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/DEVELOPMENT.md). Las tablas técnicas detalladas se mantienen en inglés.

## Novedades de 1.2.5-pl

Se han añadido seis herramientas MCP para gestionar categorías de elementos MODX (modCategory): listar, consultar, crear, renombrar o mover, eliminar de forma segura y asignar categorías a chunks, snippets, plantillas, TVs y plugins.

La eliminación comprueba primero subcategorías, elementos y conjuntos de propiedades. No se modifica el árbol de recursos ni miniShop2.

**199 acciones del servidor / 198 herramientas MCP públicas**. MODX Revolution 2.8.x / 3.x. Las actualizaciones normales conservan la configuración y el token.
