# Beveiliging en vertrouwensmodel — MODX MCP

[English](SECURITY.md) · [Русский](SECURITY.ru.md) · [Español](SECURITY.es.md) · [Deutsch](SECURITY.de.md) · [Français](SECURITY.fr.md) · [Українська](SECURITY.uk.md) · [Қазақша](SECURITY.kk.md) · [Nederlands](SECURITY.nl.md) · [Polski](SECURITY.pl.md)

[Installatie](INSTALL.nl.md) · [Mogelijkheden](CAPABILITIES.nl.md) · [API](API.nl.md)

Deze handleiding beschrijft de vertrouwensgrens van de openbare **MODX MCP 1.2.5**, de beveiligingsinstellingen en belangrijke beperkingen. Ze beschrijft geen externe infrastructuur voor agentbeheer.

## De belangrijkste regel

**Behandel het MODX MCP-API-token als inloggegevens met beheerdersrechten.** Alleen kennis van de endpoint-URL of gedocumenteerde toolnamen geeft geen toestemming voor een verzoek. Een geldig `X-MCP-Token` kan echter ingrijpende sitewijzigingen mogelijk maken.

Na authenticatie van een verzoek selecteert de server een serviceaccount met MODX-**`sudo`**-rechten. Met `modxmcp.service_user_id` kunt u een actief `sudo`-account opgeven; bij de standaardwaarde nul wordt de eerste actieve `sudo`-gebruiker geselecteerd. Dit is **geen** autorisatie per externe gebruiker op basis van de individuele managerrechten van de operator.

Een tokenhouder kan mogelijk snippets, plugins, templates, resources, instellingen en andere gevoelige inhoud wijzigen. Het bewerken van PHP in uitvoerbare MODX-elementen kan leiden tot uitvoering van willekeurige PHP-code met de rechten van het webapplicatieproces.

## Beveiligingen van het PHP-endpoint

| Maatregel | Gedrag en beperkingen |
| --- | --- |
| Token | Vereist `X-MCP-Token`; wordt gecontroleerd tegen de instelling `modxmcp.api_token`. |
| HTTPS | `modxmcp.require_https` is standaard ingeschakeld; controleer de configuratie van de TLS-terminatie. |
| IP-toegangslijst | `modxmcp.allowed_ips` vergelijkt `REMOTE_ADDR` met exacte IP-adressen of IPv4-CIDR-bereiken; leeg staat elk adres van de verbindende partij toe. |
| Reverse proxy | `modxmcp.trust_proxy_https` is standaard uitgeschakeld; schakel dit alleen in achter een vertrouwde, correct geconfigureerde reverse proxy. |
| Hoofdschakelaar | `modxmcp.enabled` schakelt geauthenticeerde API-bewerkingen uit wanneer de instelling uit staat. |
| Groottelimiet | `modxmcp.max_payload_bytes` beperkt de grootte van inkomende JSON-payloads. |
| Uitwijkmogelijkheid via processors | `modxmcp.allow_run_processor` is standaard uitgeschakeld. |
| Functiegroepen | `modxmcp.disabled_groups` schakelt geselecteerde toolgroepen uit. |

**Uitgeschakelde toolgroepen vormen geen volledige autorisatiegrens.** Standaardtools voor het maken, bijwerken en verwijderen van systeeminstellingen weigeren sleutels die beginnen met `modxmcp.`, inclusief pogingen om andere instellingen naar dat voorvoegsel te hernoemen. Tokenrotatie blijft een afzonderlijke bevoorrechte handeling. Dit is geen sandbox: een geauthenticeerde aanroeper die uitvoerbare PHP kan bewerken (of expliciet ingeschakelde processors kan aanroepen) kan nog steeds de sitebeveiliging beïnvloeden. Voor verschillende operators zijn onafhankelijke autorisatiecontroles aan de serverzijde vereist.

Een correct geauthenticeerd endpoint is niet hetzelfde als ingebouwde begrenzing van de verzoekfrequentie. Als uw implementatie zulke limieten vereist, dwing die dan af op het niveau van de reverse proxy of webserver.

## Standaard Node.js-MCP-client versus directe HTTP-aanroepen

De meegeleverde Node.js-client bevat extra beveiligingen voor sommige wijzigingen, zoals back-ups van bepaalde objecten vóór wijzigingen, expliciete toestemming via omgevingsvariabelen voor riskante bewerkingen en coördinatie van aanroepen. **Aangepaste clients die rechtstreeks naar `api.php` posten, voeren deze clientbeveiligingen niet uit.**

