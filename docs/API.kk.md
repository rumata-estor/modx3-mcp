# API анықтамалығы — MODX MCP

[English](API.md) · [Русский](API.ru.md) · [Español](API.es.md) · [Deutsch](API.de.md) · [Français](API.fr.md) · [Українська](API.uk.md) · [Қазақша](API.kk.md) · [Nederlands](API.nl.md) · [Polski](API.pl.md)

> Ескерту: бұл аударылған қысқаша шолу. 192 MCP құралының барлық параметрлер кестесі әзірге ағылшын және орыс тілдерінде қолжетімді.
Ашық MCP құралдарының атауы `modx_` префиксінен басталады. PHP HTTP әрекеттерінде бұл префикс қолданылмайды. Параметрлердің толық кестелері мен JSON схемалары ағылшын анықтамалығында берілген.

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

JSON POST сұрауларын HTTPS арқылы `X-MCP-Token` тақырыбымен жіберіңіз. `api.php` MCP JSON-RPC соңғы нүктесі емес; оған `tools/call` хабарларын тікелей жібермеңіз.

**Толық техникалық анықтамалар:** [API параметрлері (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/API.md) · [қауіпсіздік (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/SECURITY.md) · [әзірлеуші құжаттамасы (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/DEVELOPMENT.md). Техникалық кестелердің толық нұсқасы әзірге ағылшын тілінде.
