# Instalacja i podłączenie MODX MCP

[English](INSTALL.md) · [Русский](INSTALL.ru.md) · [Español](INSTALL.es.md) · [Deutsch](INSTALL.de.md) · [Français](INSTALL.fr.md) · [Українська](INSTALL.uk.md) · [Қазақша](INSTALL.kk.md) · [Nederlands](INSTALL.nl.md) · [Polski](INSTALL.pl.md) · [Możliwości](CAPABILITIES.pl.md) · [Dokumentacja API](API.pl.md) · [Bezpieczeństwo](SECURITY.pl.md)

MODX MCP umożliwia standardowemu klientowi AI zgodnemu z MCP pracę z **MODX Revolution 2.8.x i 3.x**. Typowa instalacja wymaga pakietu transportowego MODX, **Node.js 18+** na komputerze uruchamiającym klienta MCP oraz aplikacji potrafiącej uruchamiać serwery MCP korzystające ze **stdio**.

**Oddzielny serwer orkiestracji nie jest potrzebny.** Komponent MODX udostępnia w witrynie API HTTP; lokalny proces `modx-mcp` udostępnia to API klientom MCP przez `stdio`. Sam punkt końcowy PHP nie jest bezpośrednim serwerem MCP stdio.

## 1. Wybierz pakiet MODX

- **MODX 2.8.x:** `modx2mcp-1.2.5-pl.transport.zip`.
- **MODX 3.x:** `modx3mcp-1.2.5-pl.transport.zip`.

