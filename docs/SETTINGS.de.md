# MODX-MCP-Systemeinstellungen

[English](SETTINGS.md) · [Русский](SETTINGS.ru.md) · [Español](SETTINGS.es.md) · [Deutsch](SETTINGS.de.md) · [Français](SETTINGS.fr.md) · [Українська](SETTINGS.uk.md) · [Қазақша](SETTINGS.kk.md) · [Nederlands](SETTINGS.nl.md) · [Polski](SETTINGS.pl.md) · [Installation](INSTALL.de.md) · [Sicherheit](SECURITY.de.md)

Für **MODX MCP 1.2.4** auf MODX 2.8.x und 3.x. Dieses Dokument behandelt alle **16 Systemeinstellungen**, die beide Transportpakete definieren. Die Standardwerte stammen aus `_build/data/transport.settings.php`; das Verhalten wurde anhand der PHP-Implementierung geprüft.

Im MODX-Manager: **System → Systemeinstellungen**, Namensraum `modxmcp`. **Komponenten → MODX MCP** ermöglicht außerdem Änderungen an Audit log, Auto-static und Funktionsgruppen sowie das sichere Anzeigen/Kopieren des API-Tokens. Andere Optionen ändern Sie unter Systemeinstellungen. Aktualisieren Sie bei Bedarf die MODX-Caches und nach Änderungen die verfügbaren Funktionen des MCP-Clients.

**Sicherheit:** Durch das Token autorisierte Operationen laufen unter einem MODX-**Dienstbenutzer mit sudo-Rechten**. Halten Sie das Token geheim. Funktionsschalter **bilden keine Berechtigungsgrenze**.

## Verbindung und Identität

| Einstellung | Standardwert | Werte und Verhalten |
| --- | --- | --- |
| `modxmcp.enabled` | `0` | `0`: API-POST-Aktionen werden abgewiesen. `1`: API aktiviert. Der eingeschränkte `GET`-Status-/Versionsendpunkt funktioniert auch mit `0`, wenn die HTTPS-Bedingungen erfüllt sind. Erst aktivieren, wenn die Zugriffskontrollen eingerichtet sind. |
| `modxmcp.api_token` | Leerer Paketwert; bei der Installation wird ein zufälliges Token erzeugt, **falls es fehlt** | Geheime Zeichenfolge, die im Header `X-MCP-Token` gesendet wird. Fehlende/falsche Tokens führen zu `401`. Normale Aktualisierungen erhalten das Token. Manager-Benutzer mit der Berechtigung `settings` können **Anzeigen** / **Kopieren** verwenden. Eine Token-Rotation macht vorhandene Client-Zugangsdaten ungültig. |
| `modxmcp.service_user_id` | `0` | `0` wählt den nach ID ersten **aktiven MODX-Benutzer mit sudo-Rechten**; eine positive ID verlangt ein vorhandenes aktives sudo-Konto. Für vorhersehbares Verhalten ein dediziertes Dienstkonto wählen. Dies ist nicht die Identität des externen MCP-Clients. |

## Netzwerk und Sicherheit

| Einstellung | Standardwert | Werte und Verhalten |
| --- | --- | --- |
| `modxmcp.require_https` | `1` | `0`/`1`. Verlangt HTTPS, auch für die `GET`-Statusantwort, andernfalls wird `403` zurückgegeben. Auf bereitgestellten Websites bei `1` belassen. |
| `modxmcp.trust_proxy_https` | `0` | `0`/`1`. Wenn aktiviert, wird `X-Forwarded-Proto: https` als HTTPS-Nachweis akzeptiert. Nur hinter einem vertrauenswürdigen Reverse-Proxy aktivieren, der vom Client gelieferte Header überschreibt. |
| `modxmcp.allowed_ips` | Leer | Kommagetrennte exakte IP-Adressen und **IPv4 CIDR**, z. B. `203.0.113.5,198.51.100.0/24`. Leer erlaubt jede Quelle mit gültigem Token. Prüft `REMOTE_ADDR`, **nicht** `X-Forwarded-For`. Exakte IPv6-Übereinstimmungen funktionieren; IPv6 CIDR nicht. Falsche Werte führen zu `403`. |
| `modxmcp.disabled_groups` | `versionx,virtualpage,minishop2,migx,access,property_sets,contexts,package_management,namespaces,lexicon` | Kommagetrennte deaktivierte Gruppen: `versionx`, `virtualpage`, `minishop2`, `migx`, `clientconfig`, `access`, `property_sets`, `contexts`, `package_management`, `namespaces`, `lexicon`. Leer aktiviert alle ansonsten unterstützten Gruppen. **Keine Sicherheitsgrenze zwischen Rollen**; Kernaktionen können eine Website weiterhin verändern. |
| `modxmcp.allow_run_processor` | `0` | `0`/`1`. Erlaubt die allgemeine Operation `run_processor`, einschließlich eigener MODX-Prozessoren. Erweitert den Umfang administrativer Operationen: deaktiviert lassen, sofern nicht ausdrücklich benötigt. |
| `modxmcp.allow_root_filesystem_read` | `0` | `0`/`1`. Erlaubt das Durchsuchen und Lesen der Medienquelle `Filesystem` auf Root-Ebene. Kann weitergehenden Zugriff auf Serverdateien offenlegen. Deaktiviert lassen, sofern nicht benötigt. |
| `modxmcp.component_code_roots` | `core/components,assets/components` | Kommagetrennte Verzeichnisse für Komponentenquellcode, relativ zu `base_path` oder absolut. Unterstützt die Platzhalter `{core_path}`, `{assets_path}`, `{base_path}`. Begrenzt **nur die Inspektion von Komponentencode**, nicht jede MCP-Dateisystemaktion. Nicht auf das Stammverzeichnis des Serverdateisystems ausweiten. |

