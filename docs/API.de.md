# API-Referenz — MODX MCP

[English](API.md) · [Русский](API.ru.md) · [Español](API.es.md) · [Deutsch](API.de.md) · [Français](API.fr.md) · [Українська](API.uk.md) · [Қазақша](API.kk.md) · [Nederlands](API.nl.md) · [Polski](API.pl.md)

> Hinweis: Diese Seite ist eine übersetzte Kurzübersicht. Die vollständigen Parametertabellen der 192 MCP-Werkzeuge sind weiterhin auf Englisch und Russisch verfügbar.
Öffentliche MCP-Werkzeuge tragen das Präfix `modx_`. Die PHP-HTTP-API verwendet dagegen Aktionsnamen ohne Präfix. Die vollständigen Parametertabellen und JSON-Schemata bleiben in der englischen Referenz verfügbar.

## MCP

```json
{"name":"modx_get_element","arguments":{"type":"chunk","name":"Header"}}
```

## HTTP

```http
POST /assets/components/modxmcp/api.php HTTP/1.1
Host: example.com
Content-Type: application/json
X-MCP-Token: <private token>

{"action":"get_element","type":"chunk","data":{"name":"Header"}}
```

Senden Sie `POST` mit JSON und `X-MCP-Token` über HTTPS. `api.php` ist kein MCP-JSON-RPC-Endpunkt: `tools/call` wird dort nicht direkt verarbeitet.

