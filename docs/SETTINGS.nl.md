# Systeeminstellingen — MODX MCP

[English](SETTINGS.md) · [Русский](SETTINGS.ru.md) · [Español](SETTINGS.es.md) · [Deutsch](SETTINGS.de.md) · [Français](SETTINGS.fr.md) · [Українська](SETTINGS.uk.md) · [Қазақша](SETTINGS.kk.md) · [Nederlands](SETTINGS.nl.md) · [Polski](SETTINGS.pl.md)

Alle 16 instellingen staan onder ‘Systeem → Systeeminstellingen’, namespace `modxmcp`. De standaardwaarden gelden voor beide MODX-versies.

**Belangrijk:** functiegroepen uitschakelen vervangt geen autorisatie op de server. Rechtstreekse HTTP-clients nemen de veiligheidsmaatregelen van de officiële Node.js-client niet automatisch over.

| Sleutel | Standaard | Beschrijving |
| --- | --- | --- |
| `modxmcp.enabled` | `0` | Schakelt geauthenticeerde API-acties in of uit; bij een nieuwe installatie uit. |
| `modxmcp.api_token` | `(generated)` | Geheim token in de header `X-MCP-Token`; normale updates behouden dit. |
| `modxmcp.service_user_id` | `0` | MODX-servicegebruiker; `0` kiest de eerste actieve gebruiker met sudo-rechten. |
| `modxmcp.require_https` | `1` | Vereist HTTPS; in productie ingeschakeld laten. |
| `modxmcp.trust_proxy_https` | `0` | Vertrouw HTTPS-headers alleen van een correct ingestelde reverse proxy. |
| `modxmcp.allowed_ips` | `(empty)` | Toegestane IP-adressen en IPv4-CIDR-blokken; leeg betekent geen IP-beperking. |
| `modxmcp.disabled_groups` | `versionx,virtualpage,minishop2,migx,access,property_sets,contexts,package_management,namespaces,lexicon` | Met komma’s gescheiden uitgeschakelde groepen; geen volledige beveiligingsgrens. |
| `modxmcp.allow_run_processor` | `0` | Staat algemene MODX-processors toe; standaard uitgeschakeld. |
| `modxmcp.allow_root_filesystem_read` | `0` | Staat het lezen van de root-mediabron ‘Filesystem’ toe; standaard uit. |
| `modxmcp.component_code_roots` | `core/components,assets/components` | Toegestane paden voor inspectie van componentcode; geen algemene bestandssandbox. |
| `modxmcp.max_payload_bytes` | `1048576` | Maximale grootte van een POST-JSON-body in bytes; overschrijding geeft 413. |
| `modxmcp.max_read_bytes` | `262144` | Maximaal aantal bytes per leesgedeelte van een bestand. |
| `modxmcp.auto_static` | `0` | Maakt automatisch statische bestanden voor elementen. |
| `modxmcp.audit_log` | `1` | Schrijft ondersteunde wijzigingen naar het auditlogboek; niet alle aanvragen. |
| `modxmcp.debug` | `0` | Toont interne foutdetails; in productie uitschakelen. |
| `modxmcp.core_path` | `{core_path}components/modxmcp/` | Corepad van de component; alleen wijzigen bij gecontroleerde speciale installaties. |

**Volledige technische naslagwerken:** [API-parameters (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/API.md) · [beveiliging (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/SECURITY.md) · [ontwikkeling (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/DEVELOPMENT.md). De gedetailleerde technische tabellen zijn voorlopig in het Engels.
