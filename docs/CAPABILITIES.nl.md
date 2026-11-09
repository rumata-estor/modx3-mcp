# MODX MCP — mogelijkheden en toolcatalogus

[English](CAPABILITIES.md) · [Русский](CAPABILITIES.ru.md) · [Español](CAPABILITIES.es.md) · [Deutsch](CAPABILITIES.de.md) · [Français](CAPABILITIES.fr.md) · [Українська](CAPABILITIES.uk.md) · [Қазақша](CAPABILITIES.kk.md) · [Nederlands](CAPABILITIES.nl.md) · [Polski](CAPABILITIES.pl.md)

[Installatie](INSTALL.nl.md) · [API-referentie](API.nl.md)

**MODX MCP 1.2.5 · 198 openbare MCP-tools · 19 functionele groepen · MODX Revolution 2.8.x / 3.x**

MODX MCP biedt een MCP-compatibele AI-client benoemde handelingen voor MODX-objecten zonder dat elke taak als ruwe SQL of een bestandssysteemcommando moet worden beschreven. Het ondersteunt het bekijken van sites, het wijzigen van inhoud en code, TV-velden, pakketten, toegangsbeheer en geselecteerde uitbreidingen.

Dit overzicht beantwoordt **Wat kan ik met MODX MCP doen?** Voor de exacte veldtypen, enumeraties en invoerschemas: zie de [technische API](API.md). Voor toegang: lees [Installatie](INSTALL.nl.md).

**Impactlabels zijn uitleg, geen rechtencontroles.** Ook leestoegang kan vertrouwelijke gegevens teruggeven. Een actie met grote impact kan bij verkeerd gebruik de site of beveiliging beschadigen.

## Toolindex