**Vollständige technische Referenzen:** [API-Parameter (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/API.md) · [Sicherheit (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/SECURITY.md) · [Entwicklerdokumentation (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/DEVELOPMENT.md). Die ausführlichen englischen Referenztabellen sind maßgeblich.


## Verzeichnis der 192 MCP-Werkzeuge

> Die Beschreibungen sind übersetzt; verbindliche Felder, Typen, Grenzen und Aufzählungen stehen in der verlinkten englischen oder russischen technischen Referenz.

[Ausführliche Funktionen (Deutsch)](CAPABILITIES.de.md) · [English API schemas](API.md) · [Русский API](API.ru.md)

### Elemente und Ressourcen

| Werkzeug | Lokalisierte Beschreibung | Auswirkung |
| --- | --- | --- |
| [modx_list_elements](API.md#modx_list_elements) | MODX-Elemente eines Typs auflisten (id + name). | Lesen |
| [modx_make_static](API.md#modx_make_static) | Einen Chunk/ein Snippet/Template/Plugin in eine statische Datei unter core/elements/ umwandeln (schreibt die Datei, setzt static=1 + source=Filesystem). | Weitreichende Auswirkungen |
| [modx_get_element](API.md#modx_get_element) | Ein MODX-Element lesen (liefert seine Felder + den vollständigen Code). | Lesen |
| [modx_update_element_property](API.md#modx_update_element_property) | Eine vorhandene Standardeigenschaft sicher mit einer CAS-Prüfung des erwarteten Werts ändern; niemals Code oder andere Eigenschaften ersetzen. | Ändert die Website |
| [modx_update_element](API.md#modx_update_element) | Ein MODX-Element durch ERSETZEN seines gesamten Inhalts aktualisieren (den vollständigen neuen Inhalt senden). | Weitreichende Auswirkungen |
| [modx_create_element](API.md#modx_create_element) | Ein neues MODX-Element erstellen. | Weitreichende Auswirkungen |
| [modx_delete_element](API.md#modx_delete_element) | Ein MODX-Element löschen. ZUERST mit dry_run:true ausführen — liefert ohne Löschung, was gelöscht würde und wo das Element noch referenziert wird (bei Ressourcen: Anzahl untergeordneter Ressourcen). Dies vor der tatsächlichen Löschung prüfen. | Weitreichende Auswirkungen |
| [modx_view_element](API.md#modx_view_element) | Einen Chunk/ein Snippet/Template/Plugin als NUMMERIERTE Zeilen anzeigen (wie cat -n), optional auf einen Ausschnitt mit start_line/end_line begrenzt. | Ändert die Website |
| [modx_edit_element_lines](API.md#modx_edit_element_lines) | Einen Chunk/ein Snippet/Template/Plugin ZEILENWEISE bearbeiten und nur die geänderten Zeilen senden (das gesamte Element muss nicht erneut gesendet werden). | Weitreichende Auswirkungen |
| [modx_bulk_resources](API.md#modx_bulk_resources) | EINE Operation gleichzeitig auf viele Ressourcen anwenden: publish, unpublish, set_template (benötigt template), move (benötigt parent_to und/oder context_to) oder delete. | Weitreichende Auswirkungen |
| [modx_duplicate_resource](API.md#modx_duplicate_resource) | Eine Ressource duplizieren. id = Quellressource; optional name (neuer pagetitle), duplicate_children (bool), published_mode (preserve\|publish\|unpublish). | Ändert die Website |
| [modx_duplicate_element](API.md#modx_duplicate_element) | Ein Element (chunk/snippet/template/plugin/tv) duplizieren. type + id; optional name für die Kopie. | Ändert die Website |
| [modx_undelete_resource](API.md#modx_undelete_resource) | Eine vorläufig gelöschte Ressource aus dem Papierkorb wiederherstellen (Löschmarkierung aufheben). id = Ressourcen-ID. | Ändert die Website |
| [modx_empty_recycle_bin](API.md#modx_empty_recycle_bin) | ALLE Ressourcen im Papierkorb (vorläufig gelöschte Ressourcen) endgültig entfernen. | Weitreichende Auswirkungen |
| [modx_reorder_resources](API.md#modx_reorder_resources) | Ressourcen im Baum durch Setzen von menuindex (und optional parent) neu ordnen. items = [{id, menuindex, parent?}], wird pro Ressource angewendet. | Ändert die Website |

### Ressourcen-TVs

| Werkzeug | Lokalisierte Beschreibung | Auswirkung |
| --- | --- | --- |
| [modx_get_resource_tvs](API.md#modx_get_resource_tvs) | Die Werte der Template-Variablen (TVs) einer Ressource abrufen. | Lesen |
| [modx_update_resource_tvs](API.md#modx_update_resource_tvs) | Die Werte der Template-Variablen (TVs) einer Ressource aktualisieren. | Ändert die Website |
| [modx_list_tv_values](API.md#modx_list_tv_values) | Explizit gespeicherte Werte einer TV über alle Ressourcen auflisten, einschließlich veralteter Werte auf Ressourcen, deren aktuelles Template diese TV möglicherweise nicht mehr verwendet. | Lesen |
| [modx_clear_tv_values](API.md#modx_clear_tv_values) | Alle explizit gespeicherten Werte einer TV in einer Vorschau anzeigen oder löschen. | Ändert die Website |

### TV-Feldtypen

| Werkzeug | Lokalisierte Beschreibung | Auswirkung |
| --- | --- | --- |
| [modx_suggest_tv_type](API.md#modx_suggest_tv_type) | Unsicher, welcher TV-Eingabetyp zu einer Anforderung passt? Beschreiben Sie sie (auf Englisch oder Russisch), um eine Rangliste geeigneter field_types mit Begründungen + ein bearbeitbares create_element-Grundgerüst für die beste Wahl zu erhalten (mit den zusätzlich für diesen Typ erforderlichen Schlüsseln). | Lesen |
| [modx_list_tv_input_types](API.md#modx_list_tv_input_types) | Die auf dieser Website verfügbaren TV-Eingabetypen (Widgets) auflisten. | Lesen |

### Systemeinstellungen

| Werkzeug | Lokalisierte Beschreibung | Auswirkung |
| --- | --- | --- |
| [modx_list_system_settings](API.md#modx_list_system_settings) | MODX-Systemeinstellungen auflisten. | Lesen |
| [modx_get_system_setting](API.md#modx_get_system_setting) | Eine MODX-Systemeinstellung anhand von key abrufen. | Lesen |
| [modx_create_system_setting](API.md#modx_create_system_setting) | Eine MODX-Systemeinstellung erstellen. | Ändert die Website |
| [modx_update_system_setting](API.md#modx_update_system_setting) | Eine MODX-Systemeinstellung anhand von key aktualisieren. | Weitreichende Auswirkungen |
| [modx_delete_system_setting](API.md#modx_delete_system_setting) | Eine MODX-Systemeinstellung anhand von key löschen. | Weitreichende Auswirkungen |

### Medienquellen und Dateien

| Werkzeug | Lokalisierte Beschreibung | Auswirkung |
| --- | --- | --- |
| [modx_list_media_sources](API.md#modx_list_media_sources) | MODX-Medienquellen auflisten. | Lesen |
| [modx_get_media_source](API.md#modx_get_media_source) | Eine MODX-Medienquelle abrufen. | Lesen |
| [modx_create_media_source](API.md#modx_create_media_source) | Eine Medienquelle (Dateiquelle) erstellen. class_key ist standardmäßig sources.modFileMediaSource. 'properties' ist eine {name:value}-Zuordnung von Quellenparametern (z. B. {"basePath":"assets/files/","baseUrl":"assets/files/"}), die in die Quelle eingearbeitet wird. | Ändert die Website |
| [modx_update_media_source](API.md#modx_update_media_source) | Eine Medienquelle aktualisieren. 'properties' ist eine {name:value}-Zuordnung, die in die Parameter der Quelle eingearbeitet wird (andere bleiben erhalten) — damit basePath/baseUrl/usw. setzen. | Ändert die Website |
| [modx_delete_media_source](API.md#modx_delete_media_source) | Eine Medienquelle anhand von id löschen. | Ändert die Website |
| [modx_list_media_source_files](API.md#modx_list_media_source_files) | Dateien und Ordner innerhalb einer Medienquelle auflisten. | Lesen |
| [modx_read_media_source_file](API.md#modx_read_media_source_file) | Eine Datei innerhalb einer Medienquelle lesen. | Lesen |
| [modx_create_media_file](API.md#modx_create_media_file) | Eine Datei innerhalb einer Medienquelle erstellen (schreibt content). source = ID oder Name der Quelle, path = Zielverzeichnis (relativ zur Basis der Quelle), name = Dateiname, content = Dateiinhalt. | Ändert die Website |
| [modx_update_media_file](API.md#modx_update_media_file) | Den Inhalt einer Datei innerhalb einer Medienquelle überschreiben. source = id/name, path = Dateipfad innerhalb der Quelle, content = neuer Inhalt. | Ändert die Website |
| [modx_delete_media_file](API.md#modx_delete_media_file) | Eine Datei innerhalb einer Medienquelle löschen. source = id/name, path = Dateipfad innerhalb der Quelle. | Weitreichende Auswirkungen |
| [modx_rename_media_file](API.md#modx_rename_media_file) | Eine Datei innerhalb einer Medienquelle umbenennen. source = id/name, path = aktueller Dateipfad, new_name = neuer Dateiname. | Ändert die Website |
| [modx_create_media_folder](API.md#modx_create_media_folder) | Einen Ordner innerhalb einer Medienquelle erstellen. source = id/name, parent = übergeordnetes Verzeichnis (standardmäßig Stammverzeichnis), name = Ordnername. | Ändert die Website |
| [modx_delete_media_folder](API.md#modx_delete_media_folder) | Einen Ordner (und seinen Inhalt) innerhalb einer Medienquelle löschen. source = id/name, path = Ordnerpfad innerhalb der Quelle. | Weitreichende Auswirkungen |

### Komponenten und Dateien

| Werkzeug | Lokalisierte Beschreibung | Auswirkung |
| --- | --- | --- |
| [modx_list_installed_components](API.md#modx_list_installed_components) | Installierte MODX-Komponenten aus core/components und assets/components auflisten. | Lesen |
| [modx_get_component_files](API.md#modx_get_component_files) | Dateien und Ordner innerhalb einer installierten MODX-Komponente auflisten. | Lesen |
| [modx_read_component_file](API.md#modx_read_component_file) | Eine Datei einer installierten MODX-Komponente lesen. | Lesen |
| [modx_check_integrations](API.md#modx_check_integrations) | Melden, welche verbreiteten MODX-Erweiterungen (miniShop2, MIGX, pdoTools, Tickets usw.) auf dieser Website installiert sind und was modxMCP mit jeder davon tun kann. | Lesen |

### Codesuche und Abhängigkeiten

| Werkzeug | Lokalisierte Beschreibung | Auswirkung |
| --- | --- | --- |
| [modx_search_code](API.md#modx_search_code) | Volltextsuche im INHALT (und in Namen) von Elementen und Ressourcen. | Lesen |
| [modx_find_usages](API.md#modx_find_usages) | Verwendungen eines Elements finden: Treffer seines Namens im Inhalt des gesamten Codes sowie — bei einem Template — die diesem Template zugewiesenen Ressourcen. | Lesen |
| [modx_list_resources](API.md#modx_list_resources) | Ressourcen (den Inhaltsbaum) auflisten, optional gefiltert nach parent, context, Löschstatus oder einer pagetitle/alias/uri-Suchanfrage. | Lesen |
| [modx_replace_across](API.md#modx_replace_across) | Websiteweites Suchen und Ersetzen in Code-Elementen (chunk/snippet/template/plugin): alle Elemente finden, deren INHALT find enthält, und ALLE Vorkommen durch replacement ersetzen, in EINEM Aufruf (ohne einzelne Lese-/Schreibaufrufe pro Element Ihrerseits). | Weitreichende Auswirkungen |
| [modx_dependency_graph](API.md#modx_dependency_graph) | In einem Aufruf abbilden, wie die Elemente der Website MITEINANDER VERKNÜPFT SIND: welches Template welchen Chunk/welches Snippet/welche TV einbindet, welches Snippet welchen Chunk rendert und wo jede TV zugeordnet ist. | Lesen |

### VersionX

| Werkzeug | Lokalisierte Beschreibung | Auswirkung |
| --- | --- | --- |
| [modx_versionx_list_versions](API.md#modx_versionx_list_versions) | VersionX-Versionen für eine MODX-Ressource, einen Chunk, ein Snippet, Template, Plugin oder eine TV auflisten. | Lesen |
| [modx_versionx_get_version](API.md#modx_versionx_get_version) | Die Nutzdaten einer VersionX-Version lesen, bevor über eine Rückkehr zu dieser Version entschieden wird. | Ändert die Website |
| [modx_versionx_revert_version](API.md#modx_versionx_revert_version) | Ein MODX-Objekt auf eine bestimmte VersionX-Version zurücksetzen. | Ändert die Website |

### VirtualPage

| Werkzeug | Lokalisierte Beschreibung | Auswirkung |
| --- | --- | --- |
| [modx_virtualpage_list_events](API.md#modx_virtualpage_list_events) | VirtualPage-Ereignisse auflisten, die Routengruppen an MODX-Ereignisse binden. | Ändert die Website |
| [modx_virtualpage_get_event](API.md#modx_virtualpage_get_event) | Ein VirtualPage-Ereignis anhand von id oder name einschließlich seiner Routen lesen. | Ändert die Website |
| [modx_virtualpage_create_event](API.md#modx_virtualpage_create_event) | Ein VirtualPage-Ereignis erstellen, üblicherweise OnPageNotFound oder OnHandleRequest. | Ändert die Website |
| [modx_virtualpage_update_event](API.md#modx_virtualpage_update_event) | Ein VirtualPage-Ereignis anhand von id oder name aktualisieren. | Ändert die Website |
| [modx_virtualpage_list_handlers](API.md#modx_virtualpage_list_handlers) | VirtualPage-Handler auflisten, die Ressourcen, Snippets, Chunks oder dynamische Ressourcen rendern. | Ändert die Website |
| [modx_virtualpage_get_handler](API.md#modx_virtualpage_get_handler) | Einen VirtualPage-Handler anhand von id oder name einschließlich der ihn verwendenden Routen lesen. | Ändert die Website |
| [modx_virtualpage_create_handler](API.md#modx_virtualpage_create_handler) | Einen VirtualPage-Handler erstellen. type kann resource, snippet, chunk, dynamic_resource/template oder 0..3 sein. | Ändert die Website |
| [modx_virtualpage_update_handler](API.md#modx_virtualpage_update_handler) | Einen VirtualPage-Handler anhand von id oder name aktualisieren. | Ändert die Website |
| [modx_virtualpage_list_routes](API.md#modx_virtualpage_list_routes) | VirtualPage-Routen mit den Namen ihrer Ereignisse und Handler auflisten. | Ändert die Website |
| [modx_virtualpage_get_route](API.md#modx_virtualpage_get_route) | Eine VirtualPage-Route anhand von id oder anhand von route plus optional method lesen. | Ändert die Website |
| [modx_virtualpage_create_route](API.md#modx_virtualpage_create_route) | Eine VirtualPage-Route erstellen und bei Bedarf ihr Ereignis-Plugin binden. | Ändert die Website |
| [modx_virtualpage_update_route](API.md#modx_virtualpage_update_route) | Eine VirtualPage-Route anhand von id aktualisieren. | Ändert die Website |
| [modx_virtualpage_resolve_route](API.md#modx_virtualpage_resolve_route) | Die Routenzuordnung von VirtualPage simulieren und den Handler plus vp.*-Platzhalter zurückgeben. | Lesen |
| [modx_virtualpage_delete_event](API.md#modx_virtualpage_delete_event) | Ein VirtualPage-Ereignis anhand von id oder name löschen (entfernt gemäß VP-Schema auch seine Routen). | Ändert die Website |
| [modx_virtualpage_delete_handler](API.md#modx_virtualpage_delete_handler) | Einen VirtualPage-Handler anhand von id oder name löschen. | Ändert die Website |
| [modx_virtualpage_delete_route](API.md#modx_virtualpage_delete_route) | Eine VirtualPage-Route anhand von id oder name löschen. | Ändert die Website |
| [modx_virtualpage_clear_cache](API.md#modx_virtualpage_clear_cache) | VirtualPage-Routen-/Cache-Dateien löschen und den MODX-Cache aktualisieren. | Ändert die Website |

### miniShop2

| Werkzeug | Lokalisierte Beschreibung | Auswirkung |
| --- | --- | --- |
| [modx_ms2_list_option_types](API.md#modx_ms2_list_option_types) | Verfügbare Feldtypen für miniShop2-Produktoptionen auflisten. | Ändert die Website |
| [modx_ms2_list_options](API.md#modx_ms2_list_options) | miniShop2-Produktoptionen aus dem Optionen-Reiter der Einstellungen auflisten. | Ändert die Website |
| [modx_ms2_get_option](API.md#modx_ms2_get_option) | Eine miniShop2-Produktoption anhand von id lesen. | Ändert die Website |
| [modx_ms2_create_option](API.md#modx_ms2_create_option) | Eine miniShop2-Produktoption in den Einstellungsoptionen erstellen. | Ändert die Website |
| [modx_ms2_update_option](API.md#modx_ms2_update_option) | Eine miniShop2-Produktoption in den Einstellungsoptionen aktualisieren. | Ändert die Website |
| [modx_ms2_assign_option_to_category](API.md#modx_ms2_assign_option_to_category) | Eine vorhandene miniShop2-Option einer msCategory zuweisen. | Ändert die Website |
| [modx_ms2_get_product_options](API.md#modx_ms2_get_product_options) | Die einem Produkt zugewiesenen miniShop2-Optionswerte lesen. | Ändert die Website |
| [modx_ms2_update_product_options](API.md#modx_ms2_update_product_options) | miniShop2-Optionswerte für ein Produkt erstellen, aktualisieren oder entfernen. | Ändert die Website |
| [modx_ms2_list_link_types](API.md#modx_ms2_list_link_types) | miniShop2-Verknüpfungstypen (msLink) auflisten: id, type, name, description. | Ändert die Website |
| [modx_ms2_get_link_type](API.md#modx_ms2_get_link_type) | Einen miniShop2-Verknüpfungstyp anhand von id abrufen. | Ändert die Website |
| [modx_ms2_create_link_type](API.md#modx_ms2_create_link_type) | Einen miniShop2-Verknüpfungstyp erstellen. 'type' ist die Beziehungsart, die bestimmt, wie Produkte verknüpft werden. | Ändert die Website |
| [modx_ms2_update_link_type](API.md#modx_ms2_update_link_type) | Einen miniShop2-Verknüpfungstyp aktualisieren. | Ändert die Website |
| [modx_ms2_delete_link_type](API.md#modx_ms2_delete_link_type) | Einen miniShop2-Verknüpfungstyp anhand von id löschen. | Ändert die Website |
| [modx_ms2_list_product_links](API.md#modx_ms2_list_product_links) | Produkt-zu-Produkt-Verknüpfungen (msProductLink) auflisten, optional für ein einzelnes Produkt. | Ändert die Website |
| [modx_ms2_create_product_link](API.md#modx_ms2_create_product_link) | Zwei Produkte unter einem Verknüpfungstyp verknüpfen. Die Beziehungsart des Verknüpfungstyps bestimmt, ob auch die umgekehrte Verknüpfung erstellt wird. | Ändert die Website |
| [modx_ms2_delete_product_link](API.md#modx_ms2_delete_product_link) | Eine Produktverknüpfung entfernen (anhand von Verknüpfungstyp + master + slave). | Ändert die Website |
| [modx_ms2_list_categories](API.md#modx_ms2_list_categories) | miniShop2-Kategorien (msCategory) auflisten. | Ändert die Website |
| [modx_ms2_create_category](API.md#modx_ms2_create_category) | Eine miniShop2-Kategorie (msCategory-Ressource) erstellen. | Ändert die Website |
| [modx_ms2_update_category](API.md#modx_ms2_update_category) | Eine miniShop2-Kategorie aktualisieren. | Ändert die Website |
| [modx_ms2_list_orders](API.md#modx_ms2_list_orders) | miniShop2-Bestellungen (msOrder) auflisten, filterbar nach Status/Kunde/Kontext/Datumsbereich. | Ändert die Website |
| [modx_ms2_get_order](API.md#modx_ms2_get_order) | Eine miniShop2-Bestellung anhand von id abrufen (mit Positionen/Kunde). | Ändert die Website |
| [modx_ms2_update_order](API.md#modx_ms2_update_order) | Eine miniShop2-Bestellung aktualisieren — üblicherweise zum Ändern ihres Status (löst die ms2-Statusänderungslogik aus), der Lieferung oder der Zahlung. | Ändert die Website |

### MIGX

| Werkzeug | Lokalisierte Beschreibung | Auswirkung |
| --- | --- | --- |
| [modx_migx_list_configs](API.md#modx_migx_list_configs) | MIGX-Konfigurationen (migxConfig) auflisten: id, name, category, published. | Ändert die Website |
| [modx_migx_get_config](API.md#modx_migx_get_config) | Eine vollständige MIGX-Konfiguration anhand von id abrufen (formtabs, columns, buttons, filters, permissions — als gespeicherte JSON-Zeichenfolgen). | Ändert die Website |
| [modx_migx_create_config](API.md#modx_migx_create_config) | Eine MIGX-Konfiguration erstellen. formtabs/columns/contextmenus/actionbuttons/columnbuttons/filters sind JSON-Zeichenfolgen (im selben Format, das MIGX im Manager speichert). | Ändert die Website |
| [modx_migx_update_config](API.md#modx_migx_update_config) | Eine MIGX-Konfiguration aktualisieren. Nur die übergebenen Felder werden geändert. | Ändert die Website |
| [modx_migx_delete_config](API.md#modx_migx_delete_config) | Eine MIGX-Konfiguration anhand von id löschen. | Ändert die Website |

### Benutzer und Zugriffsrechte

| Werkzeug | Lokalisierte Beschreibung | Auswirkung |
| --- | --- | --- |
| [modx_flush_permissions](API.md#modx_flush_permissions) | Zwischengespeicherte Zugriffsberechtigungen leeren ('Flush Permissions' im Manager), damit ACL-Änderungen wirksam werden. | Ändert die Website |
| [modx_list_users](API.md#modx_list_users) | Manager-/Webbenutzer (modUser) auflisten. | Lesen |
| [modx_get_user](API.md#modx_get_user) | Einen Benutzer anhand von id abrufen. | Lesen |
| [modx_create_user](API.md#modx_create_user) | Einen Benutzer erstellen. 'password' übergeben, um das Passwort ausdrücklich festzulegen; zur automatischen Generierung weglassen. Profilfelder (email, fullname, phone…) werden ebenfalls akzeptiert. | Ändert die Website |
| [modx_update_user](API.md#modx_update_user) | Einen Benutzer aktualisieren (Profilfelder, Aktivierungsstatus). | Ändert die Website |
| [modx_delete_user](API.md#modx_delete_user) | Einen Benutzer anhand von id löschen. | Ändert die Website |
| [modx_list_user_groups](API.md#modx_list_user_groups) | MODX-Benutzergruppen auflisten (id, name, parent, rank). | Lesen |
| [modx_get_user_group](API.md#modx_get_user_group) | Eine einzelne Benutzergruppe anhand von id abrufen. | Lesen |
| [modx_create_user_group](API.md#modx_create_user_group) | Eine Benutzergruppe erstellen. | Ändert die Website |
| [modx_update_user_group](API.md#modx_update_user_group) | Eine Benutzergruppe aktualisieren. | Ändert die Website |
| [modx_delete_user_group](API.md#modx_delete_user_group) | Eine Benutzergruppe anhand von id löschen. | Ändert die Website |
| [modx_list_user_group_members](API.md#modx_list_user_group_members) | Mitglieder einer Benutzergruppe auflisten. | Lesen |
| [modx_add_user_to_group](API.md#modx_add_user_to_group) | Einen Benutzer einer Benutzergruppe hinzufügen, optional mit einer Rolle. | Ändert die Website |
| [modx_update_group_member](API.md#modx_update_group_member) | Die Gruppenmitgliedschaft eines Benutzers aktualisieren (z. B. die Rolle ändern). | Ändert die Website |
| [modx_remove_user_from_group](API.md#modx_remove_user_from_group) | Einen Benutzer aus einer Benutzergruppe entfernen. | Ändert die Website |
| [modx_list_roles](API.md#modx_list_roles) | Zugriffsrollen auflisten (id, name, authority). | Lesen |
| [modx_get_role](API.md#modx_get_role) | Eine Rolle anhand von id abrufen. | Lesen |
| [modx_create_role](API.md#modx_create_role) | Eine Rolle erstellen. | Ändert die Website |
| [modx_update_role](API.md#modx_update_role) | Eine Rolle aktualisieren. | Ändert die Website |
| [modx_delete_role](API.md#modx_delete_role) | Eine Rolle anhand von id löschen. | Ändert die Website |
| [modx_list_access_policies](API.md#modx_list_access_policies) | Zugriffsrichtlinien auflisten (id, name, description, template). | Lesen |
| [modx_get_access_policy](API.md#modx_get_access_policy) | Eine Zugriffsrichtlinie anhand von id abrufen. | Lesen |
| [modx_create_access_policy](API.md#modx_create_access_policy) | Eine Zugriffsrichtlinie erstellen. | Ändert die Website |
| [modx_update_access_policy](API.md#modx_update_access_policy) | Eine Zugriffsrichtlinie aktualisieren (z. B. ihre Berechtigungszuordnung). | Weitreichende Auswirkungen |
| [modx_delete_access_policy](API.md#modx_delete_access_policy) | Eine Zugriffsrichtlinie anhand von id löschen. | Ändert die Website |
| [modx_list_access_policy_templates](API.md#modx_list_access_policy_templates) | Vorlagen für Zugriffsrichtlinien auflisten. | Lesen |
| [modx_get_access_policy_template](API.md#modx_get_access_policy_template) | Eine Vorlage für Zugriffsrichtlinien anhand von id abrufen. | Lesen |
| [modx_create_access_policy_template](API.md#modx_create_access_policy_template) | Eine Vorlage für Zugriffsrichtlinien erstellen. | Ändert die Website |
| [modx_update_access_policy_template](API.md#modx_update_access_policy_template) | Eine Vorlage für Zugriffsrichtlinien aktualisieren. | Ändert die Website |
| [modx_delete_access_policy_template](API.md#modx_delete_access_policy_template) | Eine Vorlage für Zugriffsrichtlinien anhand von id löschen. | Ändert die Website |
| [modx_list_access_permissions](API.md#modx_list_access_permissions) | Die von einer Richtlinienvorlage definierten Berechtigungen auflisten (rein lesender Katalog). | Lesen |
| [modx_list_resource_groups](API.md#modx_list_resource_groups) | Ressourcengruppen auflisten (id, name). | Lesen |
| [modx_get_resource_group](API.md#modx_get_resource_group) | Eine Ressourcengruppe anhand von id abrufen. | Lesen |
| [modx_list_resource_group_resources](API.md#modx_list_resource_group_resources) | Die einer Ressourcengruppe zugewiesenen Ressourcen auflisten. | Lesen |
| [modx_create_resource_group](API.md#modx_create_resource_group) | Eine Ressourcengruppe erstellen. | Ändert die Website |
| [modx_update_resource_group](API.md#modx_update_resource_group) | Eine Ressourcengruppe aktualisieren. | Ändert die Website |
| [modx_delete_resource_group](API.md#modx_delete_resource_group) | Eine Ressourcengruppe anhand von id löschen. | Ändert die Website |
| [modx_assign_resource_to_group](API.md#modx_assign_resource_to_group) | Eine Ressource (Dokument) einer Ressourcengruppe hinzufügen. | Ändert die Website |
| [modx_remove_resource_from_group](API.md#modx_remove_resource_from_group) | Eine Ressource (Dokument) aus einer Ressourcengruppe entfernen. | Ändert die Website |
| [modx_list_context_access](API.md#modx_list_context_access) | ACL-Einträge für Kontextzugriff auflisten, optional gefiltert nach usergroup/context/policy. | Lesen |
| [modx_grant_context_access](API.md#modx_grant_context_access) | Einer Benutzergruppe Zugriff auf einen Kontext mit einer Richtlinie und einem Mindestwert für die Rollenautorität gewähren. | Ändert die Website |
| [modx_update_context_access](API.md#modx_update_context_access) | Einen ACL-Eintrag für Kontextzugriff anhand von id aktualisieren. | Ändert die Website |
| [modx_revoke_context_access](API.md#modx_revoke_context_access) | Einen ACL-Eintrag für Kontextzugriff anhand von id entfernen. | Ändert die Website |
| [modx_list_resourcegroup_access](API.md#modx_list_resourcegroup_access) | ACL-Einträge für Ressourcengruppenzugriff auflisten. | Lesen |
| [modx_grant_resourcegroup_access](API.md#modx_grant_resourcegroup_access) | Einer Benutzergruppe Zugriff auf eine Ressourcengruppe mit einer Richtlinie und einem Mindestwert für die Rollenautorität gewähren. | Ändert die Website |
| [modx_update_resourcegroup_access](API.md#modx_update_resourcegroup_access) | Einen ACL-Eintrag für Ressourcengruppenzugriff anhand von id aktualisieren. | Ändert die Website |
| [modx_revoke_resourcegroup_access](API.md#modx_revoke_resourcegroup_access) | Einen ACL-Eintrag für Ressourcengruppenzugriff anhand von id entfernen. | Ändert die Website |

### ClientConfig

| Werkzeug | Lokalisierte Beschreibung | Auswirkung |
| --- | --- | --- |
| [modx_clientconfig_list_settings](API.md#modx_clientconfig_list_settings) | ClientConfig-Einstellungen einschließlich kontextspezifischer Werte auflisten. | Ändert die Website |
| [modx_clientconfig_get_setting](API.md#modx_clientconfig_get_setting) | Eine ClientConfig-Einstellung anhand von id oder key abrufen. | Ändert die Website |
| [modx_clientconfig_create_setting](API.md#modx_clientconfig_create_setting) | Eine ClientConfig-Einstellung erstellen. | Ändert die Website |
| [modx_clientconfig_update_setting](API.md#modx_clientconfig_update_setting) | Eine ClientConfig-Einstellung anhand von id oder key aktualisieren. | Ändert die Website |
| [modx_clientconfig_delete_setting](API.md#modx_clientconfig_delete_setting) | Eine ClientConfig-Einstellung anhand von id oder key löschen. | Ändert die Website |

### Eigenschaftssätze

| Werkzeug | Lokalisierte Beschreibung | Auswirkung |
| --- | --- | --- |
| [modx_list_property_sets](API.md#modx_list_property_sets) | Eigenschaftssätze (modPropertySet) auflisten: id, name, description, category. | Lesen |
| [modx_get_property_set](API.md#modx_get_property_set) | Einen Eigenschaftssatz anhand von id abrufen (einschließlich seiner Eigenschaften). | Lesen |
| [modx_create_property_set](API.md#modx_create_property_set) | Einen Eigenschaftssatz erstellen. 'properties' ist ein JSON-Objekt mit Eigenschaftsdefinitionen. | Ändert die Website |
| [modx_update_property_set](API.md#modx_update_property_set) | Einen Eigenschaftssatz aktualisieren. | Ändert die Website |
| [modx_delete_property_set](API.md#modx_delete_property_set) | Einen Eigenschaftssatz anhand von id löschen. | Ändert die Website |
| [modx_assign_property_set](API.md#modx_assign_property_set) | Einen Eigenschaftssatz einem Element zuordnen. Das Element durch seine id + entweder element_class (z. B. 'modSnippet') oder element_type ('snippet'/'chunk'/'template'/'plugin'/'tv') identifizieren. | Ändert die Website |
| [modx_unassign_property_set](API.md#modx_unassign_property_set) | Die Zuordnung eines Eigenschaftssatzes zu einem Element entfernen (gleiche Identifikation wie beim Zuordnen). | Ändert die Website |

### Kontexte und Kontexteinstellungen

| Werkzeug | Lokalisierte Beschreibung | Auswirkung |
| --- | --- | --- |
| [modx_list_contexts](API.md#modx_list_contexts) | Kontexte (modContext) auflisten: key, name, description, rank. | Lesen |
| [modx_get_context](API.md#modx_get_context) | Einen Kontext anhand von key abrufen. | Lesen |
| [modx_create_context](API.md#modx_create_context) | Einen Kontext erstellen. 'key' ist der eindeutige Bezeichner (z. B. 'mobile'). | Ändert die Website |
| [modx_update_context](API.md#modx_update_context) | Einen Kontext aktualisieren (name/description/rank). 'settings' kann ein JSON-Array von Kontexteinstellungen sein (fortgeschrittene Verwendung). | Ändert die Website |
| [modx_delete_context](API.md#modx_delete_context) | Einen Kontext anhand von key löschen. | Weitreichende Auswirkungen |
| [modx_list_context_settings](API.md#modx_list_context_settings) | Einstellungen eines Kontexts (modContextSetting) auflisten. | Lesen |
| [modx_get_context_setting](API.md#modx_get_context_setting) | Eine Kontexteinstellung abrufen (anhand von context_key + key). | Lesen |
| [modx_create_context_setting](API.md#modx_create_context_setting) | Eine Einstellung auf Kontextebene erstellen (überschreibt die Systemeinstellung für diesen Kontext). | Ändert die Website |
| [modx_update_context_setting](API.md#modx_update_context_setting) | Eine Kontexteinstellung aktualisieren (anhand von context_key + key). | Ändert die Website |
| [modx_delete_context_setting](API.md#modx_delete_context_setting) | Eine Kontexteinstellung löschen (anhand von context_key + key). | Ändert die Website |

### Pakete und Anbieter

| Werkzeug | Lokalisierte Beschreibung | Auswirkung |
| --- | --- | --- |
| [modx_install_package](API.md#modx_install_package) | Ein Transportpaket von einem Anbieter (standardmäßig modx.com) anhand des Namens installieren, z. B. {package:'MIGX'}. | Weitreichende Auswirkungen |
| [modx_uninstall_package](API.md#modx_uninstall_package) | Ein Transportpaket anhand der Signatur deinstallieren (z. B. 'migx-2.13.0-pl'). | Weitreichende Auswirkungen |
| [modx_list_providers](API.md#modx_list_providers) | Transportanbieter (modx.com und eigene, z. B. modstore.pro) auflisten: id, name, service_url. | Lesen |
| [modx_search_packages](API.md#modx_search_packages) | Den Katalog eines Anbieters durchsuchen (vor install_package verwenden). | Lesen |
| [modx_create_provider](API.md#modx_create_provider) | Einen Transportanbieter hinzufügen (z. B. modstore.pro). | Ändert die Website |
| [modx_update_provider](API.md#modx_update_provider) | Einen Transportanbieter aktualisieren. | Ändert die Website |
| [modx_delete_provider](API.md#modx_delete_provider) | Einen Transportanbieter anhand von id löschen. | Ändert die Website |

### Namespaces

| Werkzeug | Lokalisierte Beschreibung | Auswirkung |
| --- | --- | --- |
| [modx_list_namespaces](API.md#modx_list_namespaces) | Namensräume (modNamespace) auflisten: name, path, assets_path. | Lesen |
| [modx_create_namespace](API.md#modx_create_namespace) | Einen Namensraum erstellen. | Ändert die Website |
| [modx_update_namespace](API.md#modx_update_namespace) | Einen Namensraum aktualisieren (anhand von name). | Ändert die Website |
| [modx_delete_namespace](API.md#modx_delete_namespace) | Einen Namensraum anhand von name löschen ('core' kann nicht gelöscht werden). | Ändert die Website |

### Lexikon und Übersetzungen

| Werkzeug | Lokalisierte Beschreibung | Auswirkung |
| --- | --- | --- |
| [modx_list_lexicon_entries](API.md#modx_list_lexicon_entries) | Lexikoneinträge auflisten, gefiltert nach namespace + topic + language (und optionaler Suche). | Lesen |
| [modx_list_lexicon_topics](API.md#modx_list_lexicon_topics) | Lexikonthemen für namespace/language auflisten. | Lesen |
| [modx_set_lexicon_entry](API.md#modx_set_lexicon_entry) | Einen Lexikoneintrag erstellen oder überschreiben (Datenbanküberschreibung des Dateiwerts). | Ändert die Website |
| [modx_revert_lexicon_entry](API.md#modx_revert_lexicon_entry) | Einen Lexikoneintrag zurücksetzen — seine Datenbanküberschreibung entfernen, sodass der Dateiwert gilt. | Ändert die Website |

### Wartung und Diagnose

| Werkzeug | Lokalisierte Beschreibung | Auswirkung |
| --- | --- | --- |
| [modx_get_site_state](API.md#modx_get_site_state) | Die vom Connector maßgeblich verwaltete Website-Revision und die von externen Werkzeugen verwendete Unterstützung atomarer Vorbedingungen lesen. | Lesen |
| [modx_describe_object](API.md#modx_describe_object) | Schema-Introspektion: Felder einer xPDO-Klasse (name + php/db-Typ, null, default) und ihren Primärschlüssel auflisten, damit Sie TATSÄCHLICHE Feldnamen verwenden, statt sie zu erraten. | Lesen |
| [modx_read_error_log](API.md#modx_read_error_log) | Das Ende des MODX-Fehlerprotokolls (core/cache/logs/error.log) zur Diagnose lesen. | Lesen |
| [modx_refresh_uris](API.md#modx_refresh_uris) | Alle Ressourcen-URIs neu generieren (nach Massenänderungen an Aliasen/Struktur ausführen, die veraltete URIs hinterlassen haben). | Ändert die Website |
| [modx_remove_locks](API.md#modx_remove_locks) | Veraltete Bearbeitungssperren im Manager aufheben (wenn ein Element/eine Ressource als durch eine nicht mehr aktive Sitzung gesperrt gemeldet wird). | Ändert die Website |
| [modx_project_overview](API.md#modx_project_overview) | Mit EINEM kompakten, kostengünstigen Aufruf einen Überblick über die gesamte installierte Website gewinnen. | Lesen |
| [modx_system_info](API.md#modx_system_info) | Umgebungs-/Diagnoseinformationen: MODX-Version, modxMCP-Version, PHP-Version, Datenbanktyp, wichtige Pfade. | Lesen |
| [modx_regenerate_token](API.md#modx_regenerate_token) | modxmcp.api_token auf einen neuen Zufallswert rotieren und diesen zurückgeben. | Weitreichende Auswirkungen |
| [modx_help](API.md#modx_help) | Integrierte Dokumentation. Ohne Argumente aufrufen, um Themen aufzulisten; {topic} für eine Anleitung übergeben (z. B. 'tv_input_types', 'study_component', 'minishop2', 'migx', 'acl', 'getting_started'). Lesen Sie das relevante Thema vor unvertrauten Arbeiten oder um die Verwendung einer neu installierten Komponente zu lernen. | Lesen |
| [modx_list_actions](API.md#modx_list_actions) | Die von diesem modxMCP-Server-Build unterstützten Aktionsnamen nach Bereich gruppiert auflisten. | Lesen |
| [modx_clear_cache](API.md#modx_clear_cache) | Den MODX-Cache aktualisieren. partitions übergeben (z. B. ['resource','context_settings']), um nur diese Partitionen zu aktualisieren; für eine vollständige Aktualisierung weglassen. | Ändert die Website |
| [modx_read_audit_log](API.md#modx_read_audit_log) | Das Schreib-Audit-Protokoll von modxMCP lesen (neueste Einträge zuletzt). | Lesen |
| [modx_run_processor](API.md#modx_run_processor) | JEDEN BELIEBIGEN MODX-Prozessor direkt ausführen (direkter Ausweichzugang). | Weitreichende Auswirkungen |
