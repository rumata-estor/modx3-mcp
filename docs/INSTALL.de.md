# MODX MCP installieren und verbinden

[English](INSTALL.md) · [Русский](INSTALL.ru.md) · [Español](INSTALL.es.md) · [Deutsch](INSTALL.de.md) · [Français](INSTALL.fr.md) · [Українська](INSTALL.uk.md) · [Қазақша](INSTALL.kk.md) · [Nederlands](INSTALL.nl.md) · [Polski](INSTALL.pl.md)

**MODX MCP 1.2.4 · MODX Revolution 2.8.x / 3.x · 193 / 192**

Wählen Sie zuerst das Transportpaket für Ihre MODX-Hauptversion. Für den MCP-Client wird Node.js 18+ benötigt.

## 1. MODX 2 / MODX 3

- [Transportpaket MODX 2](https://github.com/rumata-estor/modx3-mcp/releases/download/v1.2.4/modx2mcp-1.2.4-pl.transport.zip) — MODX 2.8.x
- [Transportpaket MODX 3](https://github.com/rumata-estor/modx3-mcp/releases/download/v1.2.4/modx3mcp-1.2.4-pl.transport.zip) — MODX 3.x
- [SHA256SUMS](https://github.com/rumata-estor/modx3-mcp/releases/download/v1.2.4/SHA256SUMS)

## 2. Schnellstart

1. Melden Sie sich im MODX-Manager an und installieren Sie das passende ZIP über die Paketverwaltung.
2. Öffnen Sie „Komponenten → MODX MCP“. Prüfen Sie HTTPS, Zugriffsrechte und das API-Token; eine Neuinstallation ist zunächst deaktiviert.
3. Aktivieren Sie `modxmcp.enabled` erst, wenn der Zugang abgesichert ist. Kopieren Sie das Token in den geheimen Bereich der Clientkonfiguration.

## 3. Node.js / MCP

Konfigurieren Sie den stdio-fähigen MCP-Host wie im folgenden Beispiel. Ersetzen Sie die Beispieldomain und das Token; verwenden Sie das veröffentlichte Tag v1.2.4.

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

## 4. Verbindung prüfen

Starten Sie den MCP-Host neu und prüfen Sie zunächst eine Leseoperation wie `modx_system_info` oder `modx_project_overview`. Eine GET-Statusantwort bestätigt keine erfolgreiche Authentifizierung.

## Upgrade bestehender Installationen

Normale Updates von `modx2mcp` und `modx3mcp` erhalten die Einstellungen und das API-Token. Bei einer älteren MODX-2-Installation mit dem Paketnamen `modxmcp` handelt es sich um eine andere Paketidentität: vor einer Deinstallation zuerst Konfiguration und Token sichern.

## Fehlerbehebung

- `401`: `MODX_MCP_TOKEN` / `modxmcp.api_token`
- `403`: `modxmcp.enabled`, `modxmcp.require_https`, `modxmcp.allowed_ips`
- `404`: `/assets/components/modxmcp/api.php`
- `413`: `modxmcp.max_payload_bytes`
- `500`: MODX error log and `error_id`.

**Vollständige technische Referenzen:** [API-Parameter (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/API.md) · [Sicherheit (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/SECURITY.md) · [Entwicklerdokumentation (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/DEVELOPMENT.md). Die ausführlichen englischen Referenztabellen sind maßgeblich.
