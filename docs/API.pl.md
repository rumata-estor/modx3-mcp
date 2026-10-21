# Dokumentacja API — MODX MCP

[English](API.md) · [Русский](API.ru.md) · [Español](API.es.md) · [Deutsch](API.de.md) · [Français](API.fr.md) · [Українська](API.uk.md) · [Қазақша](API.kk.md) · [Nederlands](API.nl.md) · [Polski](API.pl.md)

> Uwaga: to przetłumaczony przegląd. Pełne tabele parametrów wszystkich 192 narzędzi MCP są nadal dostępne po angielsku i rosyjsku.
Publiczne narzędzia MCP mają prefiks `modx_`; akcje PHP HTTP nie używają tego prefiksu. Pełne tabele parametrów i schematy JSON są dostępne w dokumentacji angielskiej.

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

Wysyłaj JSON metodą `POST` z `X-MCP-Token` przez HTTPS. `api.php` nie jest punktem MCP JSON-RPC: nie wysyłaj tam bezpośrednio komunikatów `tools/call`.

**Pełne dokumenty techniczne:** [parametry API (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/API.md) · [bezpieczeństwo (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/SECURITY.md) · [dla programistów (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/DEVELOPMENT.md). Szczegółowe tabele techniczne są obecnie dostępne po angielsku.
