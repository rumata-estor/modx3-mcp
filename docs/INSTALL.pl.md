# Instalacja i podłączenie MODX MCP

[English](INSTALL.md) · [Русский](INSTALL.ru.md) · [Español](INSTALL.es.md) · [Deutsch](INSTALL.de.md) · [Français](INSTALL.fr.md) · [Українська](INSTALL.uk.md) · [Қазақша](INSTALL.kk.md) · [Nederlands](INSTALL.nl.md) · [Polski](INSTALL.pl.md)

**MODX MCP 1.2.4 · MODX Revolution 2.8.x / 3.x · 193 / 192**

Wybierz pakiet transportowy zgodny z główną wersją MODX. Klient MCP wymaga Node.js 18 lub nowszego.

## 1. MODX 2 / MODX 3

- [Pakiet dla MODX 2](https://github.com/rumata-estor/modx3-mcp/releases/download/v1.2.4/modx2mcp-1.2.4-pl.transport.zip) — MODX 2.8.x
- [Pakiet dla MODX 3](https://github.com/rumata-estor/modx3-mcp/releases/download/v1.2.4/modx3mcp-1.2.4-pl.transport.zip) — MODX 3.x
- [SHA256SUMS](https://github.com/rumata-estor/modx3-mcp/releases/download/v1.2.4/SHA256SUMS)

## 2. Szybki start

1. Zaloguj się do panelu MODX i zainstaluj odpowiedni ZIP przez Menedżer pakietów.
2. Otwórz „Komponenty → MODX MCP”. Sprawdź HTTPS, uprawnienia i token API; nowe instalacje są domyślnie wyłączone.
3. Włącz `modxmcp.enabled` dopiero po zabezpieczeniu dostępu. Zapisz token w prywatnej konfiguracji klienta MCP.

## 3. Node.js / MCP

Skonfiguruj host MCP obsługujący stdio według przykładu. Zmień przykładową domenę i token; użyj opublikowanego znacznika v1.2.4.

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

## 4. Sprawdzanie połączenia

Uruchom ponownie klienta i najpierw wykonaj operację odczytu, np. `modx_system_info` lub `modx_project_overview`. Odpowiedź GET o stanie usługi nie potwierdza poprawnego uwierzytelnienia.

## Aktualizacja istniejącej instalacji

Standardowa aktualizacja `modx2mcp` lub `modx3mcp` zachowuje ustawienia i token API. Jeśli MODX 2 nadal ma stary pakiet `modxmcp`, jest to inna tożsamość pakietu: przed odinstalowaniem wykonaj kopię konfiguracji i tokenu.

## Rozwiązywanie problemów

- `401`: `MODX_MCP_TOKEN` / `modxmcp.api_token`
- `403`: `modxmcp.enabled`, `modxmcp.require_https`, `modxmcp.allowed_ips`
- `404`: `/assets/components/modxmcp/api.php`
- `413`: `modxmcp.max_payload_bytes`
- `500`: MODX error log and `error_id`.

**Pełne dokumenty techniczne:** [parametry API (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/API.md) · [bezpieczeństwo (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/SECURITY.md) · [dla programistów (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/DEVELOPMENT.md). Szczegółowe tabele techniczne są obecnie dostępne po angielsku.
