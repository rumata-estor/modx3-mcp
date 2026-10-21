# MODX MCP installeren en verbinden

[English](INSTALL.md) · [Русский](INSTALL.ru.md) · [Español](INSTALL.es.md) · [Deutsch](INSTALL.de.md) · [Français](INSTALL.fr.md) · [Українська](INSTALL.uk.md) · [Қазақша](INSTALL.kk.md) · [Nederlands](INSTALL.nl.md) · [Polski](INSTALL.pl.md)

**MODX MCP 1.2.4 · MODX Revolution 2.8.x / 3.x · 193 / 192**

Kies het transportpakket voor uw MODX-hoofdversie. De MCP-client vereist Node.js 18 of hoger.

## 1. MODX 2 / MODX 3

- [Pakket voor MODX 2](https://github.com/rumata-estor/modx3-mcp/releases/download/v1.2.4/modx2mcp-1.2.4-pl.transport.zip) — MODX 2.8.x
- [Pakket voor MODX 3](https://github.com/rumata-estor/modx3-mcp/releases/download/v1.2.4/modx3mcp-1.2.4-pl.transport.zip) — MODX 3.x
- [SHA256SUMS](https://github.com/rumata-estor/modx3-mcp/releases/download/v1.2.4/SHA256SUMS)

## 2. Snel beginnen

1. Meld u aan bij MODX Manager en installeer het juiste ZIP-bestand via Pakketbeheer.
2. Open ‘Componenten → MODX MCP’. Controleer HTTPS, bevoegdheden en het API-token; nieuwe installaties staan standaard uit.
3. Schakel `modxmcp.enabled` pas in nadat u de toegang hebt beveiligd. Bewaar het token in de geheime clientconfiguratie.

## 3. Node.js / MCP

Configureer uw MCP-host met stdio-ondersteuning zoals in het voorbeeld. Vervang het voorbeelddomein en -token; gebruik de gepubliceerde tag v1.2.4.

```json
{
  "mcpServers": {
    "modx": {
      "command": "npx",
      "args": ["-y", "github:rumata-estor/modx3-mcp#v1.2.4"],
      "env": {
        "MODX_MCP_SITE_URL": "https://example.com/assets/components/modxmcp/api.php",
        "MODX_MCP_TOKEN": "YOUR_SITE_PRIVATE_TOKEN"
      }
    }
  }
}
```

## 4. Verbinding controleren

Herstart uw MCP-client en voer eerst een leesactie uit, zoals `modx_system_info` of `modx_project_overview`. Een GET-statusantwoord bewijst geen geslaagde tokenauthenticatie.

## Bestaande installaties bijwerken

Normale upgrades van `modx2mcp` en `modx3mcp` behouden de instellingen en het API-token. Heeft een MODX 2-site nog het oude pakket `modxmcp`, dan gaat het om een andere pakketidentiteit: maak eerst een back-up van configuratie en token voordat u dat pakket verwijdert.

## Problemen oplossen

- `401`: `MODX_MCP_TOKEN` / `modxmcp.api_token`
- `403`: `modxmcp.enabled`, `modxmcp.require_https`, `modxmcp.allowed_ips`
- `404`: `/assets/components/modxmcp/api.php`
- `413`: `modxmcp.max_payload_bytes`
- `500`: MODX error log and `error_id`.

**Volledige technische naslagwerken:** [API-parameters (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/API.md) · [beveiliging (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/SECURITY.md) · [ontwikkeling (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/DEVELOPMENT.md). De gedetailleerde technische tabellen zijn voorlopig in het Engels.
