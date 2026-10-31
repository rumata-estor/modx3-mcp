# Transportpaket MODX 3 — MODX MCP 1.2.5

[English](modx3mcp-1.2.5.md) · [Русский](modx3mcp-1.2.5.ru.md) · [Español](modx3mcp-1.2.5.es.md) · [Deutsch](modx3mcp-1.2.5.de.md) · [Français](modx3mcp-1.2.5.fr.md) · [Українська](modx3mcp-1.2.5.uk.md) · [Қазақша](modx3mcp-1.2.5.kk.md) · [Nederlands](modx3mcp-1.2.5.nl.md) · [Polski](modx3mcp-1.2.5.pl.md)

[Installation](../INSTALL.de.md) · [Sicherheit](../SECURITY.de.md) · [Einstellungen](../SETTINGS.de.md) · [GitHub source](https://github.com/rumata-estor/modx3-mcp/tree/main)

**MODX Revolution 3.x · 1.2.5-pl**

MODX MCP verbindet KI-Assistenten über das Model Context Protocol mit einer MODX-Website. Die PHP-Komponente stellt eine authentifizierte HTTP-API bereit; ein lokaler Node.js-Client stellt diese als MCP-Server über stdio zur Verfügung. Ein zusätzlicher Orchestrierungsserver ist nicht erforderlich.

## Herunterladen

**Status:** Die 1.2.5-Transport-ZIPs und SHA-256-Prüfsummen sind noch nicht veröffentlicht. [Letzte veröffentlichte Version: 1.2.4](https://github.com/rumata-estor/modx3-mcp/releases/tag/v1.2.4)

## Schnellstart

1. Melden Sie sich im MODX-Manager an und installieren Sie das passende ZIP über die Paketverwaltung.
2. Öffnen Sie „Komponenten → MODX MCP“. Prüfen Sie HTTPS, Zugriffsrechte und das API-Token; eine Neuinstallation ist zunächst deaktiviert.
3. Aktivieren Sie `modxmcp.enabled` erst, wenn der Zugang abgesichert ist. Kopieren Sie das Token in den geheimen Bereich der Clientkonfiguration.

## Upgrade bestehender Installationen

Normale Updates von `modx2mcp` und `modx3mcp` erhalten die Einstellungen und das API-Token. Bei einer älteren MODX-2-Installation mit dem Paketnamen `modxmcp` handelt es sich um eine andere Paketidentität: vor einer Deinstallation zuerst Konfiguration und Token sichern.

## Sicherheit und Vertrauensmodell

**Das API-Token ist ein Administrationsgeheimnis.** Authentifizierte Aufrufe erfolgen über einen MODX-Servicebenutzer mit sudo-Rechten. Ein gültiges Token kann weitreichende Änderungen bis hin zu ausführbarem PHP ermöglichen.

**Vollständige technische Referenzen:** [API-Parameter (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/API.md) · [Sicherheit (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/SECURITY.md) · [Entwicklerdokumentation (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/DEVELOPMENT.md). Die ausführlichen englischen Referenztabellen sind maßgeblich.

## Neu in 1.2.5-pl

Sechs neue MCP-Werkzeuge verwalten MODX-Elementkategorien (modCategory): auflisten, anzeigen, erstellen, umbenennen oder verschieben, sicher löschen und Chunks, Snippets, Templates, TVs und Plugins zuweisen.

Das Löschen prüft untergeordnete Kategorien, Elemente und Eigenschaftssätze. Ressourcenbaum und miniShop2 bleiben unverändert.

**199 Serveraktionen / 198 öffentliche MCP-Werkzeuge**. MODX Revolution 2.8.x / 3.x. Normale Updates erhalten Einstellungen und API-Token.
