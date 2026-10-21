# MODX MCP — bezpieczeństwo i model zaufania

[English](SECURITY.md) · [Русский](SECURITY.ru.md) · [Español](SECURITY.es.md) · [Deutsch](SECURITY.de.md) · [Français](SECURITY.fr.md) · [Українська](SECURITY.uk.md) · [Қазақша](SECURITY.kk.md) · [Nederlands](SECURITY.nl.md) · [Polski](SECURITY.pl.md) · [Instalacja](INSTALL.pl.md) · [Możliwości](CAPABILITIES.pl.md) · [API](API.pl.md)

Ten przewodnik opisuje granicę zaufania publicznego **MODX MCP 1.2.4**, jego ustawienia bezpieczeństwa i istotne ograniczenia. Nie opisuje żadnej zewnętrznej infrastruktury zarządzania agentami.

## Najważniejsza zasada

**Traktuj token API MODX MCP jak administracyjne dane uwierzytelniające.** Sama znajomość adresu URL punktu końcowego lub udokumentowanych nazw narzędzi nie uprawnia do wykonania żądania. Ważny `X-MCP-Token` może jednak umożliwić szeroko zakrojone zmiany w witrynie.

Po uwierzytelnieniu żądania serwer wybiera konto usługowe z uprawnieniami MODX **`sudo`**. `modxmcp.service_user_id` może wskazywać aktywne konto `sudo`; przy domyślnej wartości zero wybierany jest pierwszy aktywny użytkownik `sudo`. **Nie jest** to autoryzacja poszczególnych użytkowników zewnętrznych na podstawie indywidualnych uprawnień operatora w menedżerze.

Posiadacz tokenu może potencjalnie zmieniać snippety, pluginy, szablony, zasoby, ustawienia i inne wrażliwe treści. Edycja PHP w wykonywalnych elementach MODX może prowadzić do wykonania dowolnego kodu PHP z uprawnieniami procesu aplikacji WWW.

## Zabezpieczenia punktu końcowego PHP

| Zabezpieczenie | Działanie i ograniczenia |
| --- | --- |
| Token | Wymaga `X-MCP-Token`; sprawdzany względem ustawienia `modxmcp.api_token`. |
| HTTPS | `modxmcp.require_https` jest domyślnie włączone; sprawdź konfigurację terminacji TLS. |
| Lista dozwolonych IP | `modxmcp.allowed_ips` porównuje `REMOTE_ADDR` z dokładnymi adresami IP lub zakresami IPv4 CIDR; pusta lista dopuszcza dowolny adres drugiej strony połączenia. |
| Odwrotne proxy | `modxmcp.trust_proxy_https` jest domyślnie wyłączone; włączaj tylko za zaufanym, poprawnie skonfigurowanym odwrotnym proxy. |
| Główny przełącznik | Wyłączone `modxmcp.enabled` blokuje uwierzytelnione operacje API. |
| Limit rozmiaru | `modxmcp.max_payload_bytes` ogranicza rozmiar przychodzących danych JSON. |
| Mechanizm bezpośredniego wywoływania procesorów | `modxmcp.allow_run_processor` jest domyślnie wyłączone. |
| Grupy funkcji | `modxmcp.disabled_groups` wyłącza wybrane rodziny narzędzi. |

**Wyłączone rodziny narzędzi nie stanowią pełnej granicy autoryzacji.** Standardowe narzędzia tworzenia/aktualizacji/usuwania ustawień systemowych odrzucają klucze zaczynające się od `modxmcp.`, w tym próby zmiany nazw innych ustawień na nazwy z tym prefiksem. Rotacja tokenu pozostaje oddzielną operacją uprzywilejowaną. Nie jest to środowisko izolowane: uwierzytelniony wywołujący, który może edytować wykonywalny PHP (lub wywoływać jawnie włączone procesory), nadal może wpływać na bezpieczeństwo witryny. Różni operatorzy wymagają niezależnych mechanizmów autoryzacji po stronie serwera.

Prawidłowo uwierzytelniany punkt końcowy nie oznacza wbudowanego ograniczania częstotliwości żądań. Jeśli wdrożenie wymaga takich limitów, egzekwuj je na poziomie odwrotnego proxy lub serwera WWW.

## Standardowy klient MCP Node.js a bezpośrednie wywołania HTTP

Dostarczony klient Node.js zawiera dodatkowe zabezpieczenia niektórych operacji modyfikujących, takie jak kopie zapasowe wybranych obiektów przed zmianą, jawne zezwolenia na ryzykowne operacje przez zmienne środowiskowe oraz koordynację wywołań. **Własne klienty wysyłające żądania bezpośrednio do `api.php` nie wykonują tych zabezpieczeń po stronie klienta.**

Serwer PHP również implementuje blokady i sprawdzanie warunków wstępnych dla niektórych modyfikacji oraz wpisy audytu. Nie sprawia to jednak, że bezpośredni dostęp HTTP jest równoważny użyciu standardowego klienta MCP.

