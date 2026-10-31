# Ustawienia systemowe MODX MCP

[English](SETTINGS.md) · [Русский](SETTINGS.ru.md) · [Español](SETTINGS.es.md) · [Deutsch](SETTINGS.de.md) · [Français](SETTINGS.fr.md) · [Українська](SETTINGS.uk.md) · [Қазақша](SETTINGS.kk.md) · [Nederlands](SETTINGS.nl.md) · [Polski](SETTINGS.pl.md) · [Instalacja](INSTALL.pl.md) · [Bezpieczeństwo](SECURITY.pl.md)

Dla **MODX MCP 1.2.5** na MODX 2.8.x i 3.x. Dokument obejmuje wszystkie **16 ustawień systemowych** zdefiniowanych w obu pakietach transportowych. Wartości domyślne pochodzą z `_build/data/transport.settings.php`; działanie zostało sprawdzone względem implementacji PHP.

W menedżerze MODX: **System → Ustawienia systemowe**, przestrzeń nazw `modxmcp`. **Komponenty → MODX MCP** umożliwia również zmianę Audit log, Auto-static, grup funkcji oraz bezpieczne wyświetlanie/kopiowanie tokenu API. Pozostałe opcje zmieniaj w ustawieniach systemowych. W razie potrzeby odśwież pamięć podręczną MODX, a po zmianach odśwież listę funkcji klienta MCP.

**Bezpieczeństwo:** operacje autoryzowane tokenem są wykonywane przez **użytkownika usługowego MODX z uprawnieniami sudo**. Zachowaj token w tajemnicy. Przełączniki funkcji **nie wyznaczają granicy uprawnień**.

## Połączenie i tożsamość

| Ustawienie | Wartość domyślna | Wartości i działanie |
| --- | --- | --- |
| `modxmcp.enabled` | `0` | `0`: akcje API POST są odrzucane. `1`: API włączone. Ograniczony punkt końcowy stanu/wersji `GET` działa również przy `0`, jeśli spełnione są warunki HTTPS. Włączaj dopiero po przygotowaniu kontroli dostępu. |
| `modxmcp.api_token` | Pusta wartość w pakiecie; losowy token jest generowany podczas instalacji, **jeśli go brakuje** | Tajny ciąg przesyłany w nagłówku `X-MCP-Token`. Brakujący/nieprawidłowy token powoduje zwrócenie `401`. Zwykłe aktualizacje zachowują token. Użytkownicy menedżera z uprawnieniem `settings` mogą używać **Show** / **Copy**. Rotacja tokenu unieważnia istniejące dane uwierzytelniające klientów. |
| `modxmcp.service_user_id` | `0` | `0` wybiera pierwszego według ID **aktywnego użytkownika MODX z uprawnieniami sudo**; dodatnie ID wymaga istniejącego aktywnego konta sudo. Dla przewidywalnego działania wybierz dedykowane konto usługowe. Nie jest to tożsamość zewnętrznego klienta MCP. |

## Sieć i bezpieczeństwo

| Ustawienie | Wartość domyślna | Wartości i działanie |
| --- | --- | --- |
| `modxmcp.require_https` | `1` | `0`/`1`. Wymaga HTTPS, także dla odpowiedzi o stanie `GET`; w przeciwnym razie zwraca `403`. W działających witrynach pozostaw `1`. |
| `modxmcp.trust_proxy_https` | `0` | `0`/`1`. Po włączeniu `X-Forwarded-Proto: https` jest uznawany za potwierdzenie HTTPS. Włączaj tylko za zaufanym odwrotnym proxy, które nadpisuje nagłówki dostarczane przez klienta. |
| `modxmcp.allowed_ips` | Pusta | Dokładne adresy IP i zakresy **IPv4 CIDR** oddzielone przecinkami, np. `203.0.113.5,198.51.100.0/24`. Pusta wartość dopuszcza dowolne źródło z ważnym tokenem. Sprawdza `REMOTE_ADDR`, **nie** `X-Forwarded-For`. Dokładne dopasowania IPv6 działają; IPv6 CIDR nie. Nieprawidłowe wartości powodują `403`. |
| `modxmcp.disabled_groups` | `versionx,virtualpage,minishop2,migx,access,property_sets,contexts,package_management,namespaces,lexicon` | Wyłączone grupy oddzielone przecinkami: `versionx`, `virtualpage`, `minishop2`, `migx`, `clientconfig`, `access`, `property_sets`, `contexts`, `package_management`, `namespaces`, `lexicon`. Pusta wartość włącza wszystkie grupy, które są poza tym obsługiwane. **Nie stanowi granicy uprawnień ról**; podstawowe akcje nadal mogą modyfikować witrynę. |
| `modxmcp.allow_run_processor` | `0` | `0`/`1`. Zezwala na ogólną operację `run_processor`, w tym niestandardowe procesory MODX. Rozszerza zakres operacji administracyjnych: pozostaw wyłączone, chyba że jest konkretnie potrzebne. |
| `modxmcp.allow_root_filesystem_read` | `0` | `0`/`1`. Zezwala na przeglądanie i odczyt głównego źródła mediów `Filesystem`. Może ujawnić szerszy zakres plików serwera. Pozostaw wyłączone, jeśli nie jest potrzebne. |
| `modxmcp.component_code_roots` | `core/components,assets/components` | Katalogi źródłowe komponentów oddzielone przecinkami, względne wobec `base_path` lub bezwzględne. Obsługuje symbole zastępcze `{core_path}`, `{assets_path}`, `{base_path}`. Ogranicza **wyłącznie inspekcję kodu komponentów**, a nie każdą operację MCP na systemie plików. Unikaj rozszerzania zakresu do katalogu głównego systemu plików serwera. |