> **Status:** kod źródłowy 1.2.5 jest przygotowany, ale pakiety transportowe ZIP nie zostały jeszcze opublikowane. Do instalacji użyj [wersji 1.2.4](https://github.com/rumata-estor/modx3-mcp/releases/tag/v1.2.4).

Oba warianty korzystają z tego samego publicznego kontraktu narzędzi i klienta Node.js. Pobierz odpowiedni pakiet z [GitHub Releases](https://github.com/rumata-estor/modx3-mcp/releases) lub od obsługiwanego dostawcy pakietów MODX.

**Istniejące instalacje MODX 2:** przy migracji ze starej tożsamości pakietu `modxmcp` do `modx2mcp` zaleca się całkowite odinstalowanie starego pakietu, a następnie instalację `modx2mcp`. Całkowite odinstalowanie usuwa stare ustawienia `modxmcp.*` i token API. W razie potrzeby zachowaj odpowiednią konfigurację, a następnie zaktualizuj dane uwierzytelniające klienta. Kolejne zwykłe aktualizacje `modx2mcp` zachowują bieżący token.

### Panel komponentu w menedżerze

W **Komponenty → MODX MCP** skopiuj adres URL API, wyświetl/skopiuj istniejący token tylko wtedy, gdy Twoje konto menedżera ma uprawnienie **settings**, włącz lub wyłącz opcjonalne grupy funkcji i skonfiguruj **Auto-static** / **Audit log**. Zmiany ustawień i funkcji zaczynają obowiązywać dopiero po naciśnięciu odpowiednich przycisków zapisu. **Graf zależności** jest dostępny przez odnośnik do grafu; jego obszar rysowania można powiększać kółkiem myszy, a otaczający panel przewijać, aby zobaczyć filtry i szczegóły. **Regenerate token** unieważnia dane uwierzytelniające wszystkich istniejących klientów; zwykłe aktualizacje pakietu tego nie robią. Komunikat „MCP enabled” na stronie nie stanowi bieżącej weryfikacji połączenia z konkretnym klientem.

## 2. Zainstaluj pakiet transportowy

1. Zaloguj się do menedżera MODX na konto z uprawnieniami do instalowania pakietów.
2. Otwórz zarządzanie pakietami, prześlij plik transportowy ZIP dla swojej głównej wersji MODX i zainstaluj go.
3. Otwórz komponent MODX MCP lub ustawienia systemowe z przestrzenią nazw `modxmcp`.
4. Sprawdź `modxmcp.api_token` i ograniczenia sieciowe. Nowe instalacje zaczynają z `modxmcp.enabled = 0`; włącz komponent ręcznie po zabezpieczeniu dostępu.
5. Skopiuj token do **konfiguracji klienta MCP**. Traktuj go jako sekret, nigdy jako publiczny przykład.

Jeśli ustawienie jest puste, instalator generuje kryptograficznie losowy token. Zwykła aktualizacja zachowuje istniejący token.

## 3. Ustal punkt końcowy witryny

Standardowy adres URL to:

```text
https://example.com/assets/components/modxmcp/api.php
```

Użyj własnej domeny; w niestandardowych wdrożeniach rzeczywista ścieżka może być inna. **HTTPS** jest zalecany i domyślnie wymagany.

`GET` zwraca ograniczoną, niewymagającą uwierzytelnienia odpowiedź o stanie i wersji komponentu. Żądania administracyjne używają JSON `POST` z nagłówkiem `X-MCP-Token`.

## 4. Skonfiguruj aplikację hostującą MCP

Na komputerze uruchamiającym aplikację AI zainstaluj **Node.js 18+** z npm/`npx`. Dodaj serwer MCP do konfiguracji aplikacji hostującej (lokalizacja i obsługiwany format konfiguracji zależą od aplikacji):

```json
{
  "mcpServers": {
    "modx": {
      "command": "npx",
      "args": [
        "-y",
        "github:rumata-estor/modx3-mcp#v1.2.4"
      ],
      "env": {
        "MODX_MCP_SITE_URL": "https://example.com/assets/components/modxmcp/api.php",
        "MODX_MCP_TOKEN": "YOUR_SITE_PRIVATE_TOKEN"
      }
    }
  }
}
```

`MODX_MCP_SITE_URL` to punkt końcowy API HTTP w PHP. `MODX_MCP_TOKEN` to token tej konkretnej witryny. W systemach produkcyjnych przypnij **opublikowany tag wydania** zamiast śledzić `main`. Upewnij się, że aplikacja hostująca obsługuje uruchamianie lokalnego serwera MCP przez `stdio`.

## 5. Potwierdź połączenie

Uruchom ponownie integrację MCP lub odśwież serwery w aplikacji hostującej. Poszukaj serwera `modx` i narzędzi z prefiksem `modx_`. Zacznij od żądania tylko do odczytu, na przykład:

```text
Show the MODX version and an overview of the site structure. Make no changes.
```

Można do tego użyć `modx_system_info` i `modx_project_overview`. Aplikacja hostująca może wyświetlać mniej niż 192 narzędzia, zależnie od kompilacji serwera, wyłączonych grup i zainstalowanych dodatków.

[Pełna dokumentacja ustawień systemowych](SETTINGS.pl.md) opisuje **wszystkie 16 ustawień**, wartości domyślne, działanie i konsekwencje dla bezpieczeństwa.

## 6. Ustawienia bezpieczeństwa do sprawdzenia

| Ustawienie | Znaczenie |
| --- | --- |
| `modxmcp.enabled` | Włącza lub wyłącza API. |
| `modxmcp.api_token` | Tajne dane uwierzytelniające API. |
| `modxmcp.require_https` | Wymaga HTTPS; domyślnie włączone. |
| `modxmcp.allowed_ips` | Opcjonalna lista dozwolonych adresów IP i zakresów IPv4 CIDR; pusta dopuszcza dowolne źródło. |
| `modxmcp.trust_proxy_https` | Zaufanie do nagłówka HTTPS odwrotnego proxy należy włączać wyłącznie za prawidłowo skonfigurowanym proxy. |
| `modxmcp.disabled_groups` | Wyłącza wybrane rodziny narzędzi; **nie stanowi granicy uprawnień ról**. |
| `modxmcp.allow_run_processor` | Zezwala na bezpośrednie wywoływanie procesorów MODX; domyślnie wyłączone. |
| `modxmcp.max_payload_bytes` | Limit rozmiaru żądania. |
| `modxmcp.debug` | Szczegóły błędów; pozostaw wyłączone w środowisku produkcyjnym. |

**Ważne:** żądania są wykonywane jako użytkownik usługowy MODX z uprawnieniami `sudo`. Ujawnienie ważnego tokenu może umożliwić zmiany administracyjne i modyfikację wykonywalnego kodu PHP w elementach. Przeczytaj [przewodnik bezpieczeństwa](SECURITY.pl.md).

## Rozwiązywanie problemów

| Objaw | Co sprawdzić |
| --- | --- |
| Serwer MCP nie może się uruchomić | Node.js 18+, `npx`, rozwiązywanie pakietów i konfigurację aplikacji hostującej. |
| `401 Unauthorized` | `MODX_MCP_TOKEN` musi odpowiadać `modxmcp.api_token`; sprawdź, czy token został zmieniony. |
| `403` | Ustawienie włączenia komponentu, HTTPS, konfigurację odwrotnego proxy i dozwolony adres IP. |
| `404` | Ścieżkę punktu końcowego, instalację komponentu i routing serwera WWW. |
| `413` | Żądanie przekroczyło `modxmcp.max_payload_bytes`. |
| Narzędzie niedostępne | Wersje klienta/serwera, wyłączone grupy, instalację wymaganego dodatku. |
| `500` | Dzienniki błędów MODX i `error_id`; unikaj publicznego ujawniania komunikatów debugowania. |

Nie umieszczaj tokenów API w parametrach adresu URL ani w zgłoszeniach problemów. Własne klienty HTTP muszą przesyłać `X-MCP-Token` przez TLS.

## Następne kroki

- [Możliwości](CAPABILITIES.pl.md) — co potrafi MODX MCP.
- [Dokumentacja API](API.pl.md) — narzędzia i parametry wejściowe.
- [Bezpieczeństwo](SECURITY.pl.md) — zagrożenia i konfiguracja zabezpieczeń.
- [README projektu](../README.pl.md) — przegląd projektu.
- [Walidacja](VALIDATION.pl.md) — uwagi dotyczące testów zgodności.
