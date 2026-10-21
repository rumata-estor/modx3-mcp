# API-Referenz — MODX MCP

[English](API.md) · [Русский](API.ru.md) · [Español](API.es.md) · [Deutsch](API.de.md) · [Français](API.fr.md) · [Українська](API.uk.md) · [Қазақша](API.kk.md) · [Nederlands](API.nl.md) · [Polski](API.pl.md)

> Hinweis: Diese Seite ist eine übersetzte Kurzübersicht. Die vollständigen Parametertabellen der 192 MCP-Werkzeuge sind weiterhin auf Englisch und Russisch verfügbar.
Öffentliche MCP-Werkzeuge tragen das Präfix `modx_`. Die PHP-HTTP-API verwendet dagegen Aktionsnamen ohne Präfix. Die vollständigen Parametertabellen und JSON-Schemata bleiben in der englischen Referenz verfügbar.

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

Senden Sie `POST` mit JSON und `X-MCP-Token` über HTTPS. `api.php` ist kein MCP-JSON-RPC-Endpunkt: `tools/call` wird dort nicht direkt verarbeitet.

**Vollständige technische Referenzen:** [API-Parameter (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/API.md) · [Sicherheit (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/SECURITY.md) · [Entwicklerdokumentation (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/DEVELOPMENT.md). Die ausführlichen englischen Referenztabellen sind maßgeblich.
