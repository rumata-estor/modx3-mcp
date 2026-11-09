# Systeeminstellingen — MODX MCP

[English](SETTINGS.md) · [Русский](SETTINGS.ru.md) · [Español](SETTINGS.es.md) · [Deutsch](SETTINGS.de.md) · [Français](SETTINGS.fr.md) · [Українська](SETTINGS.uk.md) · [Қазақша](SETTINGS.kk.md) · [Nederlands](SETTINGS.nl.md) · [Polski](SETTINGS.pl.md)

[Installatie](INSTALL.nl.md) · [Beveiliging](SECURITY.nl.md)

Voor **MODX MCP 1.2.5** op MODX 2.8.x en 3.x. Dit document behandelt alle **16 systeeminstellingen** die beide transportpakketten definiëren. De standaardwaarden komen uit `_build/data/transport.settings.php`; het gedrag is gecontroleerd aan de hand van de PHP-implementatie.

In de MODX-manager: **Systeem → Systeeminstellingen**, namespace `modxmcp`. Via **Componenten → MODX MCP** kunt u ook Audit log, Auto-static en functiegroepen wijzigen en het API-token veilig bekijken/kopiëren. Wijzig andere opties onder Systeeminstellingen. Vernieuw waar nodig de MODX-caches en vernieuw na wijzigingen de beschikbare functies in de MCP-client.

**Beveiliging:** bewerkingen die met het token zijn geautoriseerd, worden uitgevoerd onder een MODX-**servicegebruiker met sudo-rechten**. Houd het token geheim. Functieschakelaars **vormen geen grens voor toegangsrechten**.

## Verbinding en identiteit

| Instelling | Standaardwaarde | Waarden en gedrag |
| --- | --- | --- |
| `modxmcp.enabled` | `0` | `0`: API-POST-acties worden geweigerd. `1`: API ingeschakeld. Het beperkte `GET`-endpoint voor status/versie werkt ook met `0` als aan de HTTPS-voorwaarden is voldaan. Schakel dit pas in wanneer de toegangscontroles gereed zijn. |
| `modxmcp.api_token` | Lege pakketwaarde; bij installatie wordt een willekeurig token gegenereerd **als het ontbreekt** | Geheime tekenreeks die in de header `X-MCP-Token` wordt verzonden. Ontbrekende/onjuiste tokens leveren `401` op. Normale upgrades behouden het token. Beheerders met de permissie `settings` kunnen **Tonen** / **Kopiëren** gebruiken. Tokenrotatie maakt bestaande clientinloggegevens ongeldig. |
| `modxmcp.service_user_id` | `0` | `0` selecteert de eerste **actieve MODX-gebruiker met sudo-rechten**, gerangschikt op ID; een positieve ID vereist een bestaand actief sudo-account. Kies voor voorspelbaar gedrag een specifiek serviceaccount. Dit is niet de identiteit van de externe MCP-client. |

## Netwerk en beveiliging

| Instelling | Standaardwaarde | Waarden en gedrag |
| --- | --- | --- |
| `modxmcp.require_https` | `1` | `0`/`1`. Vereist HTTPS, ook voor het `GET`-statusantwoord; anders wordt `403` geretourneerd. Houd dit op `1` voor uitgerolde sites. |
| `modxmcp.trust_proxy_https` | `0` | `0`/`1`. Indien ingeschakeld wordt `X-Forwarded-Proto: https` geaccepteerd als bewijs van HTTPS. Schakel dit alleen in achter een vertrouwde reverse proxy die door de client aangeleverde headers overschrijft. |
| `modxmcp.allowed_ips` | Leeg | Door komma’s gescheiden exacte IP-adressen en **IPv4 CIDR**, bijvoorbeeld `203.0.113.5,198.51.100.0/24`. Leeg staat elke bron met een geldig token toe. Controleert `REMOTE_ADDR`, **niet** `X-Forwarded-For`. Exacte IPv6-overeenkomsten werken; IPv6 CIDR niet. Onjuiste waarden veroorzaken `403`. |
| `modxmcp.disabled_groups` | `versionx,virtualpage,minishop2,migx,access,property_sets,contexts,package_management,namespaces,lexicon` | Door komma’s gescheiden uitgeschakelde groepen: `versionx`, `virtualpage`, `minishop2`, `migx`, `clientconfig`, `access`, `property_sets`, `contexts`, `package_management`, `namespaces`, `lexicon`. Leeg schakelt alle verder ondersteunde groepen in. **Geen beveiligingsgrens tussen rollen**; kernacties kunnen een site nog steeds wijzigen. |
| `modxmcp.allow_run_processor` | `0` | `0`/`1`. Staat de algemene bewerking `run_processor` toe, inclusief aangepaste MODX-processors. Dit vergroot de reikwijdte van beheerdersbewerkingen: houd het uitgeschakeld tenzij het specifiek nodig is. |
| `modxmcp.allow_root_filesystem_read` | `0` | `0`/`1`. Staat bladeren door en lezen van de hoofdmediabron `Filesystem` toe. Kan meer serverbestanden blootleggen. Houd dit uitgeschakeld tenzij nodig. |
| `modxmcp.component_code_roots` | `core/components,assets/components` | Door komma’s gescheiden mappen met componentbroncode, relatief ten opzichte van `base_path` of absoluut. Ondersteunt de placeholders `{core_path}`, `{assets_path}`, `{base_path}`. Beperkt **uitsluitend de inspectie van componentcode**, niet elke MCP-bestandssysteemactie. Breid dit niet uit tot de hoofdmap van het serverbestandssysteem. |

