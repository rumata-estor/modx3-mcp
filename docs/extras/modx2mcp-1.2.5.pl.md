# Pakiet dla MODX 2 — MODX MCP 1.2.5

[English](modx2mcp-1.2.5.md) · [Русский](modx2mcp-1.2.5.ru.md) · [Español](modx2mcp-1.2.5.es.md) · [Deutsch](modx2mcp-1.2.5.de.md) · [Français](modx2mcp-1.2.5.fr.md) · [Українська](modx2mcp-1.2.5.uk.md) · [Қазақша](modx2mcp-1.2.5.kk.md) · [Nederlands](modx2mcp-1.2.5.nl.md) · [Polski](modx2mcp-1.2.5.pl.md)

[Instalacja](../INSTALL.pl.md) · [Bezpieczeństwo](../SECURITY.pl.md) · [Ustawienia](../SETTINGS.pl.md) · [GitHub source](https://github.com/rumata-estor/modx3-mcp/tree/main)

**MODX Revolution 2.8.x · 1.2.5-pl**

MODX MCP łączy asystentów AI z witrynami MODX za pomocą Model Context Protocol. Komponent PHP udostępnia uwierzytelniane API HTTP, a lokalny klient Node.js przekazuje je aplikacjom obsługującym MCP przez stdio. Osobny serwer orkiestracji nie jest wymagany.

## Pobierz

**Status:** pakiety ZIP 1.2.5 i sumy SHA-256 nie zostały jeszcze opublikowane. [Ostatnia opublikowana wersja: 1.2.4](https://github.com/rumata-estor/modx3-mcp/releases/tag/v1.2.4)

## Szybki start

1. Zaloguj się do panelu MODX i zainstaluj odpowiedni ZIP przez Menedżer pakietów.
2. Otwórz „Komponenty → MODX MCP”. Sprawdź HTTPS, uprawnienia i token API; nowe instalacje są domyślnie wyłączone.
3. Włącz `modxmcp.enabled` dopiero po zabezpieczeniu dostępu. Zapisz token w prywatnej konfiguracji klienta MCP.

## Aktualizacja istniejącej instalacji

Standardowa aktualizacja `modx2mcp` lub `modx3mcp` zachowuje ustawienia i token API. Jeśli MODX 2 nadal ma stary pakiet `modxmcp`, jest to inna tożsamość pakietu: przed odinstalowaniem wykonaj kopię konfiguracji i tokenu.

## Bezpieczeństwo i model zaufania

**Token API jest tajnym kluczem administracyjnym.** Uwierzytelniane żądania działają jako użytkownik usługowy MODX z uprawnieniami sudo. Posiadacz tokenu może zmieniać nawet wykonywalny kod PHP.

**Pełne dokumenty techniczne:** [parametry API (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/API.md) · [bezpieczeństwo (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/SECURITY.md) · [dla programistów (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/DEVELOPMENT.md). Szczegółowe tabele techniczne są obecnie dostępne po angielsku.

## Nowości w 1.2.5-pl

Dodano sześć narzędzi MCP do zarządzania kategoriami elementów MODX (modCategory): lista, podgląd, tworzenie, zmiana nazwy lub przeniesienie, bezpieczne usuwanie oraz przypisywanie kategorii do chunków, snippetów, szablonów, TV i pluginów.

Usuwanie uwzględnia podkategorie, elementy i zestawy właściwości. Drzewo zasobów i miniShop2 pozostają bez zmian.

**199 akcji serwera / 198 publicznych narzędzi MCP**. MODX Revolution 2.8.x / 3.x. Standardowa aktualizacja zachowuje ustawienia i token API.
