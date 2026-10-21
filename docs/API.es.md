# Referencia de API — MODX MCP

[English](API.md) · [Русский](API.ru.md) · [Español](API.es.md) · [Deutsch](API.de.md) · [Français](API.fr.md) · [Українська](API.uk.md) · [Қазақша](API.kk.md) · [Nederlands](API.nl.md) · [Polski](API.pl.md)

> Nota: esta es una guía traducida de consulta rápida. Las tablas detalladas de los 192 instrumentos MCP se encuentran en los documentos inglés y ruso.
Las herramientas MCP públicas usan el prefijo `modx_`; las acciones HTTP de PHP no lo llevan. Las tablas exhaustivas de parámetros y los esquemas JSON están disponibles en la referencia inglesa.

## MCP

```json
{"name":"modx_get_element","arguments":{"type":"chunk","name":"Header"}}
```

## HTTP

```http
POST /assets/components/modxmcp/api.php HTTP/1.1
Host: example.com
Content-Type: application/json
X-MCP-Token: <private token>

{"action":"get_element","type":"chunk","data":{"name":"Header"}}
```

Envíe solicitudes `POST` con JSON y `X-MCP-Token` mediante HTTPS. `api.php` no es un endpoint MCP JSON-RPC; no envíe mensajes `tools/call` directamente.

**Referencias técnicas completas:** [parámetros API (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/API.md) · [seguridad (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/SECURITY.md) · [desarrollo (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/DEVELOPMENT.md). Las tablas técnicas detalladas se mantienen en inglés.
