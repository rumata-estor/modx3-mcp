# API-referentie — MODX MCP

[English](API.md) · [Русский](API.ru.md) · [Español](API.es.md) · [Deutsch](API.de.md) · [Français](API.fr.md) · [Українська](API.uk.md) · [Қазақша](API.kk.md) · [Nederlands](API.nl.md) · [Polski](API.pl.md)

> Opmerking: dit is een vertaald overzicht. De volledige parametertabellen van alle 192 MCP-tools zijn beschikbaar in het Engels en Russisch.
Openbare MCP-tools gebruiken het voorvoegsel `modx_`; PHP-HTTP-acties gebruiken de naam zonder dit voorvoegsel. De volledige parametertabellen en JSON-schema’s staan in de Engelstalige referentie.

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

Stuur JSON via `POST` met de header `X-MCP-Token` over HTTPS. `api.php` is geen MCP-JSON-RPC-endpoint: stuur geen `tools/call`-berichten rechtstreeks.

**Volledige technische naslagwerken:** [API-parameters (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/API.md) · [beveiliging (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/SECURITY.md) · [ontwikkeling (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/DEVELOPMENT.md). De gedetailleerde technische tabellen zijn voorlopig in het Engels.


## Overzicht van 192 MCP-tools

> De beschrijvingen zijn vertaald; controleer verplichte velden, typen, limieten en enumeraties in de gekoppelde Engelse of Russische technische referentie.

[Volledige functies (Nederlands)](CAPABILITIES.nl.md) · [English API schemas](API.md) · [Русский API](API.ru.md)

### Elementen en resources

| Tool | Vertaald doel | Impact |
| --- | --- | --- |
| [modx_list_elements](API.md#modx_list_elements) | Geef MODX-elementen van een bepaald type weer (id + name). | Lezen |
| [modx_make_static](API.md#modx_make_static) | Zet een chunk/snippet/template/plugin om naar een statisch bestand onder core/elements/ (schrijft het bestand, stelt static=1 + source=Filesystem in). | Grote impact |
| [modx_get_element](API.md#modx_get_element) | Lees een MODX-element (retourneert de velden + volledige code). | Lezen |
| [modx_update_element_property](API.md#modx_update_element_property) | Wijzig veilig één bestaande standaardeigenschap met een CAS-controle op de verwachte waarde; vervang nooit code of andere eigenschappen. | Wijzigt de site |
| [modx_update_element](API.md#modx_update_element) | Werk een MODX-element bij door de volledige inhoud te VERVANGEN (verstuur de volledige nieuwe inhoud). | Grote impact |
| [modx_create_element](API.md#modx_create_element) | Maak een nieuw MODX-element. | Grote impact |
| [modx_delete_element](API.md#modx_delete_element) | Verwijder een MODX-element. Voer dit EERST uit met dry_run:true — dit retourneert wat zou worden verwijderd en waar nog naar het element wordt verwezen (voor resources: het aantal onderliggende resources), zonder iets te verwijderen. Beoordeel dit vóór de daadwerkelijke verwijdering. | Grote impact |
| [modx_view_element](API.md#modx_view_element) | Bekijk een chunk/snippet/template/plugin als GENUMMERDE regels (zoals cat -n), eventueel beperkt met start_line/end_line. | Wijzigt de site |
| [modx_edit_element_lines](API.md#modx_edit_element_lines) | Bewerk een chunk/snippet/template/plugin PER REGEL door alleen de gewijzigde regels te verzenden (het hele element hoeft niet opnieuw te worden verzonden). | Grote impact |
| [modx_bulk_resources](API.md#modx_bulk_resources) | Pas ÉÉN bewerking tegelijk toe op meerdere resources: publish, unpublish, set_template (vereist template), move (vereist parent_to en/of context_to), of delete. | Grote impact |
| [modx_duplicate_resource](API.md#modx_duplicate_resource) | Dupliceer een resource. id = bronresource; optioneel name (nieuwe pagetitle), duplicate_children (bool), published_mode (preserve\|publish\|unpublish). | Wijzigt de site |
| [modx_duplicate_element](API.md#modx_duplicate_element) | Dupliceer een element (chunk/snippet/template/plugin/tv). type + id; optioneel name voor de kopie. | Wijzigt de site |
| [modx_undelete_resource](API.md#modx_undelete_resource) | Herstel een voorlopig verwijderde resource uit de prullenbak (maakt de verwijdering ongedaan). id = resource-id. | Wijzigt de site |
| [modx_empty_recycle_bin](API.md#modx_empty_recycle_bin) | Verwijder ALLE resources in de prullenbak (voorlopig verwijderde resources) definitief. | Grote impact |
| [modx_reorder_resources](API.md#modx_reorder_resources) | Wijzig de volgorde van resources in de boomstructuur door menuindex (en eventueel parent) in te stellen. items = [{id, menuindex, parent?}], toegepast per resource. | Wijzigt de site |

### Resource-TV’s

| Tool | Vertaald doel | Impact |
| --- | --- | --- |
| [modx_get_resource_tvs](API.md#modx_get_resource_tvs) | Haal de waarden van templatevariabelen (TV’s) van een resource op. | Lezen |
| [modx_update_resource_tvs](API.md#modx_update_resource_tvs) | Werk de waarden van templatevariabelen (TV’s) van een resource bij. | Wijzigt de site |
| [modx_list_tv_values](API.md#modx_list_tv_values) | Geef de expliciet opgeslagen waarden van één TV voor alle resources weer, inclusief verouderde waarden op resources waarvan het huidige template die TV mogelijk niet meer gebruikt. | Lezen |
| [modx_clear_tv_values](API.md#modx_clear_tv_values) | Bekijk vooraf of wis alle expliciet opgeslagen waarden van één TV. | Wijzigt de site |

### TV-veldtypen

| Tool | Vertaald doel | Impact |
| --- | --- | --- |
| [modx_suggest_tv_type](API.md#modx_suggest_tv_type) | Weet u niet welk TV-invoertype bij een behoefte past? Beschrijf deze (in het Engels of Russisch) en ontvang gerangschikte kandidaat-field_types met redenen + een bewerkbaar create_element-skelet voor de beste keuze (met de extra sleutels die dat type nodig heeft). | Lezen |
| [modx_list_tv_input_types](API.md#modx_list_tv_input_types) | Geef de op deze site beschikbare TV-invoertypen (widgets) weer. | Lezen |

### Systeeminstellingen

| Tool | Vertaald doel | Impact |
| --- | --- | --- |
| [modx_list_system_settings](API.md#modx_list_system_settings) | Geef MODX-systeeminstellingen weer. | Lezen |
| [modx_get_system_setting](API.md#modx_get_system_setting) | Haal één MODX-systeeminstelling op via de sleutel. | Lezen |
| [modx_create_system_setting](API.md#modx_create_system_setting) | Maak een MODX-systeeminstelling. | Wijzigt de site |
| [modx_update_system_setting](API.md#modx_update_system_setting) | Werk een MODX-systeeminstelling bij via de sleutel. | Grote impact |
| [modx_delete_system_setting](API.md#modx_delete_system_setting) | Verwijder een MODX-systeeminstelling via de sleutel. | Grote impact |

### Mediabronnen en bestanden

| Tool | Vertaald doel | Impact |
| --- | --- | --- |
| [modx_list_media_sources](API.md#modx_list_media_sources) | Geef MODX-mediabronnen weer. | Lezen |
| [modx_get_media_source](API.md#modx_get_media_source) | Haal één MODX-mediabron op. | Lezen |
| [modx_create_media_source](API.md#modx_create_media_source) | Maak een media- (bestands)bron. class_key is standaard sources.modFileMediaSource. 'properties' is een {name:value}-map met bronparameters (bijv. {\"basePath\":\"assets/files/\",\"baseUrl\":\"assets/files/\"}) die met de bron worden samengevoegd. | Wijzigt de site |
| [modx_update_media_source](API.md#modx_update_media_source) | Werk een mediabron bij. 'properties' is een {name:value}-map die wordt samengevoegd met de bronparameters (de overige blijven behouden) — gebruik deze om basePath/baseUrl/etc. in te stellen. | Wijzigt de site |
| [modx_delete_media_source](API.md#modx_delete_media_source) | Verwijder een mediabron via id. | Wijzigt de site |
| [modx_list_media_source_files](API.md#modx_list_media_source_files) | Geef bestanden en mappen binnen een mediabron weer. | Lezen |
| [modx_read_media_source_file](API.md#modx_read_media_source_file) | Lees een bestand binnen een mediabron. | Lezen |
| [modx_create_media_file](API.md#modx_create_media_file) | Maak een bestand binnen een mediabron (schrijft inhoud). source = bron-id of -naam, path = doelmap (relatief ten opzichte van de basismap van de bron), name = bestandsnaam, content = bestandsinhoud. | Wijzigt de site |
| [modx_update_media_file](API.md#modx_update_media_file) | Overschrijf de inhoud van een bestand binnen een mediabron. source = id/naam, path = bestandspad binnen de bron, content = nieuwe inhoud. | Wijzigt de site |
| [modx_delete_media_file](API.md#modx_delete_media_file) | Verwijder een bestand binnen een mediabron. source = id/naam, path = bestandspad binnen de bron. | Grote impact |
| [modx_rename_media_file](API.md#modx_rename_media_file) | Hernoem een bestand binnen een mediabron. source = id/naam, path = huidig bestandspad, new_name = nieuwe bestandsnaam. | Wijzigt de site |
| [modx_create_media_folder](API.md#modx_create_media_folder) | Maak een map binnen een mediabron. source = id/naam, parent = bovenliggende map (standaard de hoofdmap), name = mapnaam. | Wijzigt de site |
| [modx_delete_media_folder](API.md#modx_delete_media_folder) | Verwijder een map (en de inhoud ervan) binnen een mediabron. source = id/naam, path = mappad binnen de bron. | Grote impact |

### Componenten en bestanden

| Tool | Vertaald doel | Impact |
| --- | --- | --- |
| [modx_list_installed_components](API.md#modx_list_installed_components) | Geef geïnstalleerde MODX-componenten uit core/components en assets/components weer. | Lezen |
| [modx_get_component_files](API.md#modx_get_component_files) | Geef bestanden en mappen binnen een geïnstalleerde MODX-component weer. | Lezen |
| [modx_read_component_file](API.md#modx_read_component_file) | Lees een bestand uit een geïnstalleerde MODX-component. | Lezen |
| [modx_check_integrations](API.md#modx_check_integrations) | Meld welke populaire MODX-uitbreidingen (miniShop2, MIGX, pdoTools, Tickets, enz.) op deze site zijn geïnstalleerd en wat modxMCP met elk ervan kan doen. | Lezen |

### Code doorzoeken en afhankelijkheden

| Tool | Vertaald doel | Impact |
| --- | --- | --- |
| [modx_search_code](API.md#modx_search_code) | Doorzoek de volledige tekst van de INHOUD (en namen) van elementen en resources. | Lezen |
| [modx_find_usages](API.md#modx_find_usages) | Zoek waar een element wordt gebruikt: overeenkomsten met de naam in de inhoud van alle code, plus — bij een template — de resources die aan dat template zijn toegewezen. | Lezen |
| [modx_list_resources](API.md#modx_list_resources) | Geef resources (de inhoudsboom) weer, eventueel gefilterd op parent, context, verwijderingsstatus of een zoekopdracht op pagetitle/alias/uri. | Lezen |
| [modx_replace_across](API.md#modx_replace_across) | Zoek en vervang sitebreed in code-elementen (chunk/snippet/template/plugin): vind elk element waarvan de INHOUD find bevat en vervang ALLE voorkomens door replacement, in ÉÉN aanroep (zonder dat u elk element afzonderlijk hoeft te lezen/schrijven). | Grote impact |
| [modx_dependency_graph](API.md#modx_dependency_graph) | Breng in één aanroep de ONDERLINGE VERBINDINGEN tussen site-elementen in kaart: welk template welke chunk/snippet/TV gebruikt, welke snippet welke chunk rendert en waar elke TV is gekoppeld. | Lezen |

### VersionX

| Tool | Vertaald doel | Impact |
| --- | --- | --- |
| [modx_versionx_list_versions](API.md#modx_versionx_list_versions) | Geef VersionX-versies van een MODX-resource, chunk, snippet, template, plugin of TV weer. | Lezen |
| [modx_versionx_get_version](API.md#modx_versionx_get_version) | Lees de gegevens van één VersionX-versie voordat u besluit deze terug te zetten. | Wijzigt de site |
| [modx_versionx_revert_version](API.md#modx_versionx_revert_version) | Zet een MODX-object terug naar een specifieke VersionX-versie. | Wijzigt de site |

### VirtualPage

| Tool | Vertaald doel | Impact |
| --- | --- | --- |
| [modx_virtualpage_list_events](API.md#modx_virtualpage_list_events) | Geef VirtualPage-events weer die routegroepen aan MODX-events koppelen. | Wijzigt de site |
| [modx_virtualpage_get_event](API.md#modx_virtualpage_get_event) | Lees één VirtualPage-event via id of name, inclusief de routes ervan. | Wijzigt de site |
| [modx_virtualpage_create_event](API.md#modx_virtualpage_create_event) | Maak een VirtualPage-event, meestal OnPageNotFound of OnHandleRequest. | Wijzigt de site |
| [modx_virtualpage_update_event](API.md#modx_virtualpage_update_event) | Werk een VirtualPage-event bij via id of name. | Wijzigt de site |
| [modx_virtualpage_list_handlers](API.md#modx_virtualpage_list_handlers) | Geef VirtualPage-handlers weer die resources, snippets, chunks of dynamische resources renderen. | Wijzigt de site |
| [modx_virtualpage_get_handler](API.md#modx_virtualpage_get_handler) | Lees één VirtualPage-handler via id of name, inclusief routes die deze gebruiken. | Wijzigt de site |
| [modx_virtualpage_create_handler](API.md#modx_virtualpage_create_handler) | Maak een VirtualPage-handler. type kan resource, snippet, chunk, dynamic_resource/template of 0..3 zijn. | Wijzigt de site |
| [modx_virtualpage_update_handler](API.md#modx_virtualpage_update_handler) | Werk een VirtualPage-handler bij via id of name. | Wijzigt de site |
| [modx_virtualpage_list_routes](API.md#modx_virtualpage_list_routes) | Geef VirtualPage-routes weer met de namen van hun event en handler. | Wijzigt de site |
| [modx_virtualpage_get_route](API.md#modx_virtualpage_get_route) | Lees één VirtualPage-route via id, of via route plus optioneel method. | Wijzigt de site |
| [modx_virtualpage_create_route](API.md#modx_virtualpage_create_route) | Maak een VirtualPage-route en koppel indien nodig de bijbehorende eventplugin. | Wijzigt de site |
| [modx_virtualpage_update_route](API.md#modx_virtualpage_update_route) | Werk een VirtualPage-route bij via id. | Wijzigt de site |
| [modx_virtualpage_resolve_route](API.md#modx_virtualpage_resolve_route) | Simuleer VirtualPage-routematching en retourneer de handler plus vp.*-placeholders. | Lezen |
| [modx_virtualpage_delete_event](API.md#modx_virtualpage_delete_event) | Verwijder een VirtualPage-event via id of name (verwijdert volgens het VP-schema ook de bijbehorende routes). | Wijzigt de site |
| [modx_virtualpage_delete_handler](API.md#modx_virtualpage_delete_handler) | Verwijder een VirtualPage-handler via id of name. | Wijzigt de site |
| [modx_virtualpage_delete_route](API.md#modx_virtualpage_delete_route) | Verwijder een VirtualPage-route via id of name. | Wijzigt de site |
| [modx_virtualpage_clear_cache](API.md#modx_virtualpage_clear_cache) | Wis VirtualPage-route-/cachebestanden en vernieuw de MODX-cache. | Wijzigt de site |

### miniShop2

| Tool | Vertaald doel | Impact |
| --- | --- | --- |
| [modx_ms2_list_option_types](API.md#modx_ms2_list_option_types) | Geef de beschikbare veldtypen voor miniShop2-productopties weer. | Wijzigt de site |
| [modx_ms2_list_options](API.md#modx_ms2_list_options) | Geef miniShop2-productopties uit het tabblad met opties in de instellingen weer. | Wijzigt de site |
| [modx_ms2_get_option](API.md#modx_ms2_get_option) | Lees één miniShop2-productoptie via id. | Wijzigt de site |
| [modx_ms2_create_option](API.md#modx_ms2_create_option) | Maak een miniShop2-productoptie bij de opties in de instellingen. | Wijzigt de site |
| [modx_ms2_update_option](API.md#modx_ms2_update_option) | Werk een miniShop2-productoptie bij in de opties van de instellingen. | Wijzigt de site |
| [modx_ms2_assign_option_to_category](API.md#modx_ms2_assign_option_to_category) | Wijs een bestaande miniShop2-optie toe aan een msCategory. | Wijzigt de site |
| [modx_ms2_get_product_options](API.md#modx_ms2_get_product_options) | Lees miniShop2-optiewaarden die aan een product zijn toegewezen. | Wijzigt de site |
| [modx_ms2_update_product_options](API.md#modx_ms2_update_product_options) | Maak, wijzig of verwijder miniShop2-optiewaarden voor een product. | Wijzigt de site |
| [modx_ms2_list_link_types](API.md#modx_ms2_list_link_types) | Geef miniShop2-koppelingstypen (msLink) weer: id, type, name, description. | Wijzigt de site |
| [modx_ms2_get_link_type](API.md#modx_ms2_get_link_type) | Haal een miniShop2-koppelingstype op via id. | Wijzigt de site |
| [modx_ms2_create_link_type](API.md#modx_ms2_create_link_type) | Maak een miniShop2-koppelingstype. 'type' is de relatiesoort die bepaalt hoe producten worden gekoppeld. | Wijzigt de site |
| [modx_ms2_update_link_type](API.md#modx_ms2_update_link_type) | Werk een miniShop2-koppelingstype bij. | Wijzigt de site |
| [modx_ms2_delete_link_type](API.md#modx_ms2_delete_link_type) | Verwijder een miniShop2-koppelingstype via id. | Wijzigt de site |
| [modx_ms2_list_product_links](API.md#modx_ms2_list_product_links) | Geef koppelingen tussen producten (msProductLink) weer, eventueel voor één product. | Wijzigt de site |
| [modx_ms2_create_product_link](API.md#modx_ms2_create_product_link) | Koppel twee producten met een koppelingstype. De relatiesoort van het koppelingstype bepaalt of ook de omgekeerde koppeling wordt gemaakt. | Wijzigt de site |
| [modx_ms2_delete_product_link](API.md#modx_ms2_delete_product_link) | Verwijder een productkoppeling (via het koppelingstype + master + slave). | Wijzigt de site |
| [modx_ms2_list_categories](API.md#modx_ms2_list_categories) | Geef miniShop2-categorieën (msCategory) weer. | Wijzigt de site |
| [modx_ms2_create_category](API.md#modx_ms2_create_category) | Maak een miniShop2-categorie (msCategory-resource). | Wijzigt de site |
| [modx_ms2_update_category](API.md#modx_ms2_update_category) | Werk een miniShop2-categorie bij. | Wijzigt de site |
| [modx_ms2_list_orders](API.md#modx_ms2_list_orders) | Geef miniShop2-bestellingen (msOrder) weer, filterbaar op status/klant/context/datumbereik. | Wijzigt de site |
| [modx_ms2_get_order](API.md#modx_ms2_get_order) | Haal een miniShop2-bestelling op via id (met artikelen/klant). | Wijzigt de site |
| [modx_ms2_update_order](API.md#modx_ms2_update_order) | Werk een miniShop2-bestelling bij — doorgaans om de status (activeert de ms2-logica voor statuswijzigingen), levering of betaling te wijzigen. | Wijzigt de site |

### MIGX

| Tool | Vertaald doel | Impact |
| --- | --- | --- |
| [modx_migx_list_configs](API.md#modx_migx_list_configs) | Geef MIGX-configuraties (migxConfig) weer: id, name, category, published. | Wijzigt de site |
| [modx_migx_get_config](API.md#modx_migx_get_config) | Haal een volledige MIGX-configuratie op via id (formtabs, columns, buttons, filters, permissions — als opgeslagen JSON-tekenreeksen). | Wijzigt de site |
| [modx_migx_create_config](API.md#modx_migx_create_config) | Maak een MIGX-configuratie. formtabs/columns/contextmenus/actionbuttons/columnbuttons/filters zijn JSON-tekenreeksen (dezelfde indeling die MIGX in de manager opslaat). | Wijzigt de site |
| [modx_migx_update_config](API.md#modx_migx_update_config) | Werk een MIGX-configuratie bij. Alleen de velden die u meegeeft worden gewijzigd. | Wijzigt de site |
| [modx_migx_delete_config](API.md#modx_migx_delete_config) | Verwijder een MIGX-configuratie via id. | Wijzigt de site |

### Gebruikers en toegangsrechten

| Tool | Vertaald doel | Impact |
| --- | --- | --- |
| [modx_flush_permissions](API.md#modx_flush_permissions) | Wis de gecachete toegangsrechten ('Flush Permissions' in de manager), zodat ACL-wijzigingen van kracht worden. | Wijzigt de site |
| [modx_list_users](API.md#modx_list_users) | Geef manager-/webgebruikers (modUser) weer. | Lezen |
| [modx_get_user](API.md#modx_get_user) | Haal een gebruiker op via id. | Lezen |
| [modx_create_user](API.md#modx_create_user) | Maak een gebruiker. Geef 'password' mee om het wachtwoord expliciet in te stellen; laat het weg om automatisch een wachtwoord te genereren. Daarnaast worden profielvelden (email, fullname, phone…) geaccepteerd. | Wijzigt de site |
| [modx_update_user](API.md#modx_update_user) | Werk een gebruiker bij (profielvelden, actieve status). | Wijzigt de site |
| [modx_delete_user](API.md#modx_delete_user) | Verwijder een gebruiker via id. | Wijzigt de site |
| [modx_list_user_groups](API.md#modx_list_user_groups) | Geef MODX-gebruikersgroepen weer (id, name, parent, rank). | Lezen |
| [modx_get_user_group](API.md#modx_get_user_group) | Haal één gebruikersgroep op via id. | Lezen |
| [modx_create_user_group](API.md#modx_create_user_group) | Maak een gebruikersgroep. | Wijzigt de site |
| [modx_update_user_group](API.md#modx_update_user_group) | Werk een gebruikersgroep bij. | Wijzigt de site |
| [modx_delete_user_group](API.md#modx_delete_user_group) | Verwijder een gebruikersgroep via id. | Wijzigt de site |
| [modx_list_user_group_members](API.md#modx_list_user_group_members) | Geef de leden van een gebruikersgroep weer. | Lezen |
| [modx_add_user_to_group](API.md#modx_add_user_to_group) | Voeg een gebruiker toe aan een gebruikersgroep, eventueel met een rol. | Wijzigt de site |
| [modx_update_group_member](API.md#modx_update_group_member) | Werk het groepslidmaatschap van een gebruiker bij (bijv. om de rol te wijzigen). | Wijzigt de site |
| [modx_remove_user_from_group](API.md#modx_remove_user_from_group) | Verwijder een gebruiker uit een gebruikersgroep. | Wijzigt de site |
| [modx_list_roles](API.md#modx_list_roles) | Geef toegangsrollen weer (id, name, authority). | Lezen |
| [modx_get_role](API.md#modx_get_role) | Haal een rol op via id. | Lezen |
| [modx_create_role](API.md#modx_create_role) | Maak een rol. | Wijzigt de site |
| [modx_update_role](API.md#modx_update_role) | Werk een rol bij. | Wijzigt de site |
| [modx_delete_role](API.md#modx_delete_role) | Verwijder een rol via id. | Wijzigt de site |
| [modx_list_access_policies](API.md#modx_list_access_policies) | Geef toegangsbeleidsregels weer (id, name, description, template). | Lezen |
| [modx_get_access_policy](API.md#modx_get_access_policy) | Haal een toegangsbeleidsregel op via id. | Lezen |
| [modx_create_access_policy](API.md#modx_create_access_policy) | Maak een toegangsbeleidsregel. | Wijzigt de site |
| [modx_update_access_policy](API.md#modx_update_access_policy) | Werk een toegangsbeleidsregel bij (bijv. de permissions-map). | Grote impact |
| [modx_delete_access_policy](API.md#modx_delete_access_policy) | Verwijder een toegangsbeleidsregel via id. | Wijzigt de site |
| [modx_list_access_policy_templates](API.md#modx_list_access_policy_templates) | Geef templates voor toegangsbeleid weer. | Lezen |
| [modx_get_access_policy_template](API.md#modx_get_access_policy_template) | Haal een template voor toegangsbeleid op via id. | Lezen |
| [modx_create_access_policy_template](API.md#modx_create_access_policy_template) | Maak een template voor toegangsbeleid. | Wijzigt de site |
| [modx_update_access_policy_template](API.md#modx_update_access_policy_template) | Werk een template voor toegangsbeleid bij. | Wijzigt de site |
| [modx_delete_access_policy_template](API.md#modx_delete_access_policy_template) | Verwijder een template voor toegangsbeleid via id. | Wijzigt de site |
| [modx_list_access_permissions](API.md#modx_list_access_permissions) | Geef de permissies weer die een beleidstemplate definieert (alleen-lezencatalogus). | Lezen |
| [modx_list_resource_groups](API.md#modx_list_resource_groups) | Geef resourcegroepen weer (id, name). | Lezen |
| [modx_get_resource_group](API.md#modx_get_resource_group) | Haal een resourcegroep op via id. | Lezen |
| [modx_list_resource_group_resources](API.md#modx_list_resource_group_resources) | Geef resources weer die aan een resourcegroep zijn toegewezen. | Lezen |
| [modx_create_resource_group](API.md#modx_create_resource_group) | Maak een resourcegroep. | Wijzigt de site |
| [modx_update_resource_group](API.md#modx_update_resource_group) | Werk een resourcegroep bij. | Wijzigt de site |
| [modx_delete_resource_group](API.md#modx_delete_resource_group) | Verwijder een resourcegroep via id. | Wijzigt de site |
| [modx_assign_resource_to_group](API.md#modx_assign_resource_to_group) | Voeg een resource (document) toe aan een resourcegroep. | Wijzigt de site |
| [modx_remove_resource_from_group](API.md#modx_remove_resource_from_group) | Verwijder een resource (document) uit een resourcegroep. | Wijzigt de site |
| [modx_list_context_access](API.md#modx_list_context_access) | Geef ACL-vermeldingen voor contexttoegang weer, eventueel gefilterd op usergroup/context/policy. | Lezen |
| [modx_grant_context_access](API.md#modx_grant_context_access) | Verleen een gebruikersgroep toegang tot een context met een beleidsregel en een minimale rol-authority. | Wijzigt de site |
| [modx_update_context_access](API.md#modx_update_context_access) | Werk een ACL-vermelding voor contexttoegang bij via id. | Wijzigt de site |
| [modx_revoke_context_access](API.md#modx_revoke_context_access) | Verwijder een ACL-vermelding voor contexttoegang via id. | Wijzigt de site |
| [modx_list_resourcegroup_access](API.md#modx_list_resourcegroup_access) | Geef ACL-vermeldingen voor toegang tot resourcegroepen weer. | Lezen |
| [modx_grant_resourcegroup_access](API.md#modx_grant_resourcegroup_access) | Verleen een gebruikersgroep toegang tot een resourcegroep met een beleidsregel en een minimale rol-authority. | Wijzigt de site |
| [modx_update_resourcegroup_access](API.md#modx_update_resourcegroup_access) | Werk een ACL-vermelding voor toegang tot een resourcegroep bij via id. | Wijzigt de site |
| [modx_revoke_resourcegroup_access](API.md#modx_revoke_resourcegroup_access) | Verwijder een ACL-vermelding voor toegang tot een resourcegroep via id. | Wijzigt de site |

### ClientConfig

| Tool | Vertaald doel | Impact |
| --- | --- | --- |
| [modx_clientconfig_list_settings](API.md#modx_clientconfig_list_settings) | Geef ClientConfig-instellingen weer, inclusief contextspecifieke waarden. | Wijzigt de site |
| [modx_clientconfig_get_setting](API.md#modx_clientconfig_get_setting) | Haal een ClientConfig-instelling op via id of key. | Wijzigt de site |
| [modx_clientconfig_create_setting](API.md#modx_clientconfig_create_setting) | Maak een ClientConfig-instelling. | Wijzigt de site |
| [modx_clientconfig_update_setting](API.md#modx_clientconfig_update_setting) | Werk een ClientConfig-instelling bij via id of key. | Wijzigt de site |
| [modx_clientconfig_delete_setting](API.md#modx_clientconfig_delete_setting) | Verwijder een ClientConfig-instelling via id of key. | Wijzigt de site |

### Eigenschappensets

| Tool | Vertaald doel | Impact |
| --- | --- | --- |
| [modx_list_property_sets](API.md#modx_list_property_sets) | Geef eigenschappensets (modPropertySet) weer: id, name, description, category. | Lezen |
| [modx_get_property_set](API.md#modx_get_property_set) | Haal een eigenschappenset op via id (inclusief de eigenschappen). | Lezen |
| [modx_create_property_set](API.md#modx_create_property_set) | Maak een eigenschappenset. 'properties' is een JSON-object met eigenschapsdefinities. | Wijzigt de site |
| [modx_update_property_set](API.md#modx_update_property_set) | Werk een eigenschappenset bij. | Wijzigt de site |
| [modx_delete_property_set](API.md#modx_delete_property_set) | Verwijder een eigenschappenset via id. | Wijzigt de site |
| [modx_assign_property_set](API.md#modx_assign_property_set) | Koppel een eigenschappenset aan een element. Identificeer het element met id + ofwel element_class (bijv. 'modSnippet') ofwel element_type ('snippet'/'chunk'/'template'/'plugin'/'tv'). | Wijzigt de site |
| [modx_unassign_property_set](API.md#modx_unassign_property_set) | Ontkoppel een eigenschappenset van een element (dezelfde identificatie als bij het koppelen). | Wijzigt de site |

### Contexten en contextinstellingen

| Tool | Vertaald doel | Impact |
| --- | --- | --- |
| [modx_list_contexts](API.md#modx_list_contexts) | Geef contexten (modContext) weer: key, name, description, rank. | Lezen |
| [modx_get_context](API.md#modx_get_context) | Haal een context op via key. | Lezen |
| [modx_create_context](API.md#modx_create_context) | Maak een context. 'key' is de unieke identificatie (bijv. 'mobile'). | Wijzigt de site |
| [modx_update_context](API.md#modx_update_context) | Werk een context bij (name/description/rank). 'settings' kan een JSON-array met contextinstellingen zijn (geavanceerd). | Wijzigt de site |
| [modx_delete_context](API.md#modx_delete_context) | Verwijder een context via key. | Grote impact |
| [modx_list_context_settings](API.md#modx_list_context_settings) | Geef de instellingen van een context (modContextSetting) weer. | Lezen |
| [modx_get_context_setting](API.md#modx_get_context_setting) | Haal één contextinstelling op (via context_key + key). | Lezen |
| [modx_create_context_setting](API.md#modx_create_context_setting) | Maak een instelling op contextniveau (overschrijft de systeeminstelling voor die context). | Wijzigt de site |
| [modx_update_context_setting](API.md#modx_update_context_setting) | Werk een contextinstelling bij (via context_key + key). | Wijzigt de site |
| [modx_delete_context_setting](API.md#modx_delete_context_setting) | Verwijder een contextinstelling (via context_key + key). | Wijzigt de site |

### Pakketten en providers

| Tool | Vertaald doel | Impact |
| --- | --- | --- |
| [modx_install_package](API.md#modx_install_package) | Installeer een transportpakket van een provider (standaard modx.com) op naam, bijv. {package:'MIGX'}. | Grote impact |
| [modx_uninstall_package](API.md#modx_uninstall_package) | Deïnstalleer een transportpakket via de signature (bijv. 'migx-2.13.0-pl'). | Grote impact |
| [modx_list_providers](API.md#modx_list_providers) | Geef transportproviders weer (modx.com en aangepaste providers, bijv. modstore.pro): id, name, service_url. | Lezen |
| [modx_search_packages](API.md#modx_search_packages) | Doorzoek de catalogus van een provider (gebruik dit vóór install_package). | Lezen |
| [modx_create_provider](API.md#modx_create_provider) | Voeg een transportprovider toe (bijv. modstore.pro). | Wijzigt de site |
| [modx_update_provider](API.md#modx_update_provider) | Werk een transportprovider bij. | Wijzigt de site |
| [modx_delete_provider](API.md#modx_delete_provider) | Verwijder een transportprovider via id. | Wijzigt de site |

### Namespaces

| Tool | Vertaald doel | Impact |
| --- | --- | --- |
| [modx_list_namespaces](API.md#modx_list_namespaces) | Geef namespaces (modNamespace) weer: name, path, assets_path. | Lezen |
| [modx_create_namespace](API.md#modx_create_namespace) | Maak een namespace. | Wijzigt de site |
| [modx_update_namespace](API.md#modx_update_namespace) | Werk een namespace bij (via name). | Wijzigt de site |
| [modx_delete_namespace](API.md#modx_delete_namespace) | Verwijder een namespace via name ('core' kan niet worden verwijderd). | Wijzigt de site |

### Lexicon en vertalingen

| Tool | Vertaald doel | Impact |
| --- | --- | --- |
| [modx_list_lexicon_entries](API.md#modx_list_lexicon_entries) | Geef lexiconvermeldingen weer, gefilterd op namespace + topic + language (en optioneel search). | Lezen |
| [modx_list_lexicon_topics](API.md#modx_list_lexicon_topics) | Geef lexicononderwerpen voor een namespace/language weer. | Lezen |
| [modx_set_lexicon_entry](API.md#modx_set_lexicon_entry) | Maak of overschrijf een lexiconvermelding (DB-overschrijving van de bestandswaarde). | Wijzigt de site |
| [modx_revert_lexicon_entry](API.md#modx_revert_lexicon_entry) | Herstel een lexiconvermelding — verwijder de DB-overschrijving zodat de bestandswaarde van toepassing is. | Wijzigt de site |

### Onderhoud en diagnostiek

| Tool | Vertaald doel | Impact |
| --- | --- | --- |
| [modx_get_site_state](API.md#modx_get_site_state) | Lees de siterevisie waarvoor de connector de gezaghebbende bron is en de ondersteuning voor atomaire voorafgaande voorwaarden die externe tools gebruiken. | Lezen |
| [modx_describe_object](API.md#modx_describe_object) | Schema-introspectie: geef de velden van een xPDO-klasse weer (name + php/db-type, null, default) en de primaire sleutel, zodat u ECHTE veldnamen gebruikt in plaats van te gokken. | Lezen |
| [modx_read_error_log](API.md#modx_read_error_log) | Lees het laatste deel van het MODX-foutlogboek (core/cache/logs/error.log) voor diagnostiek. | Lezen |
| [modx_refresh_uris](API.md#modx_refresh_uris) | Genereer alle resource-URI’s opnieuw (voer dit uit na bulkwijzigingen in aliassen/structuur die verouderde URI’s hebben achtergelaten). | Wijzigt de site |
| [modx_remove_locks](API.md#modx_remove_locks) | Wis verouderde bewerkingsvergrendelingen in de manager (wanneer een element/resource als vergrendeld wordt gemeld door een niet meer actieve sessie). | Wijzigt de site |
| [modx_project_overview](API.md#modx_project_overview) | Krijg in ÉÉN compacte, weinig kostbare aanroep een overzicht van de volledige geïnstalleerde site. | Lezen |
| [modx_system_info](API.md#modx_system_info) | Omgevings-/diagnostische informatie: MODX-versie, modxMCP-versie, PHP-versie, db-type, belangrijke paden. | Lezen |
| [modx_regenerate_token](API.md#modx_regenerate_token) | Vervang modxmcp.api_token door een nieuwe willekeurige waarde en retourneer deze. | Grote impact |
| [modx_help](API.md#modx_help) | Ingebouwde documentatie. Roep dit zonder argumenten aan om onderwerpen weer te geven; geef {topic} mee voor een handleiding (bijv. 'tv_input_types', 'study_component', 'minishop2', 'migx', 'acl', 'getting_started'). Lees het relevante onderwerp vóór onbekend werk, of om te leren hoe u een pas geïnstalleerde component gebruikt. | Lezen |
| [modx_list_actions](API.md#modx_list_actions) | Geef de actienamen weer die deze modxMCP-serverbuild ondersteunt, gegroepeerd per gebied. | Lezen |
| [modx_clear_cache](API.md#modx_clear_cache) | Vernieuw de MODX-cache. Geef partitions mee (bijv. ['resource','context_settings']) om alleen die te vernieuwen; laat dit weg voor een volledige vernieuwing. | Wijzigt de site |
| [modx_read_audit_log](API.md#modx_read_audit_log) | Lees het auditspoor van modxMCP-schrijfacties (nieuwste als laatste). | Lezen |
| [modx_run_processor](API.md#modx_run_processor) | Voer ELKE willekeurige MODX-processor rechtstreeks uit (uitwijkmogelijkheid). | Grote impact |
