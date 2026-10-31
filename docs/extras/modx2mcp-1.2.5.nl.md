# Pakket voor MODX 2 — MODX MCP 1.2.5

[English](modx2mcp-1.2.5.md) · [Русский](modx2mcp-1.2.5.ru.md) · [Español](modx2mcp-1.2.5.es.md) · [Deutsch](modx2mcp-1.2.5.de.md) · [Français](modx2mcp-1.2.5.fr.md) · [Українська](modx2mcp-1.2.5.uk.md) · [Қазақша](modx2mcp-1.2.5.kk.md) · [Nederlands](modx2mcp-1.2.5.nl.md) · [Polski](modx2mcp-1.2.5.pl.md)

[Installatie](../INSTALL.nl.md) · [Beveiliging](../SECURITY.nl.md) · [Instellingen](../SETTINGS.nl.md) · [GitHub source](https://github.com/rumata-estor/modx3-mcp/tree/main)

**MODX Revolution 2.8.x · 1.2.5-pl**

MODX MCP verbindt AI-assistenten met MODX-websites via het Model Context Protocol. De PHP-component biedt een geauthenticeerde HTTP-API en een lokale Node.js-client stelt die beschikbaar aan MCP-toepassingen via stdio. Een aparte orkestratieserver is niet nodig.

## Downloaden

**Status:** de 1.2.5-transport-ZIPs en SHA-256-controlesommen zijn nog niet gepubliceerd. [Laatst gepubliceerde versie: 1.2.4](https://github.com/rumata-estor/modx3-mcp/releases/tag/v1.2.4)

## Snel beginnen

1. Meld u aan bij MODX Manager en installeer het juiste ZIP-bestand via Pakketbeheer.
2. Open ‘Componenten → MODX MCP’. Controleer HTTPS, bevoegdheden en het API-token; nieuwe installaties staan standaard uit.
3. Schakel `modxmcp.enabled` pas in nadat u de toegang hebt beveiligd. Bewaar het token in de geheime clientconfiguratie.

## Bestaande installaties bijwerken

Normale upgrades van `modx2mcp` en `modx3mcp` behouden de instellingen en het API-token. Heeft een MODX 2-site nog het oude pakket `modxmcp`, dan gaat het om een andere pakketidentiteit: maak eerst een back-up van configuratie en token voordat u dat pakket verwijdert.

## Beveiliging en vertrouwensmodel

**Het API-token is een beheerdersgeheim.** Geauthenticeerde aanvragen worden uitgevoerd als een MODX-servicegebruiker met sudo-rechten. Met een geldig token zijn ingrijpende aanpassingen mogelijk, ook aan uitvoerbare PHP-code.

**Volledige technische naslagwerken:** [API-parameters (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/API.md) · [beveiliging (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/SECURITY.md) · [ontwikkeling (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/DEVELOPMENT.md). De gedetailleerde technische tabellen zijn voorlopig in het Engels.

## Nieuw in 1.2.5-pl

Zes nieuwe MCP-tools beheren MODX-elementcategorieën (modCategory): tonen, bekijken, aanmaken, hernoemen of verplaatsen, veilig verwijderen en toewijzen aan chunks, snippets, templates, TVs en plugins.

Verwijderen controleert subcategorieën, gekoppelde elementen en propertysets. De resourceboom en miniShop2 blijven ongewijzigd.

**199 serveracties / 198 openbare MCP-tools**. MODX Revolution 2.8.x / 3.x. Normale updates behouden instellingen en API-token.