De PHP-server implementeert voor sommige wijzigingen ook vergrendelingen en controles van voorafgaande voorwaarden, plus auditregistraties. Hierdoor wordt directe HTTP-toegang niet gelijkwaardig aan gebruik van de standaard MCP-client.

Als u een eigen HTTP-integratie schrijft, implementeer dan uw eigen back-ups, autorisatie, bevestigingen en herstelprocedures.

## Aanbevolen productieconfiguratie

1. Gebruik HTTPS met een geldig certificaat.
2. Bewaar het token in een beveiligde clientconfiguratie of een geheimenbeheerder. Neem het nooit op in URL’s, openbare code, schermafbeeldingen, supporttickets of logboeken.
3. Beperk waar mogelijk de toegang tot het endpoint tot bekende client-IP-adressen of een beveiligd netwerk/proxy. Controleer welk adres PHP daadwerkelijk ziet in `REMOTE_ADDR`.
4. Houd `modxmcp.allow_run_processor = 0`, tenzij het specifiek nodig is.
5. Schakel ongebruikte functiegroepen uit, maar besef dat dit geen toegangscontrole per gebruiker is.
6. Laat `modxmcp.debug` uitgeschakeld in productie.
7. Onderhoud onafhankelijke, geteste back-ups van de MODX-site en database.
8. Gebruik `dry_run` bij destructieve bulktools wanneer beschikbaar; keur ingrijpende wijzigingen in code, rechten en instellingen handmatig goed.
9. Roteer het API-token bij een vermoeden van compromittering; werk de configuratie van elke geautoriseerde client onmiddellijk bij.

## Geheimen en privégegevens in toolantwoorden

`modx_list_system_settings` en `modx_get_system_setting` kunnen waarden met **API-tokens en andere geheimen** retourneren. Stuur volledige resultaten niet door naar openbare logboeken, probleemmeldingen, analysepijplijnen of een extern model zonder het vertrouwelijkheidsrisico te beoordelen.

Ook gelezen bestanden, foutlogboeken, gebruikersrecords en bestelgegevens van webwinkels kunnen privégegevens of gevoelige informatie bevatten.

## Reikwijdte van de bescherming

Documentatie kan niet voorkomen dat een AI-client een verzoek verkeerd begrijpt. Eén wijziging in een gedeelde snippet, plugin of template kan meerdere pagina’s beïnvloeden. Elke toepassing die verschillende rechten aan meerdere operators toekent, moet die rechten binnen haar vertrouwde servergrens afdwingen, niet uitsluitend via promptinstructies of een verborgen toollijst.

Als u een kwetsbaarheid ontdekt, plaats dan geen werkende inloggegevens of klantgegevens in een openbaar GitHub-issue. Trek blootgestelde geheimen in en verstrek voldoende opgeschoonde informatie zodat beheerders het probleem veilig kunnen reproduceren.

## Gelijktijdige wijzigingen van resource-TV’s en CAS v2

Vanaf het gepubliceerde transportpakket 1.2.3 op MODX 2 en MODX 3 meldt `get_site_state` de waarde `precondition_version=2` voor TV-CAS aan de Connector-zijde. Een beschermd verzoek `modx_update_resource_tvs` bevat door de Runtime beheerde `_runtime_preconditions`: de waargenomen siterevisie, strikte afdwinging van die revisie en de map `expected_tvs` met precies de TV-namen die worden gewijzigd. De Connector controleert deze voorwaarden onder zijn vergrendeling voor sitewijzigingen en weigert verouderde waarden in plaats van ze stilzwijgend te overschrijven.

**Beperking:** het onbewerkte MCP-schema vereist deze interne beveiliging niet, dus een onbeschermde directe aanroep mag **niet** worden voorgesteld als veilig bij gelijktijdig werkende agents. Gebruikers van Site Agent moeten de beoordeelde Runtime-route `modx-tv-change` of de Executor-route gebruiken. Mislukte CAS-aanroepen vereisen opnieuw uitlezen en een nieuw plan, geen blinde herhalingen.

## Implementatie

- [PHP-endpoint en tokenvalidatie](https://github.com/rumata-estor/modx3-mcp/blob/main/core/components/modxmcp/endpoint/api.common.php)
- [Standaardinstellingen](https://github.com/rumata-estor/modx3-mcp/blob/main/_build/data/transport.settings.php)
- [Standaard MCP-client](https://github.com/rumata-estor/modx3-mcp/blob/main/client/index.js)
- [Actiedispatcher aan de serverzijde](https://github.com/rumata-estor/modx3-mcp/blob/main/core/components/modxmcp/model/modxmcp.class.php)
