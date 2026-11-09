# MODX MCP installeren en verbinden

[English](INSTALL.md) · [Русский](INSTALL.ru.md) · [Español](INSTALL.es.md) · [Deutsch](INSTALL.de.md) · [Français](INSTALL.fr.md) · [Українська](INSTALL.uk.md) · [Қазақша](INSTALL.kk.md) · [Nederlands](INSTALL.nl.md) · [Polski](INSTALL.pl.md)

[Mogelijkheden](CAPABILITIES.nl.md) · [API-referentie](API.nl.md) · [Beveiliging](SECURITY.nl.md)

Met MODX MCP kan een standaard MCP-compatibele AI-client werken met **MODX Revolution 2.8.x en 3.x**. Voor een normale installatie zijn een MODX-transportpakket, **Node.js 18+** op de computer waarop uw MCP-client draait en een toepassing die **stdio**-MCP-servers kan starten vereist.

**Een afzonderlijke orkestratieserver is niet nodig.** De MODX-component biedt een HTTP-API op uw site; het lokale proces `modx-mcp` stelt deze API via `stdio` beschikbaar aan MCP-clients. Het PHP-endpoint is zelf geen directe MCP-stdio-server.

## 1. Kies het MODX-pakket

- **MODX 2.8.x:** `modx2mcp-1.2.5-pl.transport.zip`.
- **MODX 3.x:** `modx3mcp-1.2.5-pl.transport.zip`.

