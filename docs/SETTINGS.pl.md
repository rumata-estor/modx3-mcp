# Ustawienia systemowe — MODX MCP

[English](SETTINGS.md) · [Русский](SETTINGS.ru.md) · [Español](SETTINGS.es.md) · [Deutsch](SETTINGS.de.md) · [Français](SETTINGS.fr.md) · [Українська](SETTINGS.uk.md) · [Қазақша](SETTINGS.kk.md) · [Nederlands](SETTINGS.nl.md) · [Polski](SETTINGS.pl.md)

Wszystkie 16 ustawień znajdziesz w „System → Ustawienia systemowe”, przestrzeń nazw `modxmcp`. Wartości domyślne obowiązują dla MODX 2 i MODX 3.

**Ważne:** wyłączenie grup funkcji nie zastępuje autoryzacji po stronie serwera. Bezpośredni klient HTTP nie dziedziczy automatycznie zabezpieczeń oficjalnego klienta Node.js.

| Klucz | Domyślnie | Opis |
| --- | --- | --- |
| `modxmcp.enabled` | `0` | Włącza lub wyłącza uwierzytelniane operacje API; po nowej instalacji wyłączone. |
| `modxmcp.api_token` | `(generated)` | Tajny token przesyłany w `X-MCP-Token`; standardowe aktualizacje go zachowują. |
| `modxmcp.service_user_id` | `0` | Użytkownik usługowy MODX; `0` wybiera pierwsze aktywne konto sudo. |
| `modxmcp.require_https` | `1` | Wymaga HTTPS; w środowisku produkcyjnym pozostaw włączone. |
| `modxmcp.trust_proxy_https` | `0` | Ufa nagłówkom HTTPS wyłącznie za prawidłowo skonfigurowanym zaufanym proxy. |
| `modxmcp.allowed_ips` | `(empty)` | Dozwolone adresy IP i zakresy IPv4 CIDR; puste = brak ograniczenia IP. |
| `modxmcp.disabled_groups` | `versionx,virtualpage,minishop2,migx,access,property_sets,contexts,package_management,namespaces,lexicon` | Wyłączone grupy narzędzi rozdzielone przecinkami; nie jest to pełna granica uprawnień. |
| `modxmcp.allow_run_processor` | `0` | Pozwala uruchamiać dowolne procesory MODX; domyślnie wyłączone. |
| `modxmcp.allow_root_filesystem_read` | `0` | Pozwala odczytywać główne źródło mediów „Filesystem”; domyślnie wyłączone. |
| `modxmcp.component_code_roots` | `core/components,assets/components` | Katalogi, których kod komponentów można przeglądać; nie chroni całego systemu plików. |
| `modxmcp.max_payload_bytes` | `1048576` | Maksymalny rozmiar treści JSON POST w bajtach; przekroczenie zwraca 413. |
| `modxmcp.max_read_bytes` | `262144` | Maksymalny rozmiar pojedynczego fragmentu odczytywanego pliku. |
| `modxmcp.auto_static` | `0` | Automatycznie tworzy statyczne pliki elementów. |
| `modxmcp.audit_log` | `1` | Zapisuje obsługiwane zmiany w dzienniku audytu, ale nie wszystkie żądania. |
| `modxmcp.debug` | `0` | Pokazuje szczegóły wewnętrznych błędów; wyłącz w produkcji. |
| `modxmcp.core_path` | `{core_path}components/modxmcp/` | Ścieżka do katalogu core komponentu; zmieniaj tylko w zweryfikowanych instalacjach niestandardowych. |

**Pełne dokumenty techniczne:** [parametry API (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/API.md) · [bezpieczeństwo (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/SECURITY.md) · [dla programistów (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/DEVELOPMENT.md). Szczegółowe tabele techniczne są obecnie dostępne po angielsku.
