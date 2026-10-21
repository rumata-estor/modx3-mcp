# Seguridad y modelo de confianza — MODX MCP

[English](SECURITY.md) · [Русский](SECURITY.ru.md) · [Español](SECURITY.es.md) · [Deutsch](SECURITY.de.md) · [Français](SECURITY.fr.md) · [Українська](SECURITY.uk.md) · [Қазақша](SECURITY.kk.md) · [Nederlands](SECURITY.nl.md) · [Polski](SECURITY.pl.md)

**El token de API es una credencial administrativa.** Las solicitudes autenticadas se ejecutan con un usuario de servicio MODX dotado de sudo. Un token válido puede permitir cambios amplios, incluido código PHP ejecutable.

**Importante:** desactivar grupos no sustituye la autorización del servidor. Los clientes HTTP personalizados tampoco heredan automáticamente las protecciones del cliente oficial Node.js.

## Seguridad y modelo de confianza

1. Utilice HTTPS y compruebe la terminación TLS en su proxy inverso.
2. Trate el token de API como una credencial de administrador; nunca lo publique.
3. Restrinja el acceso por IP o mediante una red protegida siempre que sea posible.
4. Mantenga `modxmcp.allow_run_processor=0` y `modxmcp.allow_root_filesystem_read=0`, salvo que sea imprescindible.
5. Desactivar grupos de funciones no equivale a asignar permisos individuales.
6. Mantenga las respuestas de depuración desactivadas en producción (`modxmcp.debug=0`).
7. Realice copias de seguridad independientes y pruebe `dry_run` antes de cambios destructivos.

## HTTP / MCP

- `POST` JSON uses `X-MCP-Token` over HTTPS.
- `GET` health/version is **not** proof that the client token is valid.
- `modxmcp.disabled_groups` is **not** an independent authorization boundary.
- External scripts calling `api.php` directly do not inherit Node.js client safeguards such as some backups and confirmations.

**Referencias técnicas completas:** [parámetros API (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/API.md) · [seguridad (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/SECURITY.md) · [desarrollo (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/DEVELOPMENT.md). Las tablas técnicas detalladas se mantienen en inglés.