## Limits

| Einstellung | Standardwert | Werte und Verhalten |
| --- | --- | --- |
| `modxmcp.max_payload_bytes` | `1048576` (1 MiB) | Maximale Größe des JSON-Anfragekörpers von HTTP-API-POST-Anfragen in Bytes; Überschreitung führt zu `413`. Werte `<=0` heben diese Prüfung auf. Eine positive Obergrenze beibehalten. |
| `modxmcp.max_read_bytes` | `262144` (256 KiB) | Maximal zurückgegebener Abschnitt beim Lesen von Dateien; `<=0` fällt auf 262144 zurück. **Kein** Limit für die gesamte Dateigröße: Lesezugriffe können `offset` und `bytes`/`length` verwenden. |

## Verhalten und Diagnose

| Einstellung | Standardwert | Werte und Verhalten |
| --- | --- | --- |
| `modxmcp.auto_static` | `0` | `0`/`1`. Wenn aktiviert, werden über MCP erstellte/aktualisierte Chunks, Snippets, Templates und Plugins zu statischen Dateien unter `core/elements/`. Erfordert passende Schreibrechte, Sicherungen und Bereitstellungsverfahren. |
| `modxmcp.audit_log` | `1` | `0`/`1`. Protokolliert unterstützte Änderungsaktionen in MODX-Protokollen und `core/components/modxmcp/logs/audit.log`. **Keine** lückenlose Aufzeichnung jeder Anfrage. In Produktion mit Zugriffs- und Größenkontrollen aktiviert lassen. |
| `modxmcp.debug` | `0` | `0`/`1`. Wenn aktiviert, können detaillierte interne Ausnahmen in HTTP-Fehlerantworten erscheinen. Wenn deaktiviert, erhalten Aufrufer eine allgemeine Fehlermeldung und `error_id`. In Produktion auf `0` belassen. |

## Core-Pfad

| Einstellung | Standardwert | Werte und Verhalten |
| --- | --- | --- |
| `modxmcp.core_path` | `{core_path}components/modxmcp/` | Core-Verzeichnis der Komponente (akzeptiert auch `[[++core_path]]`). Die HTTP-API verwendet es zum Laden von Implementierung und Version. Nur für geprüfte, vom Standard abweichende Installationen ändern; falsche Werte legen die API lahm. |

## Sichere erste Verbindung

1. Installieren Sie das Transportpaket für Ihre MODX-Hauptversion. Neuinstallationen beginnen mit `modxmcp.enabled=0`; das Token wird automatisch erzeugt, und vorhandene Tokens bleiben bei regulären Aktualisierungen erhalten.
2. Konfigurieren Sie HTTPS, gegebenenfalls IP-Einschränkungen, Reverse-Proxy-Einstellungen und `service_user_id`.
3. Verwenden Sie **Kopieren** im Manager-Dashboard, um das API-Token im Geheimnisspeicher Ihres MCP-Clients abzulegen. Geben Sie es nicht preis.
4. Aktivieren Sie nur benötigte Funktionen. Lassen Sie `allow_run_processor` und `allow_root_filesystem_read` deaktiviert, sofern keine begründete Notwendigkeit besteht.
5. Aktivieren Sie die API und prüfen Sie eine **authentifizierte, rein lesende Aktion**. Eine `GET`-Versions-/Statusantwort beweist **nicht**, dass das Client-Token gültig ist.
