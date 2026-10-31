# MODX MCP installieren und verbinden

[English](INSTALL.md) · [Русский](INSTALL.ru.md) · [Español](INSTALL.es.md) · [Deutsch](INSTALL.de.md) · [Français](INSTALL.fr.md) · [Українська](INSTALL.uk.md) · [Қазақша](INSTALL.kk.md) · [Nederlands](INSTALL.nl.md) · [Polski](INSTALL.pl.md) · [Funktionsumfang](CAPABILITIES.de.md) · [API-Referenz](API.de.md) · [Sicherheit](SECURITY.de.md)

MODX MCP ermöglicht einem standardkonformen MCP-kompatiblen KI-Client die Arbeit mit **MODX Revolution 2.8.x und 3.x**. Eine reguläre Installation benötigt ein MODX-Transportpaket, **Node.js 18+** auf dem Rechner, auf dem Ihr MCP-Client läuft, und eine Anwendung, die MCP-Server über **stdio** starten kann.

**Ein separater Orchestrierungsserver ist nicht erforderlich.** Die MODX-Komponente stellt auf Ihrer Website eine HTTP-API bereit; der lokale Prozess `modx-mcp` macht diese API für MCP-Clients über `stdio` verfügbar. Der PHP-Endpunkt selbst ist kein direkter MCP-stdio-Server.

## 1. MODX-Paket auswählen

- **MODX 2.8.x:** `modx2mcp-1.2.5-pl.transport.zip`.
- **MODX 3.x:** `modx3mcp-1.2.5-pl.transport.zip`.

