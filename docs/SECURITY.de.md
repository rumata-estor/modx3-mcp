# Sicherheit und Vertrauensmodell — MODX MCP

[English](SECURITY.md) · [Русский](SECURITY.ru.md) · [Español](SECURITY.es.md) · [Deutsch](SECURITY.de.md) · [Français](SECURITY.fr.md) · [Українська](SECURITY.uk.md) · [Қазақша](SECURITY.kk.md) · [Nederlands](SECURITY.nl.md) · [Polski](SECURITY.pl.md)

**Das API-Token ist ein Administrationsgeheimnis.** Authentifizierte Aufrufe erfolgen über einen MODX-Servicebenutzer mit sudo-Rechten. Ein gültiges Token kann weitreichende Änderungen bis hin zu ausführbarem PHP ermöglichen.

**Wichtig:** Funktionsschalter ersetzen keine serverseitige Autorisierung. Ein direkter HTTP-Client übernimmt auch nicht automatisch die Schutzmaßnahmen des offiziellen Node.js-Clients.

## Sicherheit und Vertrauensmodell

1. Verwenden Sie HTTPS und prüfen Sie die korrekte TLS-Konfiguration am Reverse Proxy.
2. Behandeln Sie das API-Token wie ein Administratorpasswort; veröffentlichen Sie es niemals.
3. Schränken Sie Zugriffe nach IP oder auf ein sicheres Netz ein, sofern dies möglich ist.
4. Belassen Sie `modxmcp.allow_run_processor=0` und `modxmcp.allow_root_filesystem_read=0`, solange nicht ausdrücklich nötig.
5. Deaktivierte Funktionsgruppen sind keine eigenständige Rechteverwaltung pro Benutzer.
6. Aktivieren Sie keine ausführlichen Fehlerantworten auf produktiven Websites (`modxmcp.debug=0`).
7. Erstellen Sie unabhängige Backups und nutzen Sie `dry_run` vor riskanten Änderungen.

## HTTP / MCP

- `POST` JSON uses `X-MCP-Token` over HTTPS.
- `GET` health/version is **not** proof that the client token is valid.
- `modxmcp.disabled_groups` is **not** an independent authorization boundary.
- External scripts calling `api.php` directly do not inherit Node.js client safeguards such as some backups and confirmations.

**Vollständige technische Referenzen:** [API-Parameter (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/API.md) · [Sicherheit (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/SECURITY.md) · [Entwicklerdokumentation (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/DEVELOPMENT.md). Die ausführlichen englischen Referenztabellen sind maßgeblich.
