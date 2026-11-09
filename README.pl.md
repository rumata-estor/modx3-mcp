# MODX MCP — zarządzanie MODX z pomocą AI

[English](README.md) · [Русский](README.ru.md) · [Español](README.es.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Українська](README.uk.md) · [Қазақша](README.kk.md) · [Nederlands](README.nl.md) · [Polski](README.pl.md)

**MODX MCP 1.2.5 · MODX Revolution 2.8.x / 3.x · 199 / 198**

MODX MCP łączy asystentów AI z witrynami MODX za pomocą Model Context Protocol. Komponent PHP udostępnia uwierzytelniane API HTTP, a lokalny klient Node.js przekazuje je aplikacjom obsługującym MCP przez stdio. Osobny serwer orkiestracji nie jest wymagany.


## Dla kogo jest ten projekt?

Dla programistów MODX, agencji internetowych i administratorów, którzy chcą bezpiecznie zarządzać witrynami z pomocą AI.

## Możliwości i narzędzia

- Wyszukiwanie, odczytywanie i edytowanie zasobów, szablonów, chunków, snippetów, pluginów i TV.
- Zarządzać kategoriami elementów MODX: tworzyć, zmieniać nazwy, przenosić, bezpiecznie usuwać i przypisywać.
- Analizowanie zależności, uszkodzonych odwołań oraz potencjalnie nieużywanego kodu w grafie.
- Obsługa ustawień systemowych, źródeł mediów, pakietów i wspieranych dodatków.
- Zabezpieczanie zmian przez warunki wstępne, podgląd i kopie zapasowe.

## Szybki start

Wybierz pakiet transportowy zgodny z główną wersją MODX. Klient MCP wymaga Node.js 18 lub nowszego.

- **MODX 2.8.x:** [Pakiet dla MODX 2](https://github.com/rumata-estor/modx3-mcp/releases/download/v1.2.4/modx2mcp-1.2.4-pl.transport.zip)
- **MODX 3.x:** [Pakiet dla MODX 3](https://github.com/rumata-estor/modx3-mcp/releases/download/v1.2.4/modx3mcp-1.2.4-pl.transport.zip)
- [GitHub v1.2.4](https://github.com/rumata-estor/modx3-mcp/releases/tag/v1.2.4) · [SHA256SUMS](https://github.com/rumata-estor/modx3-mcp/releases/download/v1.2.4/SHA256SUMS)

> Powyższe linki do pobierania dotyczą ostatniej opublikowanej wersji 1.2.4. Pakiety transportowe 1.2.5 nie są jeszcze zbudowane.

Zaloguj się do panelu MODX i zainstaluj odpowiedni ZIP przez Menedżer pakietów.

Otwórz „Komponenty → MODX MCP”. Sprawdź HTTPS, uprawnienia i token API; nowe instalacje są domyślnie wyłączone.

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

Włącz `modxmcp.enabled` dopiero po zabezpieczeniu dostępu. Zapisz token w prywatnej konfiguracji klienta MCP.

## Bezpieczeństwo i model zaufania

**Token API jest tajnym kluczem administracyjnym.** Uwierzytelniane żądania działają jako użytkownik usługowy MODX z uprawnieniami sudo. Posiadacz tokenu może zmieniać nawet wykonywalny kod PHP.

**Ważne:** wyłączenie grup funkcji nie zastępuje autoryzacji po stronie serwera. Bezpośredni klient HTTP nie dziedziczy automatycznie zabezpieczeń oficjalnego klienta Node.js.

## Dokumentacja

[Instalacja](docs/INSTALL.pl.md) · [Bezpieczeństwo](docs/SECURITY.pl.md) · [Ustawienia](docs/SETTINGS.pl.md) · [Możliwości](docs/CAPABILITIES.pl.md) · [API](docs/API.pl.md)

**Pełne dokumenty techniczne:** [parametry API (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/API.md) · [bezpieczeństwo (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/SECURITY.md) · [dla programistów (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/DEVELOPMENT.md). Szczegółowe tabele techniczne są obecnie dostępne po angielsku.
