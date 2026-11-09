# Pakiet dla MODX 3 — MODX MCP 1.2.4

[English](modx3mcp-1.2.4.md) · [Русский](modx3mcp-1.2.4.ru.md) · [Español](modx3mcp-1.2.4.es.md) · [Deutsch](modx3mcp-1.2.4.de.md) · [Français](modx3mcp-1.2.4.fr.md) · [Українська](modx3mcp-1.2.4.uk.md) · [Қазақша](modx3mcp-1.2.4.kk.md) · [Nederlands](modx3mcp-1.2.4.nl.md) · [Polski](modx3mcp-1.2.4.pl.md)

[Instalacja](../INSTALL.pl.md) · [Bezpieczeństwo](../SECURITY.pl.md) · [Ustawienia](../SETTINGS.pl.md) · [GitHub v1.2.4](https://github.com/rumata-estor/modx3-mcp/releases/tag/v1.2.4)

**MODX Revolution 3.x · 1.2.4-pl**

MODX MCP łączy asystentów AI z witrynami MODX za pomocą Model Context Protocol. Komponent PHP udostępnia uwierzytelniane API HTTP, a lokalny klient Node.js przekazuje je aplikacjom obsługującym MCP przez stdio. Osobny serwer orkiestracji nie jest wymagany.

## Pobierz

[modx3mcp-1.2.4-pl.transport.zip](https://github.com/rumata-estor/modx3-mcp/releases/download/v1.2.4/modx3mcp-1.2.4-pl.transport.zip) · [SHA256SUMS](https://github.com/rumata-estor/modx3-mcp/releases/download/v1.2.4/SHA256SUMS)

## Szybki start

1. Zaloguj się do panelu MODX i zainstaluj odpowiedni ZIP przez Menedżer pakietów.
2. Otwórz „Komponenty → MODX MCP”. Sprawdź HTTPS, uprawnienia i token API; nowe instalacje są domyślnie wyłączone.
3. Włącz `modxmcp.enabled` dopiero po zabezpieczeniu dostępu. Zapisz token w prywatnej konfiguracji klienta MCP.

## Aktualizacja istniejącej instalacji

Standardowa aktualizacja `modx2mcp` lub `modx3mcp` zachowuje ustawienia i token API. Jeśli MODX 2 nadal ma stary pakiet `modxmcp`, jest to inna tożsamość pakietu: przed odinstalowaniem wykonaj kopię konfiguracji i tokenu.

## Bezpieczeństwo i model zaufania

**Token API jest tajnym kluczem administracyjnym.** Uwierzytelniane żądania działają jako użytkownik usługowy MODX z uprawnieniami sudo. Posiadacz tokenu może zmieniać nawet wykonywalny kod PHP.

**Pełne dokumenty techniczne:** [parametry API (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/API.md) · [bezpieczeństwo (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/SECURITY.md) · [dla programistów (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/DEVELOPMENT.md). Szczegółowe tabele techniczne są obecnie dostępne po angielsku.
