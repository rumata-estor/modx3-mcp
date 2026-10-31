# MODX MCP — Sicherheits- und Vertrauensmodell

[English](SECURITY.md) · [Русский](SECURITY.ru.md) · [Español](SECURITY.es.md) · [Deutsch](SECURITY.de.md) · [Français](SECURITY.fr.md) · [Українська](SECURITY.uk.md) · [Қазақша](SECURITY.kk.md) · [Nederlands](SECURITY.nl.md) · [Polski](SECURITY.pl.md) · [Installation](INSTALL.de.md) · [Funktionsumfang](CAPABILITIES.de.md) · [API](API.de.md)

Dieser Leitfaden dokumentiert die Vertrauensgrenze der öffentlichen Version **MODX MCP 1.2.5**, ihre Sicherheitseinstellungen und wichtige Einschränkungen. Er beschreibt keine externe Infrastruktur zur Agentenverwaltung.

## Die wichtigste Regel

**Behandeln Sie das MODX-MCP-API-Token als administrativen Zugangsnachweis.** Allein die Kenntnis der Endpunkt-URL oder dokumentierter Tool-Namen berechtigt nicht zu einer Anfrage. Ein gültiges `X-MCP-Token` kann jedoch weitreichende Änderungen an der Website ermöglichen.

Nach der Authentifizierung einer Anfrage wählt der Server ein Dienstkonto mit MODX-**`sudo`**-Berechtigungen aus. Mit `modxmcp.service_user_id` lässt sich ein aktives `sudo`-Konto festlegen; beim Standardwert null wird der erste aktive `sudo`-Benutzer ausgewählt. Dies ist **keine** Autorisierung pro externem Benutzer auf Grundlage der individuellen Manager-Berechtigungen des Bedieners.

Ein Token-Inhaber kann potenziell Snippets, Plugins, Templates, Ressourcen, Einstellungen und andere sensible Inhalte verändern. Die Bearbeitung von PHP in ausführbaren MODX-Elementen kann zur Ausführung beliebigen PHP-Codes mit den Rechten des Webanwendungsprozesses führen.

## Schutzmaßnahmen des PHP-Endpunkts

| Schutzmaßnahme | Verhalten und Grenzen |
| --- | --- |
| Token | Verlangt `X-MCP-Token`; wird mit der Einstellung `modxmcp.api_token` verglichen. |
| HTTPS | `modxmcp.require_https` ist standardmäßig aktiviert; Konfiguration der TLS-Terminierung prüfen. |
| IP-Freigabeliste | `modxmcp.allowed_ips` vergleicht `REMOTE_ADDR` mit exakten IPs oder IPv4-CIDR-Bereichen; leer erlaubt jede Gegenstellenadresse. |
| Reverse-Proxy | `modxmcp.trust_proxy_https` ist standardmäßig deaktiviert; nur hinter einem vertrauenswürdigen, korrekt konfigurierten Reverse-Proxy aktivieren. |
| Hauptschalter | `modxmcp.enabled` deaktiviert im ausgeschalteten Zustand authentifizierte API-Operationen. |
| Größenlimit | `modxmcp.max_payload_bytes` begrenzt die Größe eingehender JSON-Nutzdaten. |
| Direkter Prozessorzugriff | `modxmcp.allow_run_processor` ist standardmäßig deaktiviert. |
| Funktionsgruppen | `modxmcp.disabled_groups` deaktiviert ausgewählte Tool-Familien. |

**Deaktivierte Tool-Familien bilden keine vollständige Autorisierungsgrenze.** Die Standard-Tools zum Erstellen/Aktualisieren/Löschen von Systemeinstellungen weisen Schlüssel zurück, die mit `modxmcp.` beginnen, einschließlich Versuchen, andere Einstellungen auf dieses Präfix umzubenennen. Die Token-Rotation bleibt eine separate privilegierte Aktion. Dies ist keine Sandbox: Ein authentifizierter Aufrufer, der ausführbares PHP bearbeiten oder ausdrücklich aktivierte Prozessoren aufrufen kann, kann weiterhin die Sicherheit der Website beeinflussen. Verschiedene Bediener benötigen unabhängige serverseitige Autorisierungskontrollen.

Ein korrekt authentifizierter Endpunkt ist nicht gleichbedeutend mit einer integrierten Begrenzung der Anfragerate. Falls Ihre Installation solche Limits benötigt, setzen Sie sie auf Ebene des Reverse-Proxys oder Webservers durch.

## Standardmäßiger Node.js-MCP-Client gegenüber direkten HTTP-Aufrufen

Der mitgelieferte Node.js-Client enthält zusätzliche Schutzmaßnahmen für bestimmte Änderungen, etwa Sicherungen bestimmter Objekte vor Änderungen, ausdrückliche Freigaben riskanter Operationen über Umgebungsvariablen und die Koordination von Aufrufen. **Eigene Aufrufer, die direkt an `api.php` posten, führen diese clientseitigen Schutzmaßnahmen nicht aus.**

Der PHP-Server implementiert ebenfalls Sperren und Vorbedingungsprüfungen für bestimmte Änderungen sowie Audit-Einträge. Dadurch wird direkter HTTP-Zugriff jedoch nicht gleichwertig mit der Verwendung des standardmäßigen MCP-Clients.