> **Status:** De broncode van 1.2.5 is voorbereid, maar de transport-ZIPs zijn nog niet gepubliceerd. Gebruik voor installatie [versie 1.2.4](https://github.com/rumata-estor/modx3-mcp/releases/tag/v1.2.4).

Beide varianten gebruiken hetzelfde openbare toolcontract en dezelfde Node.js-client. Download het juiste pakket via [GitHub Releases](https://github.com/rumata-estor/modx3-mcp/releases) of een ondersteunde MODX-pakketprovider.

**Bestaande MODX 2-installaties:** bij de migratie van de oude pakketidentiteit `modxmcp` naar `modx2mcp` wordt aanbevolen het oude pakket volledig te deïnstalleren en vervolgens `modx2mcp` te installeren. Een volledige deïnstallatie verwijdert de oude instellingen `modxmcp.*` en het API-token. Bewaar waar nodig de relevante configuratie en werk daarna de inloggegevens van uw client bij. Latere normale upgrades van `modx2mcp` behouden het huidige token.

### Managerdashboard

Onder **Componenten → MODX MCP** kunt u de API-URL kopiëren, het bestaande token alleen tonen/kopiëren als uw manageraccount de permissie **settings** heeft, optionele functiegroepen in- of uitschakelen en **Auto-static** / **Audit log** configureren. Wijzigingen in instellingen en functies worden pas van kracht nadat u op de bijbehorende opslagknoppen hebt geklikt. De **afhankelijkheidsgraaf** is bereikbaar via de graaflink; met het muiswiel zoomt u op het canvas, terwijl u door het omliggende paneel kunt scrollen om filters en details te bekijken. **Token opnieuw genereren** maakt alle bestaande clientinloggegevens ongeldig; gewone pakketupdates doen dat niet. De melding “MCP ingeschakeld” op de pagina is geen livecontrole van een specifieke clientverbinding.

## 2. Installeer het transportpakket

1. Meld u aan bij de MODX-manager met een account dat pakketten mag installeren.
2. Open Pakketbeheer, upload het transport-ZIP-bestand voor uw hoofdversie van MODX en installeer het.
3. Open de MODX MCP-component of Systeeminstellingen met de namespace `modxmcp`.
4. Controleer `modxmcp.api_token` en de netwerkbeperkingen. Nieuwe installaties starten met `modxmcp.enabled = 0`; schakel de component handmatig in nadat u de toegang hebt beveiligd.
5. Kopieer het token naar uw **MCP-clientconfiguratie**. Behandel het als een geheim, nooit als een openbaar voorbeeld.

Het installatieprogramma genereert een cryptografisch willekeurig token als de instelling leeg is. Een gewone upgrade behoudt een bestaand token.

## 3. Zoek het site-endpoint

De standaard-URL is:

```text
https://example.com/assets/components/modxmcp/api.php
```

Gebruik uw eigen domein; bij aangepaste implementaties kan het werkelijke pad afwijken. **HTTPS** wordt aanbevolen en is standaard verplicht.

`GET` retourneert een beperkt, niet-geauthenticeerd antwoord met de componentstatus en versie. Beheerverzoeken gebruiken JSON-`POST` met `X-MCP-Token`.

## 4. Configureer uw MCP-host

Installeer **Node.js 18+** met npm/`npx` op de computer waarop uw AI-toepassing draait. Voeg de MCP-server toe aan de configuratie van uw host (de locatie en de ondersteunde configuratiestructuur hangen af van uw toepassing):

```json
{
  "mcpServers": {
    "modx": {
      "command": "npx",
      "args": [
        "-y",
        "github:rumata-estor/modx3-mcp#v1.2.4"
      ],
      "env": {
        "MODX_MCP_SITE_URL": "https://example.com/assets/components/modxmcp/api.php",
        "MODX_MCP_TOKEN": "YOUR_SITE_PRIVATE_TOKEN"
      }
    }
  }
}
```

`MODX_MCP_SITE_URL` is het PHP-HTTP-API-endpoint. `MODX_MCP_TOKEN` is het token voor deze specifieke site. Pin op productiesystemen de **gepubliceerde releasetag** in plaats van `main` te volgen. Controleer of uw host het starten van een lokale MCP-server via `stdio` ondersteunt.

## 5. Controleer de verbinding

Herstart de MCP-integratie of vernieuw de servers van de host. Zoek de server `modx` en tools met het voorvoegsel `modx_`. Begin met een alleen-lezenverzoek, zoals:

```text
Show the MODX version and an overview of the site structure. Make no changes.
```

Hiervoor kunnen `modx_system_info` en `modx_project_overview` worden gebruikt. Uw host kan minder dan 192 tools tonen, afhankelijk van de serverbuild, uitgeschakelde groepen en geïnstalleerde uitbreidingen.

De [volledige referentie voor systeeminstellingen](SETTINGS.nl.md) beschrijft **alle 16 instellingen**, hun standaardwaarden, gedrag en gevolgen voor de beveiliging.

## 6. Te controleren beveiligingsinstellingen

| Instelling | Betekenis |
| --- | --- |
| `modxmcp.enabled` | De API in- of uitschakelen. |
| `modxmcp.api_token` | Geheime API-inloggegevens. |
| `modxmcp.require_https` | HTTPS verplicht stellen; standaard ingeschakeld. |
| `modxmcp.allowed_ips` | Optionele lijst met toegestane IP-adressen en IPv4-CIDR-bereiken; leeg staat elke bron toe. |
| `modxmcp.trust_proxy_https` | Vertrouw de HTTPS-header van een reverse proxy alleen achter een correct geconfigureerde proxy. |
| `modxmcp.disabled_groups` | Bepaalde toolgroepen uitschakelen; **geen beveiligingsgrens tussen rollen**. |
| `modxmcp.allow_run_processor` | Directe aanroepen van MODX-processors toestaan; standaard uitgeschakeld. |
| `modxmcp.max_payload_bytes` | Limiet voor de verzoekgrootte. |
| `modxmcp.debug` | Foutdetails; houd dit uitgeschakeld in productie. |

**Belangrijk:** verzoeken worden uitgevoerd als een MODX-servicegebruiker met `sudo`-rechten. Het uitlekken van een geldig token kan beheerderswijzigingen en aanpassing van uitvoerbare PHP in elementen mogelijk maken. Lees de [beveiligingshandleiding](SECURITY.nl.md).

## Problemen oplossen

| Symptoom | Controle |
| --- | --- |
| MCP-server kan niet starten | Node.js 18+, `npx`, pakketresolutie en hostconfiguratie. |
| `401 Unauthorized` | `MODX_MCP_TOKEN` moet overeenkomen met `modxmcp.api_token`; controleer of het token is vernieuwd. |
| `403` | De inschakelinstelling van de component, HTTPS, reverse-proxyconfiguratie en toegestaan IP-adres. |
| `404` | Endpointpad, componentinstallatie en webserverroutering. |
| `413` | Het verzoek overschreed `modxmcp.max_payload_bytes`. |
| Tool niet beschikbaar | Client-/serverversies, uitgeschakelde groepen, installatie van de vereiste uitbreiding. |
| `500` | MODX-foutlogboeken en `error_id`; toon debugberichten niet openbaar. |

Plaats API-tokens niet in querystrings of probleemmeldingen. Aangepaste HTTP-clients moeten `X-MCP-Token` via TLS verzenden.

## Volgende stappen

- [Mogelijkheden](CAPABILITIES.nl.md) — wat MODX MCP kan doen.
- [API-referentie](API.nl.md) — tools en invoerparameters.
- [Beveiliging](SECURITY.nl.md) — risico’s en beschermende configuratie.
- [Project-README](../README.nl.md) — projectoverzicht.
- [Validatie](VALIDATION.nl.md) — opmerkingen over compatibiliteitstests.