## Limity

| Ustawienie | Wartość domyślna | Wartości i działanie |
| --- | --- | --- |
| `modxmcp.max_payload_bytes` | `1048576` (1 MiB) | Maksymalny rozmiar treści żądania JSON POST API HTTP w bajtach; przekroczenie zwraca `413`. Wartości `<=0` wyłączają tę kontrolę. Zachowaj dodatni limit górny. |
| `modxmcp.max_read_bytes` | `262144` (256 KiB) | Maksymalny zwracany fragment przy odczycie plików; `<=0` powoduje użycie wartości zastępczej 262144. **Nie** jest to limit rozmiaru całego pliku: odczyty mogą używać `offset` i `bytes`/`length`. |

## Działanie i diagnostyka

| Ustawienie | Wartość domyślna | Wartości i działanie |
| --- | --- | --- |
| `modxmcp.auto_static` | `0` | `0`/`1`. Po włączeniu chunki, snippety, szablony i pluginy tworzone/aktualizowane przez MCP stają się plikami statycznymi w `core/elements/`. Wymaga odpowiednich uprawnień zapisu, kopii zapasowych i praktyk wdrożeniowych. |
| `modxmcp.audit_log` | `1` | `0`/`1`. Zapisuje obsługiwane akcje modyfikujące w dziennikach MODX i `core/components/modxmcp/logs/audit.log`. **Nie** jest pełnym rejestrem każdego żądania. W środowisku produkcyjnym pozostaw włączone, kontrolując dostęp i rozmiar. |
| `modxmcp.debug` | `0` | `0`/`1`. Po włączeniu szczegółowe informacje o wewnętrznych wyjątkach mogą pojawiać się w odpowiedziach HTTP z błędami. Po wyłączeniu wywołujący otrzymują ogólny błąd i `error_id`. W środowisku produkcyjnym pozostaw `0`. |

## Ścieżka rdzenia

| Ustawienie | Wartość domyślna | Wartości i działanie |
| --- | --- | --- |
| `modxmcp.core_path` | `{core_path}components/modxmcp/` | Katalog rdzenia komponentu (akceptuje także `[[++core_path]]`). Używany przez API HTTP do ładowania implementacji i wersji. Zmieniaj tylko w zweryfikowanych niestandardowych instalacjach; nieprawidłowe wartości uniemożliwiają działanie API. |

## Bezpieczne pierwsze połączenie

1. Zainstaluj pakiet transportowy zgodny z główną wersją MODX. Nowe instalacje zaczynają z `modxmcp.enabled=0`; token jest generowany automatycznie, a istniejące tokeny pozostają zachowane przy rutynowych aktualizacjach.
2. Skonfiguruj HTTPS, opcjonalne ograniczenia IP, ustawienia odwrotnego proxy i `service_user_id`.
3. Użyj **Copy** w panelu komponentu w menedżerze, aby umieścić token API w magazynie sekretów klienta MCP. Nie ujawniaj go.
4. Włącz tylko wymagane funkcje. Pozostaw `allow_run_processor` i `allow_root_filesystem_read` wyłączone, jeśli nie ma uzasadnienia dla ich włączenia.
5. Włącz API i sprawdź **uwierzytelnioną akcję tylko do odczytu**. Odpowiedź `GET` o wersji/stanie **nie** dowodzi, że token klienta jest ważny.