Jeśli tworzysz własną integrację HTTP, zaimplementuj własne kopie zapasowe, autoryzację, potwierdzenia i procedury odzyskiwania.

## Zalecana konfiguracja produkcyjna

1. Używaj HTTPS z ważnym certyfikatem.
2. Przechowuj token w chronionej konfiguracji klienta lub menedżerze sekretów. Nigdy nie umieszczaj go w adresach URL, publicznym kodzie, zrzutach ekranu, zgłoszeniach do pomocy technicznej ani dziennikach.
3. W miarę możliwości ogranicz dostęp do punktu końcowego do znanych adresów IP klientów lub zabezpieczonej sieci/proxy. Sprawdź, jaki adres PHP faktycznie widzi w `REMOTE_ADDR`.
4. Pozostaw `modxmcp.allow_run_processor = 0`, chyba że ta funkcja jest konkretnie potrzebna.
5. Wyłącz nieużywane grupy funkcji, pamiętając, że nie jest to kontrola dostępu dla poszczególnych użytkowników.
6. Pozostaw `modxmcp.debug` wyłączone w środowisku produkcyjnym.
7. Utrzymuj niezależne, przetestowane kopie zapasowe witryny MODX i bazy danych.
8. Używaj `dry_run` w destrukcyjnych narzędziach zbiorczych, jeśli jest dostępne; ręcznie zatwierdzaj zmiany kodu, uprawnień i ustawień o dużym wpływie.
9. Zmień token API w razie podejrzenia przejęcia; niezwłocznie zaktualizuj konfigurację każdego uprawnionego klienta.

## Sekrety i informacje prywatne w odpowiedziach narzędzi

`modx_list_system_settings` i `modx_get_system_setting` mogą zwracać wartości zawierające **tokeny API i inne sekrety**. Nie przekazuj pełnych wyników do publicznych dzienników, zgłoszeń problemów, potoków analitycznych ani zewnętrznego modelu bez oceny ryzyka dla poufności.

Odczyty plików, dzienniki błędów, rekordy użytkowników i dane zamówień sklepu również mogą zawierać informacje prywatne lub wrażliwe.

## Zakres ochrony

Dokumentacja nie może zapobiec błędnemu zrozumieniu żądania przez klienta AI. Pojedyncza zmiana współdzielonego snippetu, pluginu lub szablonu może wpłynąć na wiele stron. Każda aplikacja przyznająca różne prawa wielu operatorom musi egzekwować te prawa w obrębie zaufanej części serwerowej, a nie wyłącznie za pomocą instrukcji w promptach lub ukrytej listy narzędzi.

Jeśli odkryjesz lukę, nie publikuj działających danych uwierzytelniających ani danych klientów w publicznym zgłoszeniu GitHub. Unieważnij ujawnione sekrety i zgłoś wystarczająco dużo oczyszczonych z danych wrażliwych informacji, aby opiekunowie mogli bezpiecznie odtworzyć problem.

## Współbieżność TV zasobów i CAS v2

Począwszy od opublikowanego pakietu transportowego 1.2.3 dla MODX 2 i MODX 3, `get_site_state` zgłasza `precondition_version=2` dla CAS wartości TV po stronie Connectora. Chronione żądanie `modx_update_resource_tvs` zawiera zarządzane przez Runtime `_runtime_preconditions`: zaobserwowaną rewizję witryny, ścisłe egzekwowanie zgodności rewizji oraz mapę `expected_tvs` zawierającą dokładnie nazwy zmienianych TV. Connector sprawdza te warunki pod blokadą modyfikacji witryny i odrzuca nieaktualne wartości zamiast po cichu je nadpisywać.

**Ograniczenie:** sam schemat MCP nie wymaga tego wewnętrznego zabezpieczenia, dlatego bezpośredniego wywołania bez zabezpieczenia **nie wolno** przedstawiać jako bezpiecznego wobec współbieżnych działań agentów. Użytkownicy Site Agent powinni korzystać ze zweryfikowanej ścieżki Runtime `modx-tv-change` lub Executor. Nieudane wywołania CAS wymagają ponownego odczytu i nowego planu, a nie ponawiania w ciemno.

## Implementacja

- [Punkt końcowy PHP i walidacja tokenu](https://github.com/rumata-estor/modx3-mcp/blob/main/core/components/modxmcp/endpoint/api.common.php)
- [Ustawienia domyślne](https://github.com/rumata-estor/modx3-mcp/blob/main/_build/data/transport.settings.php)
- [Standardowy klient MCP](https://github.com/rumata-estor/modx3-mcp/blob/main/client/index.js)
- [Dyspozytor akcji po stronie serwera](https://github.com/rumata-estor/modx3-mcp/blob/main/core/components/modxmcp/model/modxmcp.class.php)
