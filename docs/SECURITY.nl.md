# Beveiliging en vertrouwensmodel — MODX MCP

[English](SECURITY.md) · [Русский](SECURITY.ru.md) · [Español](SECURITY.es.md) · [Deutsch](SECURITY.de.md) · [Français](SECURITY.fr.md) · [Українська](SECURITY.uk.md) · [Қазақша](SECURITY.kk.md) · [Nederlands](SECURITY.nl.md) · [Polski](SECURITY.pl.md)

**Het API-token is een beheerdersgeheim.** Geauthenticeerde aanvragen worden uitgevoerd als een MODX-servicegebruiker met sudo-rechten. Met een geldig token zijn ingrijpende aanpassingen mogelijk, ook aan uitvoerbare PHP-code.

**Belangrijk:** functiegroepen uitschakelen vervangt geen autorisatie op de server. Rechtstreekse HTTP-clients nemen de veiligheidsmaatregelen van de officiële Node.js-client niet automatisch over.

## Beveiliging en vertrouwensmodel

1. Gebruik HTTPS en controleer de TLS-instellingen van een reverse proxy.
2. Behandel het API-token als beheerdersgegevens en publiceer het nooit.
3. Beperk toegang indien mogelijk tot bekende IP-adressen of een beveiligd netwerk.
4. Houd `modxmcp.allow_run_processor=0` en `modxmcp.allow_root_filesystem_read=0` tenzij dit expliciet nodig is.
5. Uitgeschakelde functiegroepen vormen geen gebruikersgebonden toegangsbeheer.
6. Zet gedetailleerde foutmeldingen in productie uit (`modxmcp.debug=0`).
7. Maak onafhankelijke back-ups en gebruik `dry_run` vóór risicovolle wijzigingen.

## HTTP / MCP

- `POST` JSON uses `X-MCP-Token` over HTTPS.
- `GET` health/version is **not** proof that the client token is valid.
- `modxmcp.disabled_groups` is **not** an independent authorization boundary.
- External scripts calling `api.php` directly do not inherit Node.js client safeguards such as some backups and confirmations.

**Volledige technische naslagwerken:** [API-parameters (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/API.md) · [beveiliging (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/SECURITY.md) · [ontwikkeling (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/DEVELOPMENT.md). De gedetailleerde technische tabellen zijn voorlopig in het Engels.
