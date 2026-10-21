# MODX MCP — możliwości i katalog narzędzi

[English](CAPABILITIES.md) · [Русский](CAPABILITIES.ru.md) · [Español](CAPABILITIES.es.md) · [Deutsch](CAPABILITIES.de.md) · [Français](CAPABILITIES.fr.md) · [Українська](CAPABILITIES.uk.md) · [Қазақша](CAPABILITIES.kk.md) · [Nederlands](CAPABILITIES.nl.md) · [Polski](CAPABILITIES.pl.md) · [Instalacja](INSTALL.pl.md) · [Dokumentacja API](API.pl.md)

**MODX MCP 1.2.4 · 192 publiczne narzędzia · 19 grup funkcjonalnych · MODX Revolution 2.8.x / 3.x**

MODX MCP udostępnia klientowi MCP wyposażonemu w AI nazwane operacje do pracy z danymi i elementami MODX, zamiast wymagać wyrażania każdego zadania za pomocą surowego SQL lub poleceń systemu plików. Obsługuje inspekcję witryny, edycję treści i kodu, wartości TV, pakiety, administrację dostępem oraz wybrane dodatki innych producentów. Rzeczywista lista narzędzi zależy od zainstalowanej kompilacji i włączonych funkcji.

Ten przewodnik odpowiada na pytanie **„Co mogę zrobić za pomocą MODX MCP?”**. Pełny opis pól, typów, wartości wyliczeniowych i szczegółów integracji znajdziesz w [dokumentacji API](API.pl.md). Aby nawiązać połączenie, zacznij od [instalacji](INSTALL.pl.md).

**Etykiety wpływu są objaśnieniami, a nie kontrolą uprawnień.** „Odczyt” nadal może zwracać poufne dane, takie jak wartości konfiguracji. „Duży wpływ” ostrzega, że niewłaściwe użycie polecenia może uszkodzić witrynę lub naruszyć jej bezpieczeństwo.

## Indeks narzędzi

