# Довідник API — MODX MCP

[English](API.md) · [Русский](API.ru.md) · [Español](API.es.md) · [Deutsch](API.de.md) · [Français](API.fr.md) · [Українська](API.uk.md) · [Қазақша](API.kk.md) · [Nederlands](API.nl.md) · [Polski](API.pl.md)

> Примітка: це перекладений стислий огляд. Повні таблиці параметрів усіх 192 MCP-інструментів наразі доступні англійською та російською мовами.
Публічні MCP-інструменти мають префікс `modx_`, а HTTP-дії PHP використовують назви без цього префікса. Повні таблиці параметрів та JSON-схеми доступні в англомовному довіднику.

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

Надсилайте JSON POST із заголовком `X-MCP-Token` через HTTPS. `api.php` — не MCP JSON-RPC endpoint; не надсилайте туди `tools/call` напряму.

**Повні технічні довідники:** [параметри API (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/API.md) · [безпека (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/SECURITY.md) · [розробка (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/DEVELOPMENT.md). Детальні технічні таблиці поки доступні англійською.
