# MODX MCP — KI-gestützte Verwaltung von MODX

[English](README.md) · [Русский](README.ru.md) · [Español](README.es.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Українська](README.uk.md) · [Қазақша](README.kk.md) · [Nederlands](README.nl.md) · [Polski](README.pl.md)

**MODX MCP 1.2.5 · MODX Revolution 2.8.x / 3.x · 199 / 198**

MODX MCP verbindet KI-Assistenten über das Model Context Protocol mit einer MODX-Website. Die PHP-Komponente stellt eine authentifizierte HTTP-API bereit; ein lokaler Node.js-Client stellt diese als MCP-Server über stdio zur Verfügung. Ein zusätzlicher Orchestrierungsserver ist nicht erforderlich.


## Für wen ist das Projekt gedacht?

Für MODX-Entwickler, Webagenturen und Administratoren, die Websites kontrolliert mit KI-Werkzeugen verwalten möchten.


## Funktionen und Werkzeuge

- Ressourcen, Vorlagen, Chunks, Snippets, Plugins und TVs durchsuchen, lesen und ändern.
- MODX-Elementkategorien erstellen, umbenennen, verschieben, sicher löschen und Elementen zuweisen.
- Abhängigkeiten und möglicherweise ungenutzten Code im Manager-Graphen untersuchen.
- Systemeinstellungen, Medienquellen, Pakete und ausgewählte Erweiterungen verwalten.
- Änderungen durch Vorbedingungen, Vorschauläufe und Sicherungen absichern.

## Schnellstart

Wählen Sie zuerst das Transportpaket für Ihre MODX-Hauptversion. Für den MCP-Client wird Node.js 18+ benötigt.

- **MODX 2.8.x:** [Transportpaket MODX 2](https://github.com/rumata-estor/modx3-mcp/releases/download/v1.2.4/modx2mcp-1.2.4-pl.transport.zip)
- **MODX 3.x:** [Transportpaket MODX 3](https://github.com/rumata-estor/modx3-mcp/releases/download/v1.2.4/modx3mcp-1.2.4-pl.transport.zip)
- [GitHub v1.2.4](https://github.com/rumata-estor/modx3-mcp/releases/tag/v1.2.4) · [SHA256SUMS](https://github.com/rumata-estor/modx3-mcp/releases/download/v1.2.4/SHA256SUMS)

> Die Download-Links oben verweisen auf die zuletzt veröffentlichte Version 1.2.4. Die 1.2.5-Transport-ZIPs sind noch nicht erstellt.

Melden Sie sich im MODX-Manager an und installieren Sie das passende ZIP über die Paketverwaltung.

Öffnen Sie „Komponenten → MODX MCP“. Prüfen Sie HTTPS, Zugriffsrechte und das API-Token; eine Neuinstallation ist zunächst deaktiviert.

```json
{
  "mcpServers": {
    "modx": {
      "command": "npx",
      "args": ["-y", "github:rumata-estor/modx3-mcp#v1.2.4"],
      "env": {
        "MODX_MCP_SITE_URL": "https://example.com/assets/components/modxmcp/api.php",
        "MODX_MCP_TOKEN": "YOUR_SITE_PRIVATE_TOKEN"
      }
    }
  }
}
```

Aktivieren Sie `modxmcp.enabled` erst, wenn der Zugang abgesichert ist. Kopieren Sie das Token in den geheimen Bereich der Clientkonfiguration.

## Sicherheit und Vertrauensmodell

**Das API-Token ist ein Administrationsgeheimnis.** Authentifizierte Aufrufe erfolgen über einen MODX-Servicebenutzer mit sudo-Rechten. Ein gültiges Token kann weitreichende Änderungen bis hin zu ausführbarem PHP ermöglichen.

**Wichtig:** Funktionsschalter ersetzen keine serverseitige Autorisierung. Ein direkter HTTP-Client übernimmt auch nicht automatisch die Schutzmaßnahmen des offiziellen Node.js-Clients.

## Dokumentation

[Installation](docs/INSTALL.de.md) · [Sicherheit](docs/SECURITY.de.md) · [Einstellungen](docs/SETTINGS.de.md) · [Funktionen](docs/CAPABILITIES.de.md) · [API](docs/API.de.md)

**Vollständige technische Referenzen:** [API-Parameter (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/API.md) · [Sicherheit (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/SECURITY.md) · [Entwicklerdokumentation (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/DEVELOPMENT.md). Die ausführlichen englischen Referenztabellen sind maßgeblich.