## Limieten

| Instelling | Standaardwaarde | Waarden en gedrag |
| --- | --- | --- |
| `modxmcp.max_payload_bytes` | `1048576` (1 MiB) | Maximale grootte in bytes van de JSON-verzoekbody voor HTTP-API-POST; overschrijding levert `413` op. Waarden `<=0` heffen deze controle op. Houd een positieve bovengrens aan. |
| `modxmcp.max_read_bytes` | `262144` (256 KiB) | Maximaal geretourneerd blok bij het lezen van bestanden; bij `<=0` wordt teruggevallen op 262144. **Geen** limiet voor de totale bestandsgrootte: leesacties kunnen `offset` en `bytes`/`length` gebruiken. |

## Gedrag en diagnostiek

| Instelling | Standaardwaarde | Waarden en gedrag |
| --- | --- | --- |
| `modxmcp.auto_static` | `0` | `0`/`1`. Indien ingeschakeld worden via MCP gemaakte/bijgewerkte chunks, snippets, templates en plugins statische bestanden onder `core/elements/`. Vereist passende schrijfrechten, back-ups en procedures voor uitrol. |
| `modxmcp.audit_log` | `1` | `0`/`1`. Registreert ondersteunde wijzigingsacties in MODX-logboeken en `core/components/modxmcp/logs/audit.log`. **Geen** volledige registratie van elk verzoek. Houd dit ingeschakeld voor productie, met toegangs- en groottebeperkingen. |
| `modxmcp.debug` | `0` | `0`/`1`. Indien ingeschakeld kunnen gedetailleerde interne uitzonderingen in HTTP-foutantwoorden verschijnen. Indien uitgeschakeld ontvangen aanroepers een algemene fout en `error_id`. Houd dit op `0` in productie. |

## Core-pad

| Instelling | Standaardwaarde | Waarden en gedrag |
| --- | --- | --- |
| `modxmcp.core_path` | `{core_path}components/modxmcp/` | Core-map van de component (accepteert ook `[[++core_path]]`). De HTTP-API gebruikt deze om de implementatie en versie te laden. Wijzig dit alleen voor geverifieerde afwijkende installaties; onjuiste waarden maken de API onbruikbaar. |

## Veilige eerste verbinding

1. Installeer het transportpakket dat overeenkomt met uw hoofdversie van MODX. Nieuwe installaties starten met `modxmcp.enabled=0`; het token wordt automatisch gegenereerd en bestaande tokens blijven behouden bij normale upgrades.
2. Configureer HTTPS, eventueel IP-beperkingen, reverse-proxyinstellingen en `service_user_id`.
3. Gebruik **Kopiëren** in het managerdashboard om het API-token in de geheimenopslag van uw MCP-client te plaatsen. Maak het niet openbaar.
4. Schakel alleen de benodigde functies in. Laat `allow_run_processor` en `allow_root_filesystem_read` uitgeschakeld tenzij er een goede reden is om ze in te schakelen.
5. Schakel de API in en controleer een **geauthenticeerde alleen-lezenactie**. Een `GET`-antwoord met versie/status bewijst **niet** dat het clienttoken geldig is.
