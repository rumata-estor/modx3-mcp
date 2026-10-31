# MODX MCP — MODX beheren met AI-assistenten

[English](README.md) · [Русский](README.ru.md) · [Español](README.es.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Українська](README.uk.md) · [Қазақша](README.kk.md) · [Nederlands](README.nl.md) · [Polski](README.pl.md)

**MODX MCP 1.2.5 · MODX Revolution 2.8.x / 3.x · 199 / 198**

MODX MCP verbindt AI-assistenten met MODX-websites via het Model Context Protocol. De PHP-component biedt een geauthenticeerde HTTP-API en een lokale Node.js-client stelt die beschikbaar aan MCP-toepassingen via stdio. Een aparte orkestratieserver is niet nodig.


## Voor wie is dit project?

Voor MODX-ontwikkelaars, webbureaus en beheerders die websites gecontroleerd met AI willen onderhouden.

## Mogelijkheden en hulpmiddelen

- Resources, templates, chunks, snippets, plugins en TV-velden zoeken, lezen en wijzigen.
- MODX-elementcategorieën maken, hernoemen, verplaatsen, veilig verwijderen en toewijzen.
- Afhankelijkheden, verbroken verwijzingen en mogelijk ongebruikte code onderzoeken met de grafiek.
- Werken met systeeminstellingen, mediabronnen, pakketten en ondersteunde uitbreidingen.
- Wijzigingen beveiligen met voorwaarden, proefruns en back-ups.

## Snel beginnen

Kies het transportpakket voor uw MODX-hoofdversie. De MCP-client vereist Node.js 18 of hoger.

- **MODX 2.8.x:** [Pakket voor MODX 2](https://github.com/rumata-estor/modx3-mcp/releases/download/v1.2.4/modx2mcp-1.2.4-pl.transport.zip)
- **MODX 3.x:** [Pakket voor MODX 3](https://github.com/rumata-estor/modx3-mcp/releases/download/v1.2.4/modx3mcp-1.2.4-pl.transport.zip)
- [GitHub v1.2.4](https://github.com/rumata-estor/modx3-mcp/releases/tag/v1.2.4) · [SHA256SUMS](https://github.com/rumata-estor/modx3-mcp/releases/download/v1.2.4/SHA256SUMS)

> De downloadlinks hierboven verwijzen naar de laatst gepubliceerde versie 1.2.4. De 1.2.5-transport-ZIPs zijn nog niet gebouwd.

Meld u aan bij MODX Manager en installeer het juiste ZIP-bestand via Pakketbeheer.

Open ‘Componenten → MODX MCP’. Controleer HTTPS, bevoegdheden en het API-token; nieuwe installaties staan standaard uit.

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

Schakel `modxmcp.enabled` pas in nadat u de toegang hebt beveiligd. Bewaar het token in de geheime clientconfiguratie.

## Beveiliging en vertrouwensmodel

**Het API-token is een beheerdersgeheim.** Geauthenticeerde aanvragen worden uitgevoerd als een MODX-servicegebruiker met sudo-rechten. Met een geldig token zijn ingrijpende aanpassingen mogelijk, ook aan uitvoerbare PHP-code.

**Belangrijk:** functiegroepen uitschakelen vervangt geen autorisatie op de server. Rechtstreekse HTTP-clients nemen de veiligheidsmaatregelen van de officiële Node.js-client niet automatisch over.

## Documentatie

[Installatie](docs/INSTALL.nl.md) · [Beveiliging](docs/SECURITY.nl.md) · [Instellingen](docs/SETTINGS.nl.md) · [Mogelijkheden](docs/CAPABILITIES.nl.md) · [API](docs/API.nl.md)

**Volledige technische naslagwerken:** [API-parameters (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/API.md) · [beveiliging (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/SECURITY.md) · [ontwikkeling (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/DEVELOPMENT.md). De gedetailleerde technische tabellen zijn voorlopig in het Engels.
