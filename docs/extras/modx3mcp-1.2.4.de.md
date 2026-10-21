# Transportpaket MODX 3 — MODX MCP 1.2.4

[English](modx3mcp-1.2.4.md) · [Русский](modx3mcp-1.2.4.ru.md) · [Español](modx3mcp-1.2.4.es.md) · [Deutsch](modx3mcp-1.2.4.de.md) · [Français](modx3mcp-1.2.4.fr.md) · [Українська](modx3mcp-1.2.4.uk.md) · [Қазақша](modx3mcp-1.2.4.kk.md) · [Nederlands](modx3mcp-1.2.4.nl.md) · [Polski](modx3mcp-1.2.4.pl.md)

[Installation](../INSTALL.de.md) · [Sicherheit](../SECURITY.de.md) · [Einstellungen](../SETTINGS.de.md) · [GitHub v1.2.4](https://github.com/rumata-estor/modx3-mcp/releases/tag/v1.2.4)

**MODX Revolution 3.x · 1.2.4-pl**

MODX MCP verbindet KI-Assistenten über das Model Context Protocol mit einer MODX-Website. Die PHP-Komponente stellt eine authentifizierte HTTP-API bereit; ein lokaler Node.js-Client stellt diese als MCP-Server über stdio zur Verfügung. Ein zusätzlicher Orchestrierungsserver ist nicht erforderlich.

## Herunterladen

[modx3mcp-1.2.4-pl.transport.zip](https://github.com/rumata-estor/modx3-mcp/releases/download/v1.2.4/modx3mcp-1.2.4-pl.transport.zip) · [SHA256SUMS](https://github.com/rumata-estor/modx3-mcp/releases/download/v1.2.4/SHA256SUMS)

## Schnellstart

1. Melden Sie sich im MODX-Manager an und installieren Sie das passende ZIP über die Paketverwaltung.
2. Öffnen Sie „Komponenten → MODX MCP“. Prüfen Sie HTTPS, Zugriffsrechte und das API-Token; eine Neuinstallation ist zunächst deaktiviert.
3. Aktivieren Sie `modxmcp.enabled` erst, wenn der Zugang abgesichert ist. Kopieren Sie das Token in den geheimen Bereich der Clientkonfiguration.

## Upgrade bestehender Installationen

Normale Updates von `modx2mcp` und `modx3mcp` erhalten die Einstellungen und das API-Token. Bei einer älteren MODX-2-Installation mit dem Paketnamen `modxmcp` handelt es sich um eine andere Paketidentität: vor einer Deinstallation zuerst Konfiguration und Token sichern.

## Sicherheit und Vertrauensmodell

**Das API-Token ist ein Administrationsgeheimnis.** Authentifizierte Aufrufe erfolgen über einen MODX-Servicebenutzer mit sudo-Rechten. Ein gültiges Token kann weitreichende Änderungen bis hin zu ausführbarem PHP ermöglichen.

**Vollständige technische Referenzen:** [API-Parameter (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/API.md) · [Sicherheit (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/SECURITY.md) · [Entwicklerdokumentation (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/DEVELOPMENT.md). Die ausführlichen englischen Referenztabellen sind maßgeblich.