| Groep | Tools |
| --- | ---: |
| [Elementen en resources](#elementen-en-resources) | 21 |
| [Resource-TV’s](#resource-tvs) | 4 |
| [TV-veldtypen](#tv-veldtypen) | 2 |
| [Systeeminstellingen](#systeeminstellingen) | 5 |
| [Mediabronnen en bestanden](#mediabronnen-en-bestanden) | 13 |
| [Componenten en bestanden](#componenten-en-bestanden) | 4 |
| [Code doorzoeken en afhankelijkheden](#code-doorzoeken-en-afhankelijkheden) | 5 |
| [VersionX](#versionx) | 3 |
| [VirtualPage](#virtualpage) | 17 |
| [miniShop2](#minishop2) | 22 |
| [MIGX](#migx) | 5 |
| [Gebruikers en toegangsrechten](#gebruikers-en-toegangsrechten) | 47 |
| [ClientConfig](#clientconfig) | 5 |
| [Eigenschappensets](#eigenschappensets) | 7 |
| [Contexten en contextinstellingen](#contexten-en-contextinstellingen) | 10 |
| [Pakketten en providers](#pakketten-en-providers) | 7 |
| [Namespaces](#namespaces) | 4 |
| [Lexicon en vertalingen](#lexicon-en-vertalingen) | 4 |
| [Onderhoud en diagnostiek](#onderhoud-en-diagnostiek) | 13 |
| **Totaal** | **198** |

## Elementen en resources

Beheer pagina’s, templates, chunks, snippets, plugins, TV’s en categorieën als MODX-objecten.

| Tool | Wat deze doet | Impact |
| --- | --- | --- |
| `modx_list_elements` | Geef MODX-elementen van een bepaald type weer (id + name). | Lezen |
| `modx_list_categories` | Elementcategorieën tonen. | Lezen |
| `modx_get_category` | Categorie en gebruik lezen. | Lezen |
| `modx_create_category` | Categorie maken. | Grote impact |
| `modx_update_category` | Categorie hernoemen of verplaatsen. | Grote impact |
| `modx_delete_category` | Lege categorie gecontroleerd verwijderen. | Grote impact |
| `modx_assign_element_category` | Categorie aan element toewijzen. | Wijzigt de site |
| `modx_make_static` | Zet een chunk/snippet/template/plugin om naar een statisch bestand onder core/elements/ (schrijft het bestand, stelt static=1 + source=Filesystem in). | Grote impact |
| `modx_get_element` | Lees een MODX-element (retourneert de velden + volledige code). | Lezen |
| `modx_update_element_property` | Wijzig veilig één bestaande standaardeigenschap met een CAS-controle op de verwachte waarde; vervang nooit code of andere eigenschappen. | Wijzigt de site |
| `modx_update_element` | Werk een MODX-element bij door de volledige inhoud te VERVANGEN (verstuur de volledige nieuwe inhoud). | Grote impact |
| `modx_create_element` | Maak een nieuw MODX-element. | Grote impact |
| `modx_delete_element` | Verwijder een MODX-element. Voer dit EERST uit met dry_run:true — dit retourneert wat zou worden verwijderd en waar nog naar het element wordt verwezen (voor resources: het aantal onderliggende resources), zonder iets te verwijderen. Beoordeel dit vóór de daadwerkelijke verwijdering. | Grote impact |
| `modx_view_element` | Bekijk een chunk/snippet/template/plugin als GENUMMERDE regels (zoals cat -n), eventueel beperkt met start_line/end_line. | Wijzigt de site |
| `modx_edit_element_lines` | Bewerk een chunk/snippet/template/plugin PER REGEL door alleen de gewijzigde regels te verzenden (het hele element hoeft niet opnieuw te worden verzonden). | Grote impact |
| `modx_bulk_resources` | Pas ÉÉN bewerking tegelijk toe op meerdere resources: publish, unpublish, set_template (vereist template), move (vereist parent_to en/of context_to), of delete. | Grote impact |
| `modx_duplicate_resource` | Dupliceer een resource. id = bronresource; optioneel name (nieuwe pagetitle), duplicate_children (bool), published_mode (preserve\|publish\|unpublish). | Wijzigt de site |
| `modx_duplicate_element` | Dupliceer een element (chunk/snippet/template/plugin/tv). type + id; optioneel name voor de kopie. | Wijzigt de site |
| `modx_undelete_resource` | Herstel een voorlopig verwijderde resource uit de prullenbak (maakt de verwijdering ongedaan). id = resource-id. | Wijzigt de site |
| `modx_empty_recycle_bin` | Verwijder ALLE resources in de prullenbak (voorlopig verwijderde resources) definitief. | Grote impact |
| `modx_reorder_resources` | Wijzig de volgorde van resources in de boomstructuur door menuindex (en eventueel parent) in te stellen. items = [{id, menuindex, parent?}], toegepast per resource. | Wijzigt de site |

## Resource-TV’s

Lees of wijzig aangepaste resourcevelden en inspecteer opgeslagen TV-waarden.

| Tool | Wat deze doet | Impact |
| --- | --- | --- |
| `modx_get_resource_tvs` | Haal de waarden van templatevariabelen (TV’s) van een resource op. | Lezen |
| `modx_update_resource_tvs` | Werk de waarden van templatevariabelen (TV’s) van een resource bij. | Wijzigt de site |
| `modx_list_tv_values` | Geef de expliciet opgeslagen waarden van één TV voor alle resources weer, inclusief verouderde waarden op resources waarvan het huidige template die TV mogelijk niet meer gebruikt. | Lezen |
| `modx_clear_tv_values` | Bekijk vooraf of wis alle expliciet opgeslagen waarden van één TV. | Wijzigt de site |

## TV-veldtypen

Ontdek beschikbare TV-veldtypen en kies er een op basis van de toepassing.

| Tool | Wat deze doet | Impact |
| --- | --- | --- |
| `modx_suggest_tv_type` | Weet u niet welk TV-invoertype bij een behoefte past? Beschrijf deze (in het Engels of Russisch) en ontvang gerangschikte kandidaat-field_types met redenen + een bewerkbaar create_element-skelet voor de beste keuze (met de extra sleutels die dat type nodig heeft). | Lezen |
| `modx_list_tv_input_types` | Geef de op deze site beschikbare TV-invoertypen (widgets) weer. | Lezen |

## Systeeminstellingen

Bekijk en wijzig configuratie die de hele website kan beïnvloeden.

| Tool | Wat deze doet | Impact |
| --- | --- | --- |
| `modx_list_system_settings` | Geef MODX-systeeminstellingen weer. | Lezen |
| `modx_get_system_setting` | Haal één MODX-systeeminstelling op via de sleutel. | Lezen |
| `modx_create_system_setting` | Maak een MODX-systeeminstelling. | Wijzigt de site |
| `modx_update_system_setting` | Werk een MODX-systeeminstelling bij via de sleutel. | Grote impact |
| `modx_delete_system_setting` | Verwijder een MODX-systeeminstelling via de sleutel. | Grote impact |

## Mediabronnen en bestanden

Navigeer door mediabronnen en beheer beschikbare bestanden en mappen.

| Tool | Wat deze doet | Impact |
| --- | --- | --- |
| `modx_list_media_sources` | Geef MODX-mediabronnen weer. | Lezen |
| `modx_get_media_source` | Haal één MODX-mediabron op. | Lezen |
| `modx_create_media_source` | Maak een media- (bestands)bron. class_key is standaard sources.modFileMediaSource. 'properties' is een {name:value}-map met bronparameters (bijv. {\"basePath\":\"assets/files/\",\"baseUrl\":\"assets/files/\"}) die met de bron worden samengevoegd. | Wijzigt de site |
| `modx_update_media_source` | Werk een mediabron bij. 'properties' is een {name:value}-map die wordt samengevoegd met de bronparameters (de overige blijven behouden) — gebruik deze om basePath/baseUrl/etc. in te stellen. | Wijzigt de site |
| `modx_delete_media_source` | Verwijder een mediabron via id. | Wijzigt de site |
| `modx_list_media_source_files` | Geef bestanden en mappen binnen een mediabron weer. | Lezen |
| `modx_read_media_source_file` | Lees een bestand binnen een mediabron. | Lezen |
| `modx_create_media_file` | Maak een bestand binnen een mediabron (schrijft inhoud). source = bron-id of -naam, path = doelmap (relatief ten opzichte van de basismap van de bron), name = bestandsnaam, content = bestandsinhoud. | Wijzigt de site |
| `modx_update_media_file` | Overschrijf de inhoud van een bestand binnen een mediabron. source = id/naam, path = bestandspad binnen de bron, content = nieuwe inhoud. | Wijzigt de site |
| `modx_delete_media_file` | Verwijder een bestand binnen een mediabron. source = id/naam, path = bestandspad binnen de bron. | Grote impact |
| `modx_rename_media_file` | Hernoem een bestand binnen een mediabron. source = id/naam, path = huidig bestandspad, new_name = nieuwe bestandsnaam. | Wijzigt de site |
| `modx_create_media_folder` | Maak een map binnen een mediabron. source = id/naam, parent = bovenliggende map (standaard de hoofdmap), name = mapnaam. | Wijzigt de site |
| `modx_delete_media_folder` | Verwijder een map (en de inhoud ervan) binnen een mediabron. source = id/naam, path = mappad binnen de bron. | Grote impact |

## Componenten en bestanden

Ontdek geïnstalleerde uitbreidingen en inspecteer toegankelijke bestanden.

| Tool | Wat deze doet | Impact |
| --- | --- | --- |
| `modx_list_installed_components` | Geef geïnstalleerde MODX-componenten uit core/components en assets/components weer. | Lezen |
| `modx_get_component_files` | Geef bestanden en mappen binnen een geïnstalleerde MODX-component weer. | Lezen |
| `modx_read_component_file` | Lees een bestand uit een geïnstalleerde MODX-component. | Lezen |
| `modx_check_integrations` | Meld welke populaire MODX-uitbreidingen (miniShop2, MIGX, pdoTools, Tickets, enz.) op deze site zijn geïnstalleerd en wat modxMCP met elk ervan kan doen. | Lezen |

## Code doorzoeken en afhankelijkheden

Zoek implementatiedetails, verwijzingen en relaties tussen site-elementen.

| Tool | Wat deze doet | Impact |
| --- | --- | --- |
| `modx_search_code` | Doorzoek de volledige tekst van de INHOUD (en namen) van elementen en resources. | Lezen |
| `modx_find_usages` | Zoek waar een element wordt gebruikt: overeenkomsten met de naam in de inhoud van alle code, plus — bij een template — de resources die aan dat template zijn toegewezen. | Lezen |
| `modx_list_resources` | Geef resources (de inhoudsboom) weer, eventueel gefilterd op parent, context, verwijderingsstatus of een zoekopdracht op pagetitle/alias/uri. | Lezen |
| `modx_replace_across` | Zoek en vervang sitebreed in code-elementen (chunk/snippet/template/plugin): vind elk element waarvan de INHOUD find bevat en vervang ALLE voorkomens door replacement, in ÉÉN aanroep (zonder dat u elk element afzonderlijk hoeft te lezen/schrijven). | Grote impact |
| `modx_dependency_graph` | Breng in één aanroep de ONDERLINGE VERBINDINGEN tussen site-elementen in kaart: welk template welke chunk/snippet/TV gebruikt, welke snippet welke chunk rendert en waar elke TV is gekoppeld. | Lezen |

## VersionX

Bekijk of herstel historische versies als VersionX is geïnstalleerd.

| Tool | Wat deze doet | Impact |
| --- | --- | --- |
| `modx_versionx_list_versions` | Geef VersionX-versies van een MODX-resource, chunk, snippet, template, plugin of TV weer. | Lezen |
| `modx_versionx_get_version` | Lees de gegevens van één VersionX-versie voordat u besluit deze terug te zetten. | Wijzigt de site |
| `modx_versionx_revert_version` | Zet een MODX-object terug naar een specifieke VersionX-versie. | Wijzigt de site |

## VirtualPage

Beheer eventkoppelingen, handlers en routes van VirtualPage.

| Tool | Wat deze doet | Impact |
| --- | --- | --- |
| `modx_virtualpage_list_events` | Geef VirtualPage-events weer die routegroepen aan MODX-events koppelen. | Wijzigt de site |
| `modx_virtualpage_get_event` | Lees één VirtualPage-event via id of name, inclusief de routes ervan. | Wijzigt de site |
| `modx_virtualpage_create_event` | Maak een VirtualPage-event, meestal OnPageNotFound of OnHandleRequest. | Wijzigt de site |
| `modx_virtualpage_update_event` | Werk een VirtualPage-event bij via id of name. | Wijzigt de site |
| `modx_virtualpage_list_handlers` | Geef VirtualPage-handlers weer die resources, snippets, chunks of dynamische resources renderen. | Wijzigt de site |
| `modx_virtualpage_get_handler` | Lees één VirtualPage-handler via id of name, inclusief routes die deze gebruiken. | Wijzigt de site |
| `modx_virtualpage_create_handler` | Maak een VirtualPage-handler. type kan resource, snippet, chunk, dynamic_resource/template of 0..3 zijn. | Wijzigt de site |
| `modx_virtualpage_update_handler` | Werk een VirtualPage-handler bij via id of name. | Wijzigt de site |
| `modx_virtualpage_list_routes` | Geef VirtualPage-routes weer met de namen van hun event en handler. | Wijzigt de site |
| `modx_virtualpage_get_route` | Lees één VirtualPage-route via id, of via route plus optioneel method. | Wijzigt de site |
| `modx_virtualpage_create_route` | Maak een VirtualPage-route en koppel indien nodig de bijbehorende eventplugin. | Wijzigt de site |
| `modx_virtualpage_update_route` | Werk een VirtualPage-route bij via id. | Wijzigt de site |
| `modx_virtualpage_resolve_route` | Simuleer VirtualPage-routematching en retourneer de handler plus vp.*-placeholders. | Lezen |
| `modx_virtualpage_delete_event` | Verwijder een VirtualPage-event via id of name (verwijdert volgens het VP-schema ook de bijbehorende routes). | Wijzigt de site |
| `modx_virtualpage_delete_handler` | Verwijder een VirtualPage-handler via id of name. | Wijzigt de site |
| `modx_virtualpage_delete_route` | Verwijder een VirtualPage-route via id of name. | Wijzigt de site |
| `modx_virtualpage_clear_cache` | Wis VirtualPage-route-/cachebestanden en vernieuw de MODX-cache. | Wijzigt de site |

## miniShop2

Beheer productopties, categorieën, productrelaties en bestellingen.

| Tool | Wat deze doet | Impact |
| --- | --- | --- |
| `modx_ms2_list_option_types` | Geef de beschikbare veldtypen voor miniShop2-productopties weer. | Wijzigt de site |
| `modx_ms2_list_options` | Geef miniShop2-productopties uit het tabblad met opties in de instellingen weer. | Wijzigt de site |
| `modx_ms2_get_option` | Lees één miniShop2-productoptie via id. | Wijzigt de site |
| `modx_ms2_create_option` | Maak een miniShop2-productoptie bij de opties in de instellingen. | Wijzigt de site |
| `modx_ms2_update_option` | Werk een miniShop2-productoptie bij in de opties van de instellingen. | Wijzigt de site |
| `modx_ms2_assign_option_to_category` | Wijs een bestaande miniShop2-optie toe aan een msCategory. | Wijzigt de site |
| `modx_ms2_get_product_options` | Lees miniShop2-optiewaarden die aan een product zijn toegewezen. | Wijzigt de site |
| `modx_ms2_update_product_options` | Maak, wijzig of verwijder miniShop2-optiewaarden voor een product. | Wijzigt de site |
| `modx_ms2_list_link_types` | Geef miniShop2-koppelingstypen (msLink) weer: id, type, name, description. | Wijzigt de site |
| `modx_ms2_get_link_type` | Haal een miniShop2-koppelingstype op via id. | Wijzigt de site |
| `modx_ms2_create_link_type` | Maak een miniShop2-koppelingstype. 'type' is de relatiesoort die bepaalt hoe producten worden gekoppeld. | Wijzigt de site |
| `modx_ms2_update_link_type` | Werk een miniShop2-koppelingstype bij. | Wijzigt de site |
| `modx_ms2_delete_link_type` | Verwijder een miniShop2-koppelingstype via id. | Wijzigt de site |
| `modx_ms2_list_product_links` | Geef koppelingen tussen producten (msProductLink) weer, eventueel voor één product. | Wijzigt de site |
| `modx_ms2_create_product_link` | Koppel twee producten met een koppelingstype. De relatiesoort van het koppelingstype bepaalt of ook de omgekeerde koppeling wordt gemaakt. | Wijzigt de site |
| `modx_ms2_delete_product_link` | Verwijder een productkoppeling (via het koppelingstype + master + slave). | Wijzigt de site |
| `modx_ms2_list_categories` | Geef miniShop2-categorieën (msCategory) weer. | Wijzigt de site |
| `modx_ms2_create_category` | Maak een miniShop2-categorie (msCategory-resource). | Wijzigt de site |
| `modx_ms2_update_category` | Werk een miniShop2-categorie bij. | Wijzigt de site |
| `modx_ms2_list_orders` | Geef miniShop2-bestellingen (msOrder) weer, filterbaar op status/klant/context/datumbereik. | Wijzigt de site |
| `modx_ms2_get_order` | Haal een miniShop2-bestelling op via id (met artikelen/klant). | Wijzigt de site |
| `modx_ms2_update_order` | Werk een miniShop2-bestelling bij — doorgaans om de status (activeert de ms2-logica voor statuswijzigingen), levering of betaling te wijzigen. | Wijzigt de site |

## MIGX

Lees of wijzig MIGX-configuraties als deze uitbreiding is geïnstalleerd.

| Tool | Wat deze doet | Impact |
| --- | --- | --- |
| `modx_migx_list_configs` | Geef MIGX-configuraties (migxConfig) weer: id, name, category, published. | Wijzigt de site |
| `modx_migx_get_config` | Haal een volledige MIGX-configuratie op via id (formtabs, columns, buttons, filters, permissions — als opgeslagen JSON-tekenreeksen). | Wijzigt de site |
| `modx_migx_create_config` | Maak een MIGX-configuratie. formtabs/columns/contextmenus/actionbuttons/columnbuttons/filters zijn JSON-tekenreeksen (dezelfde indeling die MIGX in de manager opslaat). | Wijzigt de site |
| `modx_migx_update_config` | Werk een MIGX-configuratie bij. Alleen de velden die u meegeeft worden gewijzigd. | Wijzigt de site |
| `modx_migx_delete_config` | Verwijder een MIGX-configuratie via id. | Wijzigt de site |

## Gebruikers en toegangsrechten

Beheer gebruikers, groepen, rollen, toegangsbeleid en rechten voor resources en contexten.

| Tool | Wat deze doet | Impact |
| --- | --- | --- |
| `modx_flush_permissions` | Wis de gecachete toegangsrechten ('Flush Permissions' in de manager), zodat ACL-wijzigingen van kracht worden. | Wijzigt de site |
| `modx_list_users` | Geef manager-/webgebruikers (modUser) weer. | Lezen |
| `modx_get_user` | Haal een gebruiker op via id. | Lezen |
| `modx_create_user` | Maak een gebruiker. Geef 'password' mee om het wachtwoord expliciet in te stellen; laat het weg om automatisch een wachtwoord te genereren. Daarnaast worden profielvelden (email, fullname, phone…) geaccepteerd. | Wijzigt de site |
| `modx_update_user` | Werk een gebruiker bij (profielvelden, actieve status). | Wijzigt de site |
| `modx_delete_user` | Verwijder een gebruiker via id. | Wijzigt de site |
| `modx_list_user_groups` | Geef MODX-gebruikersgroepen weer (id, name, parent, rank). | Lezen |
| `modx_get_user_group` | Haal één gebruikersgroep op via id. | Lezen |
| `modx_create_user_group` | Maak een gebruikersgroep. | Wijzigt de site |
| `modx_update_user_group` | Werk een gebruikersgroep bij. | Wijzigt de site |
| `modx_delete_user_group` | Verwijder een gebruikersgroep via id. | Wijzigt de site |
| `modx_list_user_group_members` | Geef de leden van een gebruikersgroep weer. | Lezen |
| `modx_add_user_to_group` | Voeg een gebruiker toe aan een gebruikersgroep, eventueel met een rol. | Wijzigt de site |
| `modx_update_group_member` | Werk het groepslidmaatschap van een gebruiker bij (bijv. om de rol te wijzigen). | Wijzigt de site |
| `modx_remove_user_from_group` | Verwijder een gebruiker uit een gebruikersgroep. | Wijzigt de site |
| `modx_list_roles` | Geef toegangsrollen weer (id, name, authority). | Lezen |
| `modx_get_role` | Haal een rol op via id. | Lezen |
| `modx_create_role` | Maak een rol. | Wijzigt de site |
| `modx_update_role` | Werk een rol bij. | Wijzigt de site |
| `modx_delete_role` | Verwijder een rol via id. | Wijzigt de site |
| `modx_list_access_policies` | Geef toegangsbeleidsregels weer (id, name, description, template). | Lezen |
| `modx_get_access_policy` | Haal een toegangsbeleidsregel op via id. | Lezen |
| `modx_create_access_policy` | Maak een toegangsbeleidsregel. | Wijzigt de site |
| `modx_update_access_policy` | Werk een toegangsbeleidsregel bij (bijv. de permissions-map). | Grote impact |
| `modx_delete_access_policy` | Verwijder een toegangsbeleidsregel via id. | Wijzigt de site |
| `modx_list_access_policy_templates` | Geef templates voor toegangsbeleid weer. | Lezen |
| `modx_get_access_policy_template` | Haal een template voor toegangsbeleid op via id. | Lezen |
| `modx_create_access_policy_template` | Maak een template voor toegangsbeleid. | Wijzigt de site |
| `modx_update_access_policy_template` | Werk een template voor toegangsbeleid bij. | Wijzigt de site |
| `modx_delete_access_policy_template` | Verwijder een template voor toegangsbeleid via id. | Wijzigt de site |
| `modx_list_access_permissions` | Geef de permissies weer die een beleidstemplate definieert (alleen-lezencatalogus). | Lezen |
| `modx_list_resource_groups` | Geef resourcegroepen weer (id, name). | Lezen |
| `modx_get_resource_group` | Haal een resourcegroep op via id. | Lezen |
| `modx_list_resource_group_resources` | Geef resources weer die aan een resourcegroep zijn toegewezen. | Lezen |
| `modx_create_resource_group` | Maak een resourcegroep. | Wijzigt de site |
| `modx_update_resource_group` | Werk een resourcegroep bij. | Wijzigt de site |
| `modx_delete_resource_group` | Verwijder een resourcegroep via id. | Wijzigt de site |
| `modx_assign_resource_to_group` | Voeg een resource (document) toe aan een resourcegroep. | Wijzigt de site |
| `modx_remove_resource_from_group` | Verwijder een resource (document) uit een resourcegroep. | Wijzigt de site |
| `modx_list_context_access` | Geef ACL-vermeldingen voor contexttoegang weer, eventueel gefilterd op usergroup/context/policy. | Lezen |
| `modx_grant_context_access` | Verleen een gebruikersgroep toegang tot een context met een beleidsregel en een minimale rol-authority. | Wijzigt de site |
| `modx_update_context_access` | Werk een ACL-vermelding voor contexttoegang bij via id. | Wijzigt de site |
| `modx_revoke_context_access` | Verwijder een ACL-vermelding voor contexttoegang via id. | Wijzigt de site |
| `modx_list_resourcegroup_access` | Geef ACL-vermeldingen voor toegang tot resourcegroepen weer. | Lezen |
| `modx_grant_resourcegroup_access` | Verleen een gebruikersgroep toegang tot een resourcegroep met een beleidsregel en een minimale rol-authority. | Wijzigt de site |
| `modx_update_resourcegroup_access` | Werk een ACL-vermelding voor toegang tot een resourcegroep bij via id. | Wijzigt de site |
| `modx_revoke_resourcegroup_access` | Verwijder een ACL-vermelding voor toegang tot een resourcegroep via id. | Wijzigt de site |

## ClientConfig

Beheer instellingen van de uitbreiding ClientConfig.

| Tool | Wat deze doet | Impact |
| --- | --- | --- |
| `modx_clientconfig_list_settings` | Geef ClientConfig-instellingen weer, inclusief contextspecifieke waarden. | Wijzigt de site |
| `modx_clientconfig_get_setting` | Haal een ClientConfig-instelling op via id of key. | Wijzigt de site |
| `modx_clientconfig_create_setting` | Maak een ClientConfig-instelling. | Wijzigt de site |
| `modx_clientconfig_update_setting` | Werk een ClientConfig-instelling bij via id of key. | Wijzigt de site |
| `modx_clientconfig_delete_setting` | Verwijder een ClientConfig-instelling via id of key. | Wijzigt de site |

## Eigenschappensets

Maak herbruikbare MODX-eigenschappensets en koppel deze aan elementen.

| Tool | Wat deze doet | Impact |
| --- | --- | --- |
| `modx_list_property_sets` | Geef eigenschappensets (modPropertySet) weer: id, name, description, category. | Lezen |
| `modx_get_property_set` | Haal een eigenschappenset op via id (inclusief de eigenschappen). | Lezen |
| `modx_create_property_set` | Maak een eigenschappenset. 'properties' is een JSON-object met eigenschapsdefinities. | Wijzigt de site |
| `modx_update_property_set` | Werk een eigenschappenset bij. | Wijzigt de site |
| `modx_delete_property_set` | Verwijder een eigenschappenset via id. | Wijzigt de site |
| `modx_assign_property_set` | Koppel een eigenschappenset aan een element. Identificeer het element met id + ofwel element_class (bijv. 'modSnippet') ofwel element_type ('snippet'/'chunk'/'template'/'plugin'/'tv'). | Wijzigt de site |
| `modx_unassign_property_set` | Ontkoppel een eigenschappenset van een element (dezelfde identificatie als bij het koppelen). | Wijzigt de site |

## Contexten en contextinstellingen

Beheer contextmetadata en contextspecifieke instellingen.

| Tool | Wat deze doet | Impact |
| --- | --- | --- |
| `modx_list_contexts` | Geef contexten (modContext) weer: key, name, description, rank. | Lezen |
| `modx_get_context` | Haal een context op via key. | Lezen |
| `modx_create_context` | Maak een context. 'key' is de unieke identificatie (bijv. 'mobile'). | Wijzigt de site |
| `modx_update_context` | Werk een context bij (name/description/rank). 'settings' kan een JSON-array met contextinstellingen zijn (geavanceerd). | Wijzigt de site |
| `modx_delete_context` | Verwijder een context via key. | Grote impact |
| `modx_list_context_settings` | Geef de instellingen van een context (modContextSetting) weer. | Lezen |
| `modx_get_context_setting` | Haal één contextinstelling op (via context_key + key). | Lezen |
| `modx_create_context_setting` | Maak een instelling op contextniveau (overschrijft de systeeminstelling voor die context). | Wijzigt de site |
| `modx_update_context_setting` | Werk een contextinstelling bij (via context_key + key). | Wijzigt de site |
| `modx_delete_context_setting` | Verwijder een contextinstelling (via context_key + key). | Wijzigt de site |

## Pakketten en providers

Doorzoek pakketproviders en installeer of verwijder MODX-uitbreidingen.

| Tool | Wat deze doet | Impact |
| --- | --- | --- |
| `modx_install_package` | Installeer een transportpakket van een provider (standaard modx.com) op naam, bijv. {package:'MIGX'}. | Grote impact |
| `modx_uninstall_package` | Deïnstalleer een transportpakket via de signature (bijv. 'migx-2.13.0-pl'). | Grote impact |
| `modx_list_providers` | Geef transportproviders weer (modx.com en aangepaste providers, bijv. modstore.pro): id, name, service_url. | Lezen |
| `modx_search_packages` | Doorzoek de catalogus van een provider (gebruik dit vóór install_package). | Lezen |
| `modx_create_provider` | Voeg een transportprovider toe (bijv. modstore.pro). | Wijzigt de site |
| `modx_update_provider` | Werk een transportprovider bij. | Wijzigt de site |
| `modx_delete_provider` | Verwijder een transportprovider via id. | Wijzigt de site |

## Namespaces

Registreer en beheer namespaces en bestandspaden van uitbreidingen.

| Tool | Wat deze doet | Impact |
| --- | --- | --- |
| `modx_list_namespaces` | Geef namespaces (modNamespace) weer: name, path, assets_path. | Lezen |
| `modx_create_namespace` | Maak een namespace. | Wijzigt de site |
| `modx_update_namespace` | Werk een namespace bij (via name). | Wijzigt de site |
| `modx_delete_namespace` | Verwijder een namespace via name ('core' kan niet worden verwijderd). | Wijzigt de site |

## Lexicon en vertalingen

Lees en wijzig lexiconvermeldingen en vertaalonderwerpen.

| Tool | Wat deze doet | Impact |
| --- | --- | --- |
| `modx_list_lexicon_entries` | Geef lexiconvermeldingen weer, gefilterd op namespace + topic + language (en optioneel search). | Lezen |
| `modx_list_lexicon_topics` | Geef lexicononderwerpen voor een namespace/language weer. | Lezen |
| `modx_set_lexicon_entry` | Maak of overschrijf een lexiconvermelding (DB-overschrijving van de bestandswaarde). | Wijzigt de site |
| `modx_revert_lexicon_entry` | Herstel een lexiconvermelding — verwijder de DB-overschrijving zodat de bestandswaarde van toepassing is. | Wijzigt de site |

## Onderhoud en diagnostiek

Onderzoek sitegegevens, status en logboeken en voer onderhoud uit.

| Tool | Wat deze doet | Impact |
| --- | --- | --- |
| `modx_get_site_state` | Lees de siterevisie waarvoor de connector de gezaghebbende bron is en de ondersteuning voor atomaire voorafgaande voorwaarden die externe tools gebruiken. | Lezen |
| `modx_describe_object` | Schema-introspectie: geef de velden van een xPDO-klasse weer (name + php/db-type, null, default) en de primaire sleutel, zodat u ECHTE veldnamen gebruikt in plaats van te gokken. | Lezen |
| `modx_read_error_log` | Lees het laatste deel van het MODX-foutlogboek (core/cache/logs/error.log) voor diagnostiek. | Lezen |
| `modx_refresh_uris` | Genereer alle resource-URI’s opnieuw (voer dit uit na bulkwijzigingen in aliassen/structuur die verouderde URI’s hebben achtergelaten). | Wijzigt de site |
| `modx_remove_locks` | Wis verouderde bewerkingsvergrendelingen in de manager (wanneer een element/resource als vergrendeld wordt gemeld door een niet meer actieve sessie). | Wijzigt de site |
| `modx_project_overview` | Krijg in ÉÉN compacte, weinig kostbare aanroep een overzicht van de volledige geïnstalleerde site. | Lezen |
| `modx_system_info` | Omgevings-/diagnostische informatie: MODX-versie, modxMCP-versie, PHP-versie, db-type, belangrijke paden. | Lezen |
| `modx_regenerate_token` | Vervang modxmcp.api_token door een nieuwe willekeurige waarde en retourneer deze. | Grote impact |
| `modx_help` | Ingebouwde documentatie. Roep dit zonder argumenten aan om onderwerpen weer te geven; geef {topic} mee voor een handleiding (bijv. 'tv_input_types', 'study_component', 'minishop2', 'migx', 'acl', 'getting_started'). Lees het relevante onderwerp vóór onbekend werk, of om te leren hoe u een pas geïnstalleerde component gebruikt. | Lezen |
| `modx_list_actions` | Geef de actienamen weer die deze modxMCP-serverbuild ondersteunt, gegroepeerd per gebied. | Lezen |
| `modx_clear_cache` | Vernieuw de MODX-cache. Geef partitions mee (bijv. ['resource','context_settings']) om alleen die te vernieuwen; laat dit weg voor een volledige vernieuwing. | Wijzigt de site |
| `modx_read_audit_log` | Lees het auditspoor van modxMCP-schrijfacties (nieuwste als laatste). | Lezen |
| `modx_run_processor` | Voer ELKE willekeurige MODX-processor rechtstreeks uit (uitwijkmogelijkheid). | Grote impact |


## De catalogus gebruiken

Een MCP-host ontdekt de beschikbare tools en geeft argumenten door aan de lokale MCP-client. U hoeft de toolnamen niet uit uw hoofd te leren. Zo vraagt u bij het analyseren van een pagina de resource, het gekoppelde template en de gebruikte chunks of snippets op.

Ontbreekt een tool, controleer of de functiegroep is uitgeschakeld, de benodigde uitbreiding is geïnstalleerd en client en server dezelfde versie gebruiken. De actie **modx_list_actions** toont de ondersteunde acties.

## Beveiligingswaarschuwing

Het geheime **X-MCP-Token** geeft toegang via een MODX-servicegebruiker met hoge rechten. Alleen de toolnamen kennen verleent geen toegang. Met een geldig gestolen token kan echter uitvoerbare PHP-code of de configuratie worden gewijzigd. Het uitschakelen van functiegroepen vervangt geen echte serverautorisatie.

Bekijk de [openbare clientdefinities](https://github.com/rumata-estor/modx3-mcp/blob/main/client/index.js) voor de implementatie.