> **Status:** Der Quellcode für 1.2.5 ist vorbereitet, die Transport-ZIPs sind aber noch nicht veröffentlicht. Für die Installation verwenden Sie [Version 1.2.4](https://github.com/rumata-estor/modx3-mcp/releases/tag/v1.2.4).

Beide Varianten verwenden denselben öffentlichen Tool-Vertrag und denselben Node.js-Client. Laden Sie das passende Paket von [GitHub Releases](https://github.com/rumata-estor/modx3-mcp/releases) oder einem unterstützten MODX-Paketanbieter herunter.

**Bestehende MODX-2-Installationen:** Beim Wechsel von der alten Paketidentität `modxmcp` zu `modx2mcp` wird eine saubere Deinstallation des alten Pakets mit anschließender Installation von `modx2mcp` empfohlen. Eine saubere Deinstallation entfernt die alten Einstellungen `modxmcp.*` und das API-Token. Sichern Sie bei Bedarf relevante Konfigurationen und aktualisieren Sie danach die Zugangsdaten Ihrer Clients. Spätere reguläre Aktualisierungen von `modx2mcp` erhalten das aktuelle Token.

### Dashboard im Manager

Unter **Komponenten → MODX MCP** können Sie die API-URL kopieren, das vorhandene Token nur mit der Berechtigung **settings** Ihres Manager-Kontos anzeigen/kopieren, optionale Funktionsgruppen umschalten und **Auto-static** / **Audit log** konfigurieren. Änderungen an Einstellungen und Funktionsgruppen werden erst durch Betätigen der jeweiligen Speichern-Schaltflächen wirksam. Der **Abhängigkeitsgraph** ist über den Graph-Link verfügbar; seine Zeichenfläche lässt sich mit dem Mausrad zoomen, während das umgebende Panel gescrollt werden kann, um Filter und Details anzuzeigen. **Token neu generieren** macht sämtliche bestehenden Client-Zugangsdaten ungültig; normale Paketaktualisierungen tun dies nicht. Die Anzeige „MCP aktiviert“ auf der Seite ist keine Live-Prüfung einer bestimmten Client-Verbindung.

## 2. Transportpaket installieren

1. Melden Sie sich mit einem zur Paketinstallation berechtigten Konto im MODX-Manager an.
2. Öffnen Sie die Paketverwaltung, laden Sie das Transport-ZIP für Ihre MODX-Hauptversion hoch und installieren Sie es.
3. Öffnen Sie die MODX-MCP-Komponente oder die Systemeinstellungen mit dem Namensraum `modxmcp`.
4. Prüfen Sie `modxmcp.api_token` und die Netzwerkeinschränkungen. Neuinstallationen beginnen mit `modxmcp.enabled = 0`; aktivieren Sie die Komponente manuell, nachdem der Zugriff abgesichert wurde.
5. Kopieren Sie das Token in Ihre **MCP-Client-Konfiguration**. Behandeln Sie es als Geheimnis, niemals als öffentliches Beispiel.

Das Installationsprogramm erzeugt ein kryptografisch zufälliges Token, wenn die Einstellung leer ist. Eine reguläre Aktualisierung erhält ein vorhandenes Token.

## 3. Website-Endpunkt ermitteln

Die Standard-URL lautet:

```text
https://example.com/assets/components/modxmcp/api.php
```

Verwenden Sie Ihre eigene Domain; bei angepassten Installationen kann der tatsächliche Pfad abweichen. **HTTPS** wird empfohlen und ist standardmäßig vorgeschrieben.

`GET` liefert eine eingeschränkte, nicht authentifizierte Statusantwort mit Komponenten-/Versionsinformationen. Administrative Anfragen verwenden JSON-`POST` mit `X-MCP-Token`.

## 4. MCP-Host konfigurieren

Installieren Sie auf dem Rechner Ihrer KI-Anwendung **Node.js 18+** mit npm/`npx`. Ergänzen Sie den MCP-Server in der Konfiguration Ihres Hosts (Speicherort und unterstützte Konfigurationsstruktur hängen von der Anwendung ab):

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

`MODX_MCP_SITE_URL` ist der PHP-HTTP-API-Endpunkt. `MODX_MCP_TOKEN` ist das Token für genau diese Website. Verwenden Sie auf Produktionssystemen einen festgelegten **veröffentlichten Release-Tag**, statt `main` zu folgen. Stellen Sie sicher, dass Ihr Host einen lokalen MCP-Server über `stdio` starten kann.

## 5. Verbindung bestätigen

Starten Sie die MCP-Integration neu oder aktualisieren Sie die Serverliste des Hosts. Suchen Sie nach dem Server `modx` und Tools mit dem Präfix `modx_`. Beginnen Sie mit einer rein lesenden Anfrage, zum Beispiel:

```text
Show the MODX version and an overview of the site structure. Make no changes.
```

Dafür können `modx_system_info` und `modx_project_overview` verwendet werden. Abhängig vom Server-Build, deaktivierten Gruppen und installierten Erweiterungen kann Ihr Host weniger als 192 Tools anzeigen.

Die [vollständige Referenz der Systemeinstellungen](SETTINGS.de.md) dokumentiert **alle 16 Einstellungen**, Standardwerte, Verhalten und Sicherheitsauswirkungen.

## 6. Zu prüfende Sicherheitseinstellungen

| Einstellung | Bedeutung |
| --- | --- |
| `modxmcp.enabled` | API aktivieren oder deaktivieren. |
| `modxmcp.api_token` | Geheimer API-Zugangsnachweis. |
| `modxmcp.require_https` | HTTPS verlangen; standardmäßig aktiviert. |
| `modxmcp.allowed_ips` | Optionale Freigabeliste von IPs und IPv4-CIDR-Bereichen; leer erlaubt jede Quelle. |
| `modxmcp.trust_proxy_https` | Dem HTTPS-Header des Reverse-Proxys nur hinter einem korrekt konfigurierten Proxy vertrauen. |
| `modxmcp.disabled_groups` | Bestimmte Tool-Familien deaktivieren; **keine Sicherheitsgrenze zwischen Rollen**. |
| `modxmcp.allow_run_processor` | Direkten Aufruf von MODX-Prozessoren erlauben; standardmäßig deaktiviert. |
| `modxmcp.max_payload_bytes` | Größenlimit für Anfragen. |
| `modxmcp.debug` | Fehlerdetails; in Produktion deaktiviert lassen. |

**Wichtig:** Anfragen werden als MODX-Dienstbenutzer mit `sudo`-Rechten ausgeführt. Ein offengelegtes gültiges Token kann administrative Änderungen und die Änderung ausführbaren PHP-Codes in Elementen ermöglichen. Lesen Sie den [Sicherheitsleitfaden](SECURITY.de.md).

## Fehlerbehebung

| Symptom | Prüfen |
| --- | --- |
| MCP-Server startet nicht | Node.js 18+, `npx`, Paketauflösung und Host-Konfiguration. |
| `401 Unauthorized` | `MODX_MCP_TOKEN` muss mit `modxmcp.api_token` übereinstimmen; Token-Rotation prüfen. |
| `403` | Aktivierung der Komponente, HTTPS, Reverse-Proxy-Konfiguration und erlaubte IP. |
| `404` | Endpunktpfad, Komponenteninstallation und Webserver-Routing. |
| `413` | Die Anfrage hat `modxmcp.max_payload_bytes` überschritten. |
| Tool nicht verfügbar | Client-/Server-Versionen, deaktivierte Gruppen, Installation der erforderlichen Erweiterung. |
| `500` | MODX-Fehlerprotokolle und `error_id`; Debug-Meldungen nicht öffentlich anzeigen. |

Fügen Sie API-Tokens weder in URL-Abfrageparameter noch in Fehlerberichte ein. Eigene HTTP-Clients müssen `X-MCP-Token` über TLS senden.

## Nächste Schritte

- [Funktionsumfang](CAPABILITIES.de.md) — was MODX MCP leisten kann.
- [API-Referenz](API.de.md) — Tools und Eingabeparameter.
- [Sicherheit](SECURITY.de.md) — Risiken und schützende Konfiguration.
- [Projekt-README](../README.de.md) — Projektübersicht.
- [Validierung](VALIDATION.de.md) — Hinweise zu Kompatibilitätstests.
