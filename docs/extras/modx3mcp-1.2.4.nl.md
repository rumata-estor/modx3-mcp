# Pakket voor MODX 3 — MODX MCP 1.2.4

[English](modx3mcp-1.2.4.md) · [Русский](modx3mcp-1.2.4.ru.md) · [Español](modx3mcp-1.2.4.es.md) · [Deutsch](modx3mcp-1.2.4.de.md) · [Français](modx3mcp-1.2.4.fr.md) · [Українська](modx3mcp-1.2.4.uk.md) · [Қазақша](modx3mcp-1.2.4.kk.md) · [Nederlands](modx3mcp-1.2.4.nl.md) · [Polski](modx3mcp-1.2.4.pl.md)

[Installatie](../INSTALL.nl.md) · [Beveiliging](../SECURITY.nl.md) · [Instellingen](../SETTINGS.nl.md) · [GitHub v1.2.4](https://github.com/rumata-estor/modx3-mcp/releases/tag/v1.2.4)

**MODX Revolution 3.x · 1.2.4-pl**

MODX MCP verbindt AI-assistenten met MODX-websites via het Model Context Protocol. De PHP-component biedt een geauthenticeerde HTTP-API en een lokale Node.js-client stelt die beschikbaar aan MCP-toepassingen via stdio. Een aparte orkestratieserver is niet nodig.

## Downloaden

[modx3mcp-1.2.4-pl.transport.zip](https://github.com/rumata-estor/modx3-mcp/releases/download/v1.2.4/modx3mcp-1.2.4-pl.transport.zip) · [SHA256SUMS](https://github.com/rumata-estor/modx3-mcp/releases/download/v1.2.4/SHA256SUMS)

## Snel beginnen

1. Meld u aan bij MODX Manager en installeer het juiste ZIP-bestand via Pakketbeheer.
2. Open ‘Componenten → MODX MCP’. Controleer HTTPS, bevoegdheden en het API-token; nieuwe installaties staan standaard uit.
3. Schakel `modxmcp.enabled` pas in nadat u de toegang hebt beveiligd. Bewaar het token in de geheime clientconfiguratie.

## Bestaande installaties bijwerken

Normale upgrades van `modx2mcp` en `modx3mcp` behouden de instellingen en het API-token. Heeft een MODX 2-site nog het oude pakket `modxmcp`, dan gaat het om een andere pakketidentiteit: maak eerst een back-up van configuratie en token voordat u dat pakket verwijdert.

## Beveiliging en vertrouwensmodel

**Het API-token is een beheerdersgeheim.** Geauthenticeerde aanvragen worden uitgevoerd als een MODX-servicegebruiker met sudo-rechten. Met een geldig token zijn ingrijpende aanpassingen mogelijk, ook aan uitvoerbare PHP-code.

**Volledige technische naslagwerken:** [API-parameters (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/API.md) · [beveiliging (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/SECURITY.md) · [ontwikkeling (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/DEVELOPMENT.md). De gedetailleerde technische tabellen zijn voorlopig in het Engels.
