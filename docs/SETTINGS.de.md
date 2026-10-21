# Systemeinstellungen — MODX MCP

[English](SETTINGS.md) · [Русский](SETTINGS.ru.md) · [Español](SETTINGS.es.md) · [Deutsch](SETTINGS.de.md) · [Français](SETTINGS.fr.md) · [Українська](SETTINGS.uk.md) · [Қазақша](SETTINGS.kk.md) · [Nederlands](SETTINGS.nl.md) · [Polski](SETTINGS.pl.md)

Alle 16 Einstellungen sind unter „System → Systemeinstellungen“ im Namensraum `modxmcp` erreichbar. Die Werte stammen aus dem gemeinsamen Transportpaket beider MODX-Versionen.

**Wichtig:** Funktionsschalter ersetzen keine serverseitige Autorisierung. Ein direkter HTTP-Client übernimmt auch nicht automatisch die Schutzmaßnahmen des offiziellen Node.js-Clients.

| Schlüssel | Standard | Beschreibung |
| --- | --- | --- |
| `modxmcp.enabled` | `0` | Schaltet die authentifizierten API-Aktionen ein oder aus; Neuinstallationen sind deaktiviert. |
| `modxmcp.api_token` | `(generated)` | Geheimes Token im Header `X-MCP-Token`; normale Updates erhalten es. |
| `modxmcp.service_user_id` | `0` | MODX-Servicebenutzer. `0` wählt den ersten aktiven Benutzer mit sudo-Rechten. |
| `modxmcp.require_https` | `1` | HTTPS für API-Anfragen erzwingen; produktiv aktiviert lassen. |
| `modxmcp.trust_proxy_https` | `0` | HTTPS-Header nur hinter einem vertrauenswürdigen Reverse Proxy beachten. |
| `modxmcp.allowed_ips` | `(empty)` | Erlaubte IP-Adressen und IPv4-CIDR-Bereiche; leer bedeutet keine IP-Beschränkung. |
| `modxmcp.disabled_groups` | `versionx,virtualpage,minishop2,migx,access,property_sets,contexts,package_management,namespaces,lexicon` | Kommagetrennte deaktivierte Funktionsgruppen; keine vollständige Sicherheitsgrenze. |
| `modxmcp.allow_run_processor` | `0` | Beliebige MODX-Prozessoren zulassen; standardmäßig aus Sicherheitsgründen aus. |
| `modxmcp.allow_root_filesystem_read` | `0` | Zugriff auf die Root-Medienquelle „Filesystem“; standardmäßig aus. |
| `modxmcp.component_code_roots` | `core/components,assets/components` | Verzeichnisse für die Prüfung von Komponentencode; keine globale Dateisystem-Sandbox. |
| `modxmcp.max_payload_bytes` | `1048576` | Maximale Größe eines POST-JSON-Bodys in Byte; Überschreitung liefert 413. |
| `modxmcp.max_read_bytes` | `262144` | Maximale Länge eines Dateilese-Abschnitts in Byte. |
| `modxmcp.auto_static` | `0` | Bei Änderungen automatisch statische Elementdateien anlegen. |
| `modxmcp.audit_log` | `1` | Unterstützte Änderungen im Audit-Protokoll festhalten; kein vollständiger Requestmitschnitt. |
| `modxmcp.debug` | `0` | Detaillierte Fehlerausgaben; in Produktion deaktivieren. |
| `modxmcp.core_path` | `{core_path}components/modxmcp/` | Kernpfad der Komponente; nur bei geprüften Sonderinstallationen ändern. |

**Vollständige technische Referenzen:** [API-Parameter (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/API.md) · [Sicherheit (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/SECURITY.md) · [Entwicklerdokumentation (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/DEVELOPMENT.md). Die ausführlichen englischen Referenztabellen sind maßgeblich.
