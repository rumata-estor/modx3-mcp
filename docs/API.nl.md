# API-referentie — MODX MCP

[English](API.md) · [Русский](API.ru.md) · [Español](API.es.md) · [Deutsch](API.de.md) · [Français](API.fr.md) · [Українська](API.uk.md) · [Қазақша](API.kk.md) · [Nederlands](API.nl.md) · [Polski](API.pl.md)

> Opmerking: dit is een vertaald overzicht. De volledige parametertabellen van alle 192 MCP-tools zijn beschikbaar in het Engels en Russisch.
Openbare MCP-tools gebruiken het voorvoegsel `modx_`; PHP-HTTP-acties gebruiken de naam zonder dit voorvoegsel. De volledige parametertabellen en JSON-schema’s staan in de Engelstalige referentie.

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

Stuur JSON via `POST` met de header `X-MCP-Token` over HTTPS. `api.php` is geen MCP-JSON-RPC-endpoint: stuur geen `tools/call`-berichten rechtstreeks.

**Volledige technische naslagwerken:** [API-parameters (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/API.md) · [beveiliging (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/SECURITY.md) · [ontwikkeling (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/DEVELOPMENT.md). De gedetailleerde technische tabellen zijn voorlopig in het Engels.