| Grupa | Narzędzia |
| --- | ---: |
| [Elementy i zasoby](#elementy-i-zasoby) | 15 |
| [TV zasobów](#tv-zasobów) | 4 |
| [Typy pól TV](#typy-pól-tv) | 2 |
| [Ustawienia systemowe](#ustawienia-systemowe) | 5 |
| [Źródła mediów i pliki](#źródła-mediów-i-pliki) | 13 |
| [Komponenty i pliki](#komponenty-i-pliki) | 4 |
| [Wyszukiwanie kodu i zależności](#wyszukiwanie-kodu-i-zależności) | 5 |
| [VersionX](#versionx) | 3 |
| [VirtualPage](#virtualpage) | 17 |
| [miniShop2](#minishop2) | 22 |
| [MIGX](#migx) | 5 |
| [Użytkownicy i prawa dostępu](#użytkownicy-i-prawa-dostępu) | 47 |
| [ClientConfig](#clientconfig) | 5 |
| [Zestawy właściwości](#zestawy-właściwości) | 7 |
| [Konteksty i ustawienia kontekstów](#konteksty-i-ustawienia-kontekstów) | 10 |
| [Pakiety i dostawcy](#pakiety-i-dostawcy) | 7 |
| [Przestrzenie nazw](#przestrzenie-nazw) | 4 |
| [Leksykon i tłumaczenia](#leksykon-i-tłumaczenia) | 4 |
| [Konserwacja i diagnostyka](#konserwacja-i-diagnostyka) | 13 |
| **Łącznie** | **192** |

## Elementy i zasoby

Zarządzaj stronami, szablonami, chunkami, snippetami, pluginami, TV i kategoriami jako obiektami MODX.

| Narzędzie | Działanie | Wpływ |
| --- | --- | --- |
| `modx_list_elements` | Wyświetla listę elementów MODX danego typu (id + name). | Odczyt |
| `modx_make_static` | Przekształca chunk/snippet/template/plugin w plik statyczny w core/elements/ (zapisuje plik, ustawia static=1 + source=Filesystem). | Duży wpływ |
| `modx_get_element` | Odczytuje element MODX (zwraca jego pola + pełny kod). | Odczyt |
| `modx_update_element_property` | Bezpiecznie zmienia jedną istniejącą właściwość domyślną ze sprawdzeniem oczekiwanej wartości przez CAS; nigdy nie zastępuje kodu ani innych właściwości. | Zmienia witrynę |
| `modx_update_element` | Aktualizuje element MODX, ZASTĘPUJĄC całą jego zawartość (prześlij kompletną nową zawartość). | Duży wpływ |
| `modx_create_element` | Tworzy nowy element MODX. | Duży wpływ |
| `modx_delete_element` | Usuwa element MODX. NAJPIERW uruchom z dry_run:true — bez usuwania zwróci, co zostałoby usunięte oraz gdzie element jest nadal przywoływany (dla zasobów: liczbę zasobów potomnych). Sprawdź to przed rzeczywistym usunięciem. | Duży wpływ |
| `modx_view_element` | Wyświetla chunk/snippet/template/plugin jako NUMEROWANE wiersze (jak cat -n), opcjonalnie w zakresie start_line/end_line. | Zmienia witrynę |
| `modx_edit_element_lines` | Edytuje chunk/snippet/template/plugin WIERSZ PO WIERSZU, przesyłając tylko zmienione wiersze (bez potrzeby ponownego wysyłania całego elementu). | Duży wpływ |
| `modx_bulk_resources` | Stosuje JEDNĄ operację do wielu zasobów naraz: publish, unpublish, set_template (wymaga template), move (wymaga parent_to i/lub context_to) lub delete. | Duży wpływ |
| `modx_duplicate_resource` | Duplikuje zasób. id = zasób źródłowy; opcjonalnie name (nowy pagetitle), duplicate_children (bool), published_mode (preserve\|publish\|unpublish). | Zmienia witrynę |
| `modx_duplicate_element` | Duplikuje element (chunk/snippet/template/plugin/tv). type + id; opcjonalnie name dla kopii. | Zmienia witrynę |
| `modx_undelete_resource` | Przywraca logicznie usunięty zasób z kosza (cofa usunięcie). id = identyfikator zasobu. | Zmienia witrynę |
| `modx_empty_recycle_bin` | Trwale usuwa WSZYSTKIE zasoby znajdujące się w koszu (usunięte logicznie). | Duży wpływ |
| `modx_reorder_resources` | Zmienia kolejność zasobów w drzewie przez ustawienie menuindex (i opcjonalnie parent). items = [{id, menuindex, parent?}], stosowane dla każdego zasobu. | Zmienia witrynę |

## TV zasobów

Odczytuj lub aktualizuj niestandardowe pola zasobów i sprawdzaj zapisane wartości TV.

| Narzędzie | Działanie | Wpływ |
| --- | --- | --- |
| `modx_get_resource_tvs` | Pobiera wartości zmiennych szablonu (TV) zasobu. | Odczyt |
| `modx_update_resource_tvs` | Aktualizuje wartości zmiennych szablonu (TV) zasobu. | Zmienia witrynę |
| `modx_list_tv_values` | Wyświetla jawnie zapisane wartości jednej TV we wszystkich zasobach, w tym nieaktualne wartości w zasobach, których obecny szablon może już nie używać tej TV. | Odczyt |
| `modx_clear_tv_values` | Pokazuje podgląd lub usuwa wszystkie jawnie zapisane wartości jednej TV. | Zmienia witrynę |

## Typy pól TV

Poznaj dostępne typy pól TV i wybierz odpowiedni do wymagań.

| Narzędzie | Działanie | Wpływ |
| --- | --- | --- |
| `modx_suggest_tv_type` | Nie wiesz, który typ pola wejściowego TV odpowiada potrzebie? Opisz ją (po angielsku lub rosyjsku), aby otrzymać uporządkowane propozycje field_types z uzasadnieniami oraz gotowy do edycji szkielet create_element dla najlepszej propozycji (z dodatkowymi kluczami wymaganymi przez ten typ). | Odczyt |
| `modx_list_tv_input_types` | Wyświetla typy pól wejściowych (widżetów) TV dostępne w tej witrynie. | Odczyt |

## Ustawienia systemowe

Sprawdzaj i edytuj konfigurację, która może wpływać na całą witrynę.

| Narzędzie | Działanie | Wpływ |
| --- | --- | --- |
| `modx_list_system_settings` | Wyświetla ustawienia systemowe MODX. | Odczyt |
| `modx_get_system_setting` | Pobiera jedno ustawienie systemowe MODX według klucza. | Odczyt |
| `modx_create_system_setting` | Tworzy ustawienie systemowe MODX. | Zmienia witrynę |
| `modx_update_system_setting` | Aktualizuje ustawienie systemowe MODX według klucza. | Duży wpływ |
| `modx_delete_system_setting` | Usuwa ustawienie systemowe MODX według klucza. | Duży wpływ |

## Źródła mediów i pliki

Przeglądaj źródła mediów i zarządzaj udostępnianymi przez nie plikami i folderami.

| Narzędzie | Działanie | Wpływ |
| --- | --- | --- |
| `modx_list_media_sources` | Wyświetla źródła mediów MODX. | Odczyt |
| `modx_get_media_source` | Pobiera jedno źródło mediów MODX. | Odczyt |
| `modx_create_media_source` | Tworzy źródło mediów (plików). Domyślne class_key to sources.modFileMediaSource. 'properties' to mapa {name:value} parametrów źródła (np. {"basePath":"assets/files/","baseUrl":"assets/files/"}) scalana ze źródłem. | Zmienia witrynę |
| `modx_update_media_source` | Aktualizuje źródło mediów. 'properties' to mapa {name:value} scalana z parametrami źródła (pozostałe są zachowywane) — użyj jej do ustawienia basePath/baseUrl/itd. | Zmienia witrynę |
| `modx_delete_media_source` | Usuwa źródło mediów według id. | Zmienia witrynę |
| `modx_list_media_source_files` | Wyświetla pliki i foldery w źródle mediów. | Odczyt |
| `modx_read_media_source_file` | Odczytuje plik w źródle mediów. | Odczyt |
| `modx_create_media_file` | Tworzy plik w źródle mediów (zapisuje zawartość). source = id lub nazwa źródła, path = katalog docelowy (względem katalogu bazowego źródła), name = nazwa pliku, content = treść pliku. | Zmienia witrynę |
| `modx_update_media_file` | Nadpisuje zawartość pliku w źródle mediów. source = id/nazwa, path = ścieżka pliku wewnątrz źródła, content = nowa treść. | Zmienia witrynę |
| `modx_delete_media_file` | Usuwa plik w źródle mediów. source = id/nazwa, path = ścieżka pliku wewnątrz źródła. | Duży wpływ |
| `modx_rename_media_file` | Zmienia nazwę pliku w źródle mediów. source = id/nazwa, path = bieżąca ścieżka pliku, new_name = nowa nazwa pliku. | Zmienia witrynę |
| `modx_create_media_folder` | Tworzy folder w źródle mediów. source = id/nazwa, parent = katalog nadrzędny (domyślnie katalog główny), name = nazwa folderu. | Zmienia witrynę |
| `modx_delete_media_folder` | Usuwa folder (wraz z zawartością) w źródle mediów. source = id/nazwa, path = ścieżka folderu wewnątrz źródła. | Duży wpływ |

## Komponenty i pliki

Poznaj zainstalowane dodatki i sprawdzaj pliki, do których dostęp jest dozwolony.

| Narzędzie | Działanie | Wpływ |
| --- | --- | --- |
| `modx_list_installed_components` | Wyświetla zainstalowane komponenty MODX z core/components i assets/components. | Odczyt |
| `modx_get_component_files` | Wyświetla pliki i foldery wewnątrz zainstalowanego komponentu MODX. | Odczyt |
| `modx_read_component_file` | Odczytuje plik z zainstalowanego komponentu MODX. | Odczyt |
| `modx_check_integrations` | Informuje, które popularne dodatki MODX (miniShop2, MIGX, pdoTools, Tickets itd.) są zainstalowane w tej witrynie i co modxMCP potrafi zrobić z każdym z nich. | Odczyt |

## Wyszukiwanie kodu i zależności

Znajduj szczegóły implementacji, odwołania i powiązania między elementami witryny.

| Narzędzie | Działanie | Wpływ |
| --- | --- | --- |
| `modx_search_code` | Wyszukuje pełnotekstowo w TREŚCI elementów i zasobów (oraz w nazwach). | Odczyt |
| `modx_find_usages` | Znajduje miejsca użycia elementu: dopasowania jego nazwy w treści całego kodu, a dla szablonu także zasoby przypisane do tego szablonu. | Odczyt |
| `modx_list_resources` | Wyświetla zasoby (drzewo treści), opcjonalnie filtrowane według parent, context, stanu deleted lub zapytania pagetitle/alias/uri. | Odczyt |
| `modx_replace_across` | Wyszukuje i zamienia w elementach kodu całej witryny (chunk/snippet/template/plugin): znajduje każdy element, którego TREŚĆ zawiera find, i zastępuje WSZYSTKIE wystąpienia przez replacement w JEDNYM wywołaniu (bez odczytów/zapisów poszczególnych elementów po Twojej stronie). | Duży wpływ |
| `modx_dependency_graph` | W jednym wywołaniu odwzorowuje POWIĄZANIA elementów witryny: który szablon dołącza który chunk/snippet/TV, który snippet renderuje który chunk oraz gdzie przypięta jest każda TV. | Odczyt |

## VersionX

Sprawdzaj lub przywracaj historyczne rewizje, gdy zainstalowano VersionX.

| Narzędzie | Działanie | Wpływ |
| --- | --- | --- |
| `modx_versionx_list_versions` | Wyświetla wersje VersionX zasobu, chunka, snippetu, szablonu, pluginu lub TV w MODX. | Odczyt |
| `modx_versionx_get_version` | Odczytuje dane jednej wersji VersionX przed podjęciem decyzji o jej przywróceniu. | Zmienia witrynę |
| `modx_versionx_revert_version` | Przywraca obiekt MODX do określonej wersji VersionX. | Zmienia witrynę |

## VirtualPage

Zarządzaj powiązaniami zdarzeń, procedurami obsługi i trasami udostępnianymi przez VirtualPage.

| Narzędzie | Działanie | Wpływ |
| --- | --- | --- |
| `modx_virtualpage_list_events` | Wyświetla zdarzenia VirtualPage wiążące grupy tras ze zdarzeniami MODX. | Zmienia witrynę |
| `modx_virtualpage_get_event` | Odczytuje jedno zdarzenie VirtualPage według id lub nazwy, wraz z jego trasami. | Zmienia witrynę |
| `modx_virtualpage_create_event` | Tworzy zdarzenie VirtualPage, zwykle OnPageNotFound lub OnHandleRequest. | Zmienia witrynę |
| `modx_virtualpage_update_event` | Aktualizuje zdarzenie VirtualPage według id lub nazwy. | Zmienia witrynę |
| `modx_virtualpage_list_handlers` | Wyświetla procedury obsługi VirtualPage renderujące zasoby, snippety, chunki lub zasoby dynamiczne. | Zmienia witrynę |
| `modx_virtualpage_get_handler` | Odczytuje jedną procedurę obsługi VirtualPage według id lub nazwy, wraz z trasami, które jej używają. | Zmienia witrynę |
| `modx_virtualpage_create_handler` | Tworzy procedurę obsługi VirtualPage. type może mieć wartość resource, snippet, chunk, dynamic_resource/template lub 0..3. | Zmienia witrynę |
| `modx_virtualpage_update_handler` | Aktualizuje procedurę obsługi VirtualPage według id lub nazwy. | Zmienia witrynę |
| `modx_virtualpage_list_routes` | Wyświetla trasy VirtualPage z nazwami ich zdarzeń i procedur obsługi. | Zmienia witrynę |
| `modx_virtualpage_get_route` | Odczytuje jedną trasę VirtualPage według id albo według route i opcjonalnego method. | Zmienia witrynę |
| `modx_virtualpage_create_route` | Tworzy trasę VirtualPage i w razie potrzeby wiąże plugin jej zdarzenia. | Zmienia witrynę |
| `modx_virtualpage_update_route` | Aktualizuje trasę VirtualPage według id. | Zmienia witrynę |
| `modx_virtualpage_resolve_route` | Symuluje dopasowanie trasy VirtualPage i zwraca procedurę obsługi oraz symbole zastępcze vp.*. | Odczyt |
| `modx_virtualpage_delete_event` | Usuwa zdarzenie VirtualPage według id lub nazwy (zgodnie ze schematem VP usuwa również jego trasy). | Zmienia witrynę |
| `modx_virtualpage_delete_handler` | Usuwa procedurę obsługi VirtualPage według id lub nazwy. | Zmienia witrynę |
| `modx_virtualpage_delete_route` | Usuwa trasę VirtualPage według id lub nazwy. | Zmienia witrynę |
| `modx_virtualpage_clear_cache` | Czyści pliki tras/pamięci podręcznej VirtualPage i odświeża pamięć podręczną MODX. | Zmienia witrynę |

## miniShop2

Pracuj z opcjami sklepu, kategoriami, powiązaniami produktów i zamówieniami.

| Narzędzie | Działanie | Wpływ |
| --- | --- | --- |
| `modx_ms2_list_option_types` | Wyświetla dostępne typy pól opcji produktów miniShop2. | Zmienia witrynę |
| `modx_ms2_list_options` | Wyświetla opcje produktów miniShop2 z zakładki opcji w ustawieniach. | Zmienia witrynę |
| `modx_ms2_get_option` | Odczytuje jedną opcję produktu miniShop2 według id. | Zmienia witrynę |
| `modx_ms2_create_option` | Tworzy opcję produktu miniShop2 w opcjach ustawień. | Zmienia witrynę |
| `modx_ms2_update_option` | Aktualizuje opcję produktu miniShop2 w opcjach ustawień. | Zmienia witrynę |
| `modx_ms2_assign_option_to_category` | Przypisuje istniejącą opcję miniShop2 do msCategory. | Zmienia witrynę |
| `modx_ms2_get_product_options` | Odczytuje wartości opcji miniShop2 przypisane do produktu. | Zmienia witrynę |
| `modx_ms2_update_product_options` | Tworzy, aktualizuje lub usuwa wartości opcji miniShop2 dla produktu. | Zmienia witrynę |
| `modx_ms2_list_link_types` | Wyświetla typy powiązań miniShop2 (msLink): id, type, name, description. | Zmienia witrynę |
| `modx_ms2_get_link_type` | Pobiera typ powiązania miniShop2 według id. | Zmienia witrynę |
| `modx_ms2_create_link_type` | Tworzy typ powiązania miniShop2. 'type' to rodzaj relacji decydujący o sposobie łączenia produktów. | Zmienia witrynę |
| `modx_ms2_update_link_type` | Aktualizuje typ powiązania miniShop2. | Zmienia witrynę |
| `modx_ms2_delete_link_type` | Usuwa typ powiązania miniShop2 według id. | Zmienia witrynę |
| `modx_ms2_list_product_links` | Wyświetla powiązania między produktami (msProductLink), opcjonalnie dla jednego produktu. | Zmienia witrynę |
| `modx_ms2_create_product_link` | Łączy dwa produkty za pomocą typu powiązania. Rodzaj relacji tego typu decyduje, czy tworzone jest również powiązanie odwrotne. | Zmienia witrynę |
| `modx_ms2_delete_product_link` | Usuwa powiązanie produktów (według typu powiązania + master + slave). | Zmienia witrynę |
| `modx_ms2_list_categories` | Wyświetla kategorie miniShop2 (msCategory). | Zmienia witrynę |
| `modx_ms2_create_category` | Tworzy kategorię miniShop2 (zasób msCategory). | Zmienia witrynę |
| `modx_ms2_update_category` | Aktualizuje kategorię miniShop2. | Zmienia witrynę |
| `modx_ms2_list_orders` | Wyświetla zamówienia miniShop2 (msOrder), z możliwością filtrowania według statusu/klienta/kontekstu/zakresu dat. | Zmienia witrynę |
| `modx_ms2_get_order` | Pobiera zamówienie miniShop2 według id (z pozycjami/klientem). | Zmienia witrynę |
| `modx_ms2_update_order` | Aktualizuje zamówienie miniShop2 — zwykle w celu zmiany statusu (uruchamia logikę zmiany statusu ms2), dostawy lub płatności. | Zmienia witrynę |

## MIGX

Odczytuj lub zmieniaj konfiguracje MIGX, gdy dodatek jest zainstalowany.

| Narzędzie | Działanie | Wpływ |
| --- | --- | --- |
| `modx_migx_list_configs` | Wyświetla konfiguracje MIGX (migxConfig): id, name, category, published. | Zmienia witrynę |
| `modx_migx_get_config` | Pobiera pełną konfigurację MIGX według id (formtabs, columns, buttons, filters, permissions — jako zapisane ciągi JSON). | Zmienia witrynę |
| `modx_migx_create_config` | Tworzy konfigurację MIGX. formtabs/columns/contextmenus/actionbuttons/columnbuttons/filters to ciągi JSON (ten sam format, który MIGX zapisuje w menedżerze). | Zmienia witrynę |
| `modx_migx_update_config` | Aktualizuje konfigurację MIGX. Zmienia tylko przekazane pola. | Zmienia witrynę |
| `modx_migx_delete_config` | Usuwa konfigurację MIGX według id. | Zmienia witrynę |

## Użytkownicy i prawa dostępu

Administruj użytkownikami, grupami, rolami, politykami i uprawnieniami do zasobów/kontekstów.

| Narzędzie | Działanie | Wpływ |
| --- | --- | --- |
| `modx_flush_permissions` | Czyści uprawnienia dostępu z pamięci podręcznej (funkcja menedżera 'Flush Permissions'), aby zmiany ACL zaczęły obowiązywać. | Zmienia witrynę |
| `modx_list_users` | Wyświetla użytkowników menedżera/witryny (modUser). | Odczyt |
| `modx_get_user` | Pobiera użytkownika według id. | Odczyt |
| `modx_create_user` | Tworzy użytkownika. Przekaż 'password', aby jawnie ustawić hasło; pomiń, aby wygenerować je automatycznie. Przyjmowane są także pola profilu (email, fullname, phone…). | Zmienia witrynę |
| `modx_update_user` | Aktualizuje użytkownika (pola profilu, stan aktywności). | Zmienia witrynę |
| `modx_delete_user` | Usuwa użytkownika według id. | Zmienia witrynę |
| `modx_list_user_groups` | Wyświetla grupy użytkowników MODX (id, name, parent, rank). | Odczyt |
| `modx_get_user_group` | Pobiera pojedynczą grupę użytkowników według id. | Odczyt |
| `modx_create_user_group` | Tworzy grupę użytkowników. | Zmienia witrynę |
| `modx_update_user_group` | Aktualizuje grupę użytkowników. | Zmienia witrynę |
| `modx_delete_user_group` | Usuwa grupę użytkowników według id. | Zmienia witrynę |
| `modx_list_user_group_members` | Wyświetla członków grupy użytkowników. | Odczyt |
| `modx_add_user_to_group` | Dodaje użytkownika do grupy użytkowników, opcjonalnie z rolą. | Zmienia witrynę |
| `modx_update_group_member` | Aktualizuje członkostwo użytkownika w grupie (np. zmienia rolę). | Zmienia witrynę |
| `modx_remove_user_from_group` | Usuwa użytkownika z grupy użytkowników. | Zmienia witrynę |
| `modx_list_roles` | Wyświetla role dostępu (id, name, authority). | Odczyt |
| `modx_get_role` | Pobiera rolę według id. | Odczyt |
| `modx_create_role` | Tworzy rolę. | Zmienia witrynę |
| `modx_update_role` | Aktualizuje rolę. | Zmienia witrynę |
| `modx_delete_role` | Usuwa rolę według id. | Zmienia witrynę |
| `modx_list_access_policies` | Wyświetla polityki dostępu (id, name, description, template). | Odczyt |
| `modx_get_access_policy` | Pobiera politykę dostępu według id. | Odczyt |
| `modx_create_access_policy` | Tworzy politykę dostępu. | Zmienia witrynę |
| `modx_update_access_policy` | Aktualizuje politykę dostępu (np. jej mapę uprawnień). | Duży wpływ |
| `modx_delete_access_policy` | Usuwa politykę dostępu według id. | Zmienia witrynę |
| `modx_list_access_policy_templates` | Wyświetla szablony polityk dostępu. | Odczyt |
| `modx_get_access_policy_template` | Pobiera szablon polityki dostępu według id. | Odczyt |
| `modx_create_access_policy_template` | Tworzy szablon polityki dostępu. | Zmienia witrynę |
| `modx_update_access_policy_template` | Aktualizuje szablon polityki dostępu. | Zmienia witrynę |
| `modx_delete_access_policy_template` | Usuwa szablon polityki dostępu według id. | Zmienia witrynę |
| `modx_list_access_permissions` | Wyświetla uprawnienia zdefiniowane przez szablon polityki (katalog tylko do odczytu). | Odczyt |
| `modx_list_resource_groups` | Wyświetla grupy zasobów (id, name). | Odczyt |
| `modx_get_resource_group` | Pobiera grupę zasobów według id. | Odczyt |
| `modx_list_resource_group_resources` | Wyświetla zasoby przypisane do grupy zasobów. | Odczyt |
| `modx_create_resource_group` | Tworzy grupę zasobów. | Zmienia witrynę |
| `modx_update_resource_group` | Aktualizuje grupę zasobów. | Zmienia witrynę |
| `modx_delete_resource_group` | Usuwa grupę zasobów według id. | Zmienia witrynę |
| `modx_assign_resource_to_group` | Dodaje zasób (dokument) do grupy zasobów. | Zmienia witrynę |
| `modx_remove_resource_from_group` | Usuwa zasób (dokument) z grupy zasobów. | Zmienia witrynę |
| `modx_list_context_access` | Wyświetla wpisy ACL dostępu do kontekstu, opcjonalnie filtrowane według usergroup/context/policy. | Odczyt |
| `modx_grant_context_access` | Przyznaje grupie użytkowników dostęp do kontekstu z polityką i minimalną wartością authority roli. | Zmienia witrynę |
| `modx_update_context_access` | Aktualizuje wpis ACL dostępu do kontekstu według id. | Zmienia witrynę |
| `modx_revoke_context_access` | Usuwa wpis ACL dostępu do kontekstu według id. | Zmienia witrynę |
| `modx_list_resourcegroup_access` | Wyświetla wpisy ACL dostępu do grup zasobów. | Odczyt |
| `modx_grant_resourcegroup_access` | Przyznaje grupie użytkowników dostęp do grupy zasobów z polityką i minimalną wartością authority roli. | Zmienia witrynę |
| `modx_update_resourcegroup_access` | Aktualizuje wpis ACL dostępu do grupy zasobów według id. | Zmienia witrynę |
| `modx_revoke_resourcegroup_access` | Usuwa wpis ACL dostępu do grupy zasobów według id. | Zmienia witrynę |

## ClientConfig

Zarządzaj ustawieniami dostarczanymi przez dodatek ClientConfig.

| Narzędzie | Działanie | Wpływ |
| --- | --- | --- |
| `modx_clientconfig_list_settings` | Wyświetla ustawienia ClientConfig, w tym wartości właściwe dla poszczególnych kontekstów. | Zmienia witrynę |
| `modx_clientconfig_get_setting` | Pobiera ustawienie ClientConfig według id lub klucza. | Zmienia witrynę |
| `modx_clientconfig_create_setting` | Tworzy ustawienie ClientConfig. | Zmienia witrynę |
| `modx_clientconfig_update_setting` | Aktualizuje ustawienie ClientConfig według id lub klucza. | Zmienia witrynę |
| `modx_clientconfig_delete_setting` | Usuwa ustawienie ClientConfig według id lub klucza. | Zmienia witrynę |

## Zestawy właściwości

Twórz i przypisuj zestawy właściwości MODX wielokrotnego użytku.

| Narzędzie | Działanie | Wpływ |
| --- | --- | --- |
| `modx_list_property_sets` | Wyświetla zestawy właściwości (modPropertySet): id, name, description, category. | Odczyt |
| `modx_get_property_set` | Pobiera zestaw właściwości według id (wraz z jego właściwościami). | Odczyt |
| `modx_create_property_set` | Tworzy zestaw właściwości. 'properties' to obiekt JSON zawierający definicje właściwości. | Zmienia witrynę |
| `modx_update_property_set` | Aktualizuje zestaw właściwości. | Zmienia witrynę |
| `modx_delete_property_set` | Usuwa zestaw właściwości według id. | Zmienia witrynę |
| `modx_assign_property_set` | Przypisuje zestaw właściwości do elementu. Zidentyfikuj element przez id + element_class (np. 'modSnippet') lub element_type ('snippet'/'chunk'/'template'/'plugin'/'tv'). | Zmienia witrynę |
| `modx_unassign_property_set` | Odłącza zestaw właściwości od elementu (identyfikacja taka sama jak przy przypisywaniu). | Zmienia witrynę |

## Konteksty i ustawienia kontekstów

Zarządzaj metadanymi kontekstów i konfiguracją ograniczoną do ich zakresu.

| Narzędzie | Działanie | Wpływ |
| --- | --- | --- |
| `modx_list_contexts` | Wyświetla konteksty (modContext): key, name, description, rank. | Odczyt |
| `modx_get_context` | Pobiera kontekst według klucza. | Odczyt |
| `modx_create_context` | Tworzy kontekst. 'key' to unikatowy identyfikator (np. 'mobile'). | Zmienia witrynę |
| `modx_update_context` | Aktualizuje kontekst (name/description/rank). 'settings' może być tablicą JSON ustawień kontekstu (użycie zaawansowane). | Zmienia witrynę |
| `modx_delete_context` | Usuwa kontekst według klucza. | Duży wpływ |
| `modx_list_context_settings` | Wyświetla ustawienia kontekstu (modContextSetting). | Odczyt |
| `modx_get_context_setting` | Pobiera jedno ustawienie kontekstu (według context_key + key). | Odczyt |
| `modx_create_context_setting` | Tworzy ustawienie na poziomie kontekstu (nadpisuje ustawienie systemowe dla tego kontekstu). | Zmienia witrynę |
| `modx_update_context_setting` | Aktualizuje ustawienie kontekstu (według context_key + key). | Zmienia witrynę |
| `modx_delete_context_setting` | Usuwa ustawienie kontekstu (według context_key + key). | Zmienia witrynę |

## Pakiety i dostawcy

Przeszukuj oferty dostawców pakietów oraz instaluj lub odinstalowuj dodatki MODX.

| Narzędzie | Działanie | Wpływ |
| --- | --- | --- |
| `modx_install_package` | Instaluje pakiet transportowy od dostawcy (domyślnie modx.com) według nazwy, np. {package:'MIGX'}. | Duży wpływ |
| `modx_uninstall_package` | Odinstalowuje pakiet transportowy według sygnatury (np. 'migx-2.13.0-pl'). | Duży wpływ |
| `modx_list_providers` | Wyświetla dostawców pakietów transportowych (modx.com i niestandardowych, np. modstore.pro): id, name, service_url. | Odczyt |
| `modx_search_packages` | Przeszukuje katalog dostawcy (użyj przed install_package). | Odczyt |
| `modx_create_provider` | Dodaje dostawcę pakietów transportowych (np. modstore.pro). | Zmienia witrynę |
| `modx_update_provider` | Aktualizuje dostawcę pakietów transportowych. | Zmienia witrynę |
| `modx_delete_provider` | Usuwa dostawcę pakietów transportowych według id. | Zmienia witrynę |

## Przestrzenie nazw

Rejestruj przestrzenie nazw i ścieżki dodatków oraz zarządzaj nimi.

| Narzędzie | Działanie | Wpływ |
| --- | --- | --- |
| `modx_list_namespaces` | Wyświetla przestrzenie nazw (modNamespace): name, path, assets_path. | Odczyt |
| `modx_create_namespace` | Tworzy przestrzeń nazw. | Zmienia witrynę |
| `modx_update_namespace` | Aktualizuje przestrzeń nazw (według nazwy). | Zmienia witrynę |
| `modx_delete_namespace` | Usuwa przestrzeń nazw według nazwy ('core' nie można usunąć). | Zmienia witrynę |

## Leksykon i tłumaczenia

Odczytuj lub nadpisuj przetłumaczone ciągi i tematy leksykonu.

| Narzędzie | Działanie | Wpływ |
| --- | --- | --- |
| `modx_list_lexicon_entries` | Wyświetla wpisy leksykonu, filtrowane według namespace + topic + language (i opcjonalnego wyszukiwania). | Odczyt |
| `modx_list_lexicon_topics` | Wyświetla tematy leksykonu dla namespace/language. | Odczyt |
| `modx_set_lexicon_entry` | Tworzy lub nadpisuje wpis leksykonu (nadpisanie wartości z pliku w bazie danych). | Zmienia witrynę |
| `modx_revert_lexicon_entry` | Przywraca wpis leksykonu — usuwa jego nadpisanie w bazie danych, aby obowiązywała wartość z pliku. | Zmienia witrynę |

## Konserwacja i diagnostyka

Sprawdzaj metadane witryny, jej stan i dzienniki oraz wykonuj czynności konserwacyjne.

| Narzędzie | Działanie | Wpływ |
| --- | --- | --- |
| `modx_get_site_state` | Odczytuje autorytatywną rewizję witryny z Connectora oraz informację o obsłudze atomowych warunków wstępnych używaną przez narzędzia zewnętrzne. | Odczyt |
| `modx_describe_object` | Introspekcja schematu: wyświetla pola klasy xPDO (nazwa + typ php/db, null, default) i jej klucz główny, aby używać RZECZYWISTYCH nazw pól zamiast zgadywać. | Odczyt |
| `modx_read_error_log` | Odczytuje końcówkę dziennika błędów MODX (core/cache/logs/error.log) do celów diagnostycznych. | Odczyt |
| `modx_refresh_uris` | Ponownie generuje wszystkie URI zasobów (uruchom po zbiorczych zmianach aliasów/struktury, które pozostawiły nieaktualne URI). | Zmienia witrynę |
| `modx_remove_locks` | Usuwa nieaktualne blokady edycji w menedżerze (gdy element/zasób jest zgłaszany jako zablokowany przez nieaktywną sesję). | Zmienia witrynę |
| `modx_project_overview` | Pozwala zorientować się w całej zainstalowanej witrynie za pomocą JEDNEGO zwięzłego wywołania o niskim koszcie. | Odczyt |
| `modx_system_info` | Informacje o środowisku/diagnostyce: wersja MODX, wersja modxMCP, wersja PHP, typ bazy danych, kluczowe ścieżki. | Odczyt |
| `modx_regenerate_token` | Zmienia modxmcp.api_token na nową losową wartość i ją zwraca. | Duży wpływ |
| `modx_help` | Wbudowana dokumentacja. Wywołaj bez argumentów, aby wyświetlić tematy; przekaż {topic}, aby otrzymać przewodnik (np. 'tv_input_types', 'study_component', 'minishop2', 'migx', 'acl', 'getting_started'). Przeczytaj odpowiedni temat przed nieznaną pracą lub aby dowiedzieć się, jak używać nowo zainstalowanego komponentu. | Odczyt |
| `modx_list_actions` | Wyświetla nazwy akcji obsługiwanych przez tę kompilację serwera modxMCP, pogrupowane według obszaru. | Odczyt |
| `modx_clear_cache` | Odświeża pamięć podręczną MODX. Przekaż partitions (np. ['resource','context_settings']), aby odświeżyć tylko te partycje; pomiń, aby odświeżyć całość. | Zmienia witrynę |
| `modx_read_audit_log` | Odczytuje dziennik audytu zapisów modxMCP (najnowsze wpisy na końcu). | Odczyt |
| `modx_run_processor` | Uruchamia bezpośrednio DOWOLNY procesor MODX (mechanizm wyjścia poza standardowy zestaw operacji). | Duży wpływ |

## Jak korzystać z katalogu

Zgodna aplikacja hostująca MCP wykryje dostępne narzędzia i przekaże zweryfikowane argumenty do lokalnego klienta MCP. Nie musisz zapamiętywać nazw poleceń. Na przykład ustalenie, jak strona jest renderowana, może wymagać wyświetlenia jej zasobu, odczytania przypisanego szablonu i sprawdzenia przywołanego chunka lub snippetu. Są to standardowe operacje publiczne, a nie narzucony sposób pracy agenta.

Jeśli brakuje akcji, sprawdź, czy jej grupa funkcji nie została wyłączona, czy wymagany dodatek jest zainstalowany i czy wersje klienta/serwera są zgodne. `modx_list_actions` informuje o akcjach znanych zainstalowanemu serwerowi.

## Przypomnienie o bezpieczeństwie

Dane uwierzytelniające `X-MCP-Token` autoryzują użytkownika usługowego o wysokich uprawnieniach. Sama znajomość nazw tych narzędzi nie daje dostępu. Jednak atakujący, który uzyska ważny token, może potencjalnie modyfikować elementy PHP i ustawienia systemowe. Nie traktuj etykiety „Odczyt” w tym dokumencie jako granicy bezpieczeństwa. Zobacz [Bezpieczeństwo](SECURITY.pl.md).

Szczegóły implementacji znajdziesz w [publicznych definicjach klienta](https://github.com/rumata-estor/modx3-mcp/blob/main/client/index.js).