Wenn Sie eine eigene HTTP-Integration schreiben, implementieren Sie eigene Sicherungen, Autorisierung, Bestätigungen und Wiederherstellungsverfahren.

## Empfohlene Produktionskonfiguration

1. Verwenden Sie HTTPS mit einem gültigen Zertifikat.
2. Speichern Sie das Token in einer geschützten Client-Konfiguration oder einer Geheimnisverwaltung. Betten Sie es niemals in URLs, öffentlichen Code, Screenshots, Support-Tickets oder Protokolle ein.
3. Beschränken Sie den Endpunktzugriff nach Möglichkeit auf bekannte Client-IPs oder ein abgesichertes Netzwerk bzw. einen abgesicherten Proxy. Prüfen Sie, welche Adresse PHP tatsächlich in `REMOTE_ADDR` sieht.
4. Lassen Sie `modxmcp.allow_run_processor = 0`, sofern es nicht ausdrücklich benötigt wird.
5. Deaktivieren Sie ungenutzte Funktionsgruppen, beachten Sie aber, dass dies keine benutzerbezogene Zugriffskontrolle ist.
6. Lassen Sie `modxmcp.debug` in Produktion deaktiviert.
7. Pflegen Sie unabhängige, getestete Sicherungen der MODX-Website und Datenbank.
8. Verwenden Sie bei destruktiven Massen-Tools nach Möglichkeit `dry_run`; genehmigen Sie Änderungen an Code, Berechtigungen und Einstellungen mit weitreichenden Auswirkungen manuell.
9. Rotieren Sie das API-Token bei Verdacht auf Kompromittierung; aktualisieren Sie umgehend jede autorisierte Client-Konfiguration.

## Geheimnisse und private Informationen in Tool-Antworten

`modx_list_system_settings` und `modx_get_system_setting` können Werte zurückgeben, die **API-Tokens und andere Geheimnisse** enthalten. Leiten Sie vollständige Ergebnisse nicht in öffentliche Protokolle, Fehlerberichte, Analyse-Pipelines oder an ein externes Modell weiter, ohne das Vertraulichkeitsrisiko zu bewerten.

Dateiinhalte, Fehlerprotokolle, Benutzerdatensätze und Bestelldaten aus dem E-Commerce können ebenfalls private oder sensible Informationen enthalten.

## Schutzumfang

Dokumentation kann nicht verhindern, dass ein KI-Client eine Anfrage missversteht. Eine einzige Änderung an einem gemeinsam verwendeten Snippet, Plugin oder Template kann mehrere Seiten betreffen. Jede Anwendung, die mehreren Bedienern unterschiedliche Rechte gewährt, muss diese innerhalb ihrer vertrauenswürdigen Servergrenze durchsetzen, nicht allein durch Prompt-Anweisungen oder eine ausgeblendete Tool-Liste.

Wenn Sie eine Schwachstelle entdecken, veröffentlichen Sie keine funktionierenden Zugangsdaten oder Kundendaten in einem öffentlichen GitHub-Issue. Widerrufen Sie offengelegte Geheimnisse und melden Sie ausreichend bereinigte Informationen, damit die Verantwortlichen das Problem sicher reproduzieren können.

## Nebenläufigkeit bei Ressourcen-TVs und CAS v2

Seit dem veröffentlichten Transportpaket 1.2.3 auf MODX 2 und MODX 3 meldet `get_site_state` für Connector-seitiges TV-CAS `precondition_version=2`. Eine geschützte Anfrage an `modx_update_resource_tvs` enthält vom Runtime verwaltete `_runtime_preconditions`: die beobachtete Website-Revision, die strikte Durchsetzung der Revision und die Zuordnung `expected_tvs` mit genau den zu ändernden TV-Namen. Der Connector prüft diese Bedingungen unter seiner Sperre für Website-Änderungen und weist veraltete Werte zurück, statt sie stillschweigend zu überschreiben.

**Einschränkung:** Das rohe MCP-Schema verlangt diese interne Absicherung nicht; ein ungeschützter direkter Aufruf darf daher **nicht** als sicher gegenüber gleichzeitig arbeitenden Agenten dargestellt werden. Site-Agent-Benutzer sollten den geprüften Runtime-Weg `modx-tv-change` oder den Executor-Weg verwenden. Fehlgeschlagene CAS-Aufrufe erfordern erneutes Lesen und einen neuen Plan, keine blinden Wiederholungen.

## Implementierung

- [PHP-Endpunkt und Token-Validierung](https://github.com/rumata-estor/modx3-mcp/blob/main/core/components/modxmcp/endpoint/api.common.php)
- [Standardeinstellungen](https://github.com/rumata-estor/modx3-mcp/blob/main/_build/data/transport.settings.php)
- [Standard-MCP-Client](https://github.com/rumata-estor/modx3-mcp/blob/main/client/index.js)
- [Serverseitiger Aktionsverteiler](https://github.com/rumata-estor/modx3-mcp/blob/main/core/components/modxmcp/model/modxmcp.class.php)
