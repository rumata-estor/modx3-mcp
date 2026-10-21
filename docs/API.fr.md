# Référence API — MODX MCP

[English](API.md) · [Русский](API.ru.md) · [Español](API.es.md) · [Deutsch](API.de.md) · [Français](API.fr.md) · [Українська](API.uk.md) · [Қазақша](API.kk.md) · [Nederlands](API.nl.md) · [Polski](API.pl.md)

> Remarque : cette page est une présentation traduite. Les tableaux complets des paramètres des 192 outils MCP restent disponibles en anglais et en russe.
Les outils MCP publics commencent par `modx_` ; les actions HTTP PHP utilisent les noms sans ce préfixe. Les tableaux exhaustifs des paramètres et les schémas JSON restent consultables dans la référence anglaise.

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

Envoyez un `POST` JSON avec `X-MCP-Token` via HTTPS. `api.php` n’est pas un point d’accès MCP JSON-RPC : n’envoyez pas directement de requêtes `tools/call`.

**Références techniques complètes :** [paramètres API (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/API.md) · [sécurité (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/SECURITY.md) · [développement (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/DEVELOPMENT.md). Les tables techniques détaillées restent en anglais.
