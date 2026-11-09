# API анықтамалығы — MODX MCP

[English](API.md) · [Русский](API.ru.md) · [Español](API.es.md) · [Deutsch](API.de.md) · [Français](API.fr.md) · [Українська](API.uk.md) · [Қазақша](API.kk.md) · [Nederlands](API.nl.md) · [Polski](API.pl.md)

> Ескерту: бұл аударылған қысқаша шолу. 198 MCP құралының барлық параметрлер кестесі әзірге ағылшын және орыс тілдерінде қолжетімді.
Ашық MCP құралдарының атауы `modx_` префиксінен басталады. PHP HTTP әрекеттерінде бұл префикс қолданылмайды. Параметрлердің толық кестелері мен JSON схемалары ағылшын анықтамалығында берілген.

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

JSON POST сұрауларын HTTPS арқылы `X-MCP-Token` тақырыбымен жіберіңіз. `api.php` MCP JSON-RPC соңғы нүктесі емес; оған `tools/call` хабарларын тікелей жібермеңіз.

**Толық техникалық анықтамалар:** [API параметрлері (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/API.md) · [қауіпсіздік (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/SECURITY.md) · [әзірлеуші құжаттамасы (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/DEVELOPMENT.md). Техникалық кестелердің толық нұсқасы әзірге ағылшын тілінде.


## 198 MCP құралының тізімі

> Сипаттамалар аударылған; міндетті өрістер, түрлер, шектер және тізімдер үшін сілтемедегі ағылшын не орыс техникалық анықтамалығын қараңыз.

[Толық мүмкіндіктер (қазақша)](CAPABILITIES.kk.md) · [English API schemas](API.md) · [Русский API](API.ru.md)

### Элементтер және ресурстар

| Құрал | Аударылған сипаттама | Әсері |
| --- | --- | --- |
| [modx_list_elements](API.md#modx_list_elements) | Белгілі бір түрдегі MODX элементтерін тізімдеу (id + name). | Оқу |
| [modx_list_categories](API.md#modx_list_categories) | Элемент санаттарын тізімдеу. | Оқу |
| [modx_get_category](API.md#modx_get_category) | Санатты және қолданылуын қарау. | Оқу |
| [modx_create_category](API.md#modx_create_category) | Санат жасау. | Значний вплив |
| [modx_update_category](API.md#modx_update_category) | Санатты қайта атау немесе жылжыту. | Значний вплив |
| [modx_delete_category](API.md#modx_delete_category) | Бос санатты тексеріп жою. | Значний вплив |
| [modx_assign_element_category](API.md#modx_assign_element_category) | Элементке санат тағайындау. | Сайтты өзгертеді |
| [modx_make_static](API.md#modx_make_static) | Чанкті/сниппетті/шаблонды/плагинді core/elements/ ішіндегі статикалық файлға түрлендіру (файлды жазады, static=1 + source=Filesystem орнатады). | Әсері жоғары |
| [modx_get_element](API.md#modx_get_element) | MODX элементін оқу (өрістерін + толық кодын қайтарады). | Оқу |
| [modx_update_element_property](API.md#modx_update_element_property) | Күтілетін мәнді CAS арқылы тексеріп, бір қолданыстағы әдепкі қасиетті қауіпсіз өзгерту; кодты немесе басқа қасиеттерді ешқашан алмастырмайды. | Сайтты өзгертеді |
| [modx_update_element](API.md#modx_update_element) | MODX элементінің бүкіл мазмұнын АЛМАСТЫРУ арқылы жаңарту (толық жаңа мазмұнды жіберіңіз). | Әсері жоғары |
| [modx_create_element](API.md#modx_create_element) | Жаңа MODX элементін жасау. | Әсері жоғары |
| [modx_delete_element](API.md#modx_delete_element) | MODX элементін жою. АЛДЫМЕН dry_run:true арқылы іске қосыңыз — ештеңені жоймай, не жойылатынын және элементке қай жерде әлі сілтеме барын (ресурстар үшін: еншілес ресурстар санын) қайтарады. Нақты жою алдында осыны қарап шығыңыз. | Әсері жоғары |
| [modx_view_element](API.md#modx_view_element) | Чанкті/сниппетті/шаблонды/плагинді НӨМІРЛЕНГЕН жолдар түрінде көру (cat -n сияқты), қажет болса start_line/end_line аралығымен шектеу. | Сайтты өзгертеді |
| [modx_edit_element_lines](API.md#modx_edit_element_lines) | Чанкті/сниппетті/шаблонды/плагинді тек өзгерген жолдарды жіберіп, ЖОЛ БОЙЫНША өңдеу (бүкіл элементті қайта жіберудің қажеті жоқ). | Әсері жоғары |
| [modx_bulk_resources](API.md#modx_bulk_resources) | Көп ресурсқа бірден БІР операция қолдану: publish, unpublish, set_template (template қажет), move (parent_to және/немесе context_to қажет) немесе delete. | Әсері жоғары |
| [modx_duplicate_resource](API.md#modx_duplicate_resource) | Ресурстың көшірмесін жасау. id = бастапқы ресурс; міндетті емес name (жаңа pagetitle), duplicate_children (bool), published_mode (preserve\|publish\|unpublish). | Сайтты өзгертеді |
| [modx_duplicate_element](API.md#modx_duplicate_element) | Элементтің көшірмесін жасау (chunk/snippet/template/plugin/tv). type + id; көшірме үшін міндетті емес name. | Сайтты өзгертеді |
| [modx_undelete_resource](API.md#modx_undelete_resource) | Жұмсақ жойылған ресурсты себеттен қалпына келтіру (жойылған белгісін алып тастайды). id = ресурс id. | Сайтты өзгертеді |
| [modx_empty_recycle_bin](API.md#modx_empty_recycle_bin) | Себеттегі (жұмсақ жойылған) БАРЛЫҚ ресурстарды біржола жою. | Әсері жоғары |
| [modx_reorder_resources](API.md#modx_reorder_resources) | menuindex (және қажет болса parent) орнату арқылы ағаштағы ресурстардың ретін өзгерту. items = [{id, menuindex, parent?}], әр ресурсқа жеке қолданылады. | Сайтты өзгертеді |

### Ресурс TV мәндері

| Құрал | Аударылған сипаттама | Әсері |
| --- | --- | --- |
| [modx_get_resource_tvs](API.md#modx_get_resource_tvs) | Ресурстың шаблон айнымалыларының (TV) мәндерін алу. | Оқу |
| [modx_update_resource_tvs](API.md#modx_update_resource_tvs) | Ресурстың шаблон айнымалыларының (TV) мәндерін жаңарту. | Сайтты өзгертеді |
| [modx_list_tv_values](API.md#modx_list_tv_values) | Бір TV үшін барлық ресурстарда айқын сақталған мәндерді, соның ішінде ағымдағы шаблоны сол TV-ді енді пайдаланбауы мүмкін ресурстардағы ескірген мәндерді тізімдеу. | Оқу |
| [modx_clear_tv_values](API.md#modx_clear_tv_values) | Бір TV үшін айқын сақталған барлық мәндерді алдын ала қарау немесе тазалау. | Сайтты өзгертеді |

### TV өрістерінің түрлері

| Құрал | Аударылған сипаттама | Әсері |
| --- | --- | --- |
| [modx_suggest_tv_type](API.md#modx_suggest_tv_type) | Қажеттілікке қай TV енгізу түрі сәйкес келетінін білмейсіз бе? Оны ағылшынша немесе орысша сипаттап, негіздемелері бар реттелген field_types үміткерлерін және ең жақсы нұсқа үшін өңдеуге дайын create_element қаңқасын алыңыз (сол түрге қажет қосымша кілттермен). | Оқу |
| [modx_list_tv_input_types](API.md#modx_list_tv_input_types) | Осы сайтта қолжетімді TV енгізу (виджет) түрлерін тізімдеу. | Оқу |

### Жүйелік баптаулар

| Құрал | Аударылған сипаттама | Әсері |
| --- | --- | --- |
| [modx_list_system_settings](API.md#modx_list_system_settings) | MODX жүйелік баптауларын тізімдеу. | Оқу |
| [modx_get_system_setting](API.md#modx_get_system_setting) | Бір MODX жүйелік баптауын кілті бойынша алу. | Оқу |
| [modx_create_system_setting](API.md#modx_create_system_setting) | MODX жүйелік баптауын жасау. | Сайтты өзгертеді |
| [modx_update_system_setting](API.md#modx_update_system_setting) | MODX жүйелік баптауын кілті бойынша жаңарту. | Әсері жоғары |
| [modx_delete_system_setting](API.md#modx_delete_system_setting) | MODX жүйелік баптауын кілті бойынша жою. | Әсері жоғары |

### Медиа көздері және файлдар

| Құрал | Аударылған сипаттама | Әсері |
| --- | --- | --- |
| [modx_list_media_sources](API.md#modx_list_media_sources) | MODX медиа көздерін тізімдеу. | Оқу |
| [modx_get_media_source](API.md#modx_get_media_source) | Бір MODX медиа көзін алу. | Оқу |
| [modx_create_media_source](API.md#modx_create_media_source) | Медиа (файл) көзін жасау. class_key әдепкі мәні — sources.modFileMediaSource. 'properties' — көзге біріктірілетін параметрлердің {name:value} картасы (мысалы, {"basePath":"assets/files/","baseUrl":"assets/files/"}). | Сайтты өзгертеді |
| [modx_update_media_source](API.md#modx_update_media_source) | Медиа көзін жаңарту. 'properties' — көз параметрлеріне біріктірілетін {name:value} картасы (басқалары сақталады); basePath/baseUrl/т.б. орнату үшін пайдаланыңыз. | Сайтты өзгертеді |
| [modx_delete_media_source](API.md#modx_delete_media_source) | Медиа көзін id бойынша жою. | Сайтты өзгертеді |
| [modx_list_media_source_files](API.md#modx_list_media_source_files) | Медиа көзінің ішіндегі файлдар мен қалталарды тізімдеу. | Оқу |
| [modx_read_media_source_file](API.md#modx_read_media_source_file) | Медиа көзінің ішіндегі файлды оқу. | Оқу |
| [modx_create_media_file](API.md#modx_create_media_file) | Медиа көзінің ішінде файл жасау (мазмұнды жазады). source = көздің id немесе атауы, path = мақсатты каталог (көздің негізгі каталогына қатысты), name = файл атауы, content = файл мазмұны. | Сайтты өзгертеді |
| [modx_update_media_file](API.md#modx_update_media_file) | Медиа көзінің ішіндегі файл мазмұнын қайта жазу. source = id/name, path = көз ішіндегі файл жолы, content = жаңа мазмұн. | Сайтты өзгертеді |
| [modx_delete_media_file](API.md#modx_delete_media_file) | Медиа көзінің ішіндегі файлды жою. source = id/name, path = көз ішіндегі файл жолы. | Әсері жоғары |
| [modx_rename_media_file](API.md#modx_rename_media_file) | Медиа көзінің ішіндегі файлдың атын өзгерту. source = id/name, path = ағымдағы файл жолы, new_name = жаңа файл атауы. | Сайтты өзгертеді |
| [modx_create_media_folder](API.md#modx_create_media_folder) | Медиа көзінің ішінде қалта жасау. source = id/name, parent = ата-аналық каталог (әдепкіде түбір), name = қалта атауы. | Сайтты өзгертеді |
| [modx_delete_media_folder](API.md#modx_delete_media_folder) | Медиа көзінің ішіндегі қалтаны (және оның мазмұнын) жою. source = id/name, path = көз ішіндегі қалта жолы. | Әсері жоғары |

### Компоненттер және файлдар

| Құрал | Аударылған сипаттама | Әсері |
| --- | --- | --- |
| [modx_list_installed_components](API.md#modx_list_installed_components) | core/components және assets/components ішіндегі орнатылған MODX компоненттерін тізімдеу. | Оқу |
| [modx_get_component_files](API.md#modx_get_component_files) | Орнатылған MODX компонентінің ішіндегі файлдар мен қалталарды тізімдеу. | Оқу |
| [modx_read_component_file](API.md#modx_read_component_file) | Орнатылған MODX компонентінен файл оқу. | Оқу |
| [modx_check_integrations](API.md#modx_check_integrations) | Сайтта қай танымал MODX қосымшалары (miniShop2, MIGX, pdoTools, Tickets және т.б.) орнатылғанын және modxMCP әрқайсысымен не істей алатынын хабарлау. | Оқу |

### Код іздеу және тәуелділіктер

| Құрал | Аударылған сипаттама | Әсері |
| --- | --- | --- |
| [modx_search_code](API.md#modx_search_code) | Элементтер мен ресурстардың МАЗМҰНЫ (және атаулары) бойынша толық мәтінді іздеу. | Оқу |
| [modx_find_usages](API.md#modx_find_usages) | Элементтің қайда пайдаланылатынын табу: бүкіл код мазмұнындағы оның атауының сәйкестіктері және, егер ол шаблон болса, сол шаблон тағайындалған ресурстар. | Оқу |
| [modx_list_resources](API.md#modx_list_resources) | Ресурстарды (мазмұн ағашын) тізімдеу; қажет болса parent, context, deleted күйі немесе pagetitle/alias/uri сұрауы бойынша сүзу. | Оқу |
| [modx_replace_across](API.md#modx_replace_across) | Бүкіл сайттың код элементтері (chunk/snippet/template/plugin) бойынша іздеу және алмастыру: МАЗМҰНЫНДА find бар әр элементті тауып, БАРЛЫҚ сәйкестіктерді replacement мәнімен БІР шақыруда алмастыру (сіздің тарапыңыздан әр элементті жеке оқу/жазу қажет емес). | Әсері жоғары |
| [modx_dependency_graph](API.md#modx_dependency_graph) | Сайт элементтерінің ӨЗАРА БАЙЛАНЫСЫН бір шақыруда көрсету: қай шаблон қай чанкті/сниппетті/TV-ді қосады, қай сниппет қай чанкті шығарады, әр TV қайда бекітілген. | Оқу |

### VersionX

| Құрал | Аударылған сипаттама | Әсері |
| --- | --- | --- |
| [modx_versionx_list_versions](API.md#modx_versionx_list_versions) | MODX ресурсының, чанкінің, сниппетінің, шаблонының, плагинінің немесе TV-дің VersionX нұсқаларын тізімдеу. | Оқу |
| [modx_versionx_get_version](API.md#modx_versionx_get_version) | Бір VersionX нұсқасына қайту туралы шешім қабылдар алдында оның деректерін оқу. | Сайтты өзгертеді |
| [modx_versionx_revert_version](API.md#modx_versionx_revert_version) | MODX нысанын нақты VersionX нұсқасына қайтару. | Сайтты өзгертеді |

### VirtualPage

| Құрал | Аударылған сипаттама | Әсері |
| --- | --- | --- |
| [modx_virtualpage_list_events](API.md#modx_virtualpage_list_events) | Маршрут топтарын MODX оқиғаларына байланыстыратын VirtualPage оқиғаларын тізімдеу. | Сайтты өзгертеді |
| [modx_virtualpage_get_event](API.md#modx_virtualpage_get_event) | Бір VirtualPage оқиғасын id немесе атауы бойынша, оның маршруттарымен қоса оқу. | Сайтты өзгертеді |
| [modx_virtualpage_create_event](API.md#modx_virtualpage_create_event) | VirtualPage оқиғасын жасау, әдетте OnPageNotFound немесе OnHandleRequest. | Сайтты өзгертеді |
| [modx_virtualpage_update_event](API.md#modx_virtualpage_update_event) | VirtualPage оқиғасын id немесе атауы бойынша жаңарту. | Сайтты өзгертеді |
| [modx_virtualpage_list_handlers](API.md#modx_virtualpage_list_handlers) | Ресурстарды, сниппеттерді, чанктерді немесе динамикалық ресурстарды шығаратын VirtualPage өңдегіштерін тізімдеу. | Сайтты өзгертеді |
| [modx_virtualpage_get_handler](API.md#modx_virtualpage_get_handler) | Бір VirtualPage өңдегішін id немесе атауы бойынша, оны пайдаланатын маршруттармен қоса оқу. | Сайтты өзгертеді |
| [modx_virtualpage_create_handler](API.md#modx_virtualpage_create_handler) | VirtualPage өңдегішін жасау. type мәні resource, snippet, chunk, dynamic_resource/template немесе 0..3 болуы мүмкін. | Сайтты өзгертеді |
| [modx_virtualpage_update_handler](API.md#modx_virtualpage_update_handler) | VirtualPage өңдегішін id немесе атауы бойынша жаңарту. | Сайтты өзгертеді |
| [modx_virtualpage_list_routes](API.md#modx_virtualpage_list_routes) | VirtualPage маршруттарын олардың оқиға және өңдегіш атауларымен бірге тізімдеу. | Сайтты өзгертеді |
| [modx_virtualpage_get_route](API.md#modx_virtualpage_get_route) | Бір VirtualPage маршрутын id бойынша немесе route және міндетті емес method арқылы оқу. | Сайтты өзгертеді |
| [modx_virtualpage_create_route](API.md#modx_virtualpage_create_route) | VirtualPage маршрутын жасау және қажет болса оның оқиға плагинін байланыстыру. | Сайтты өзгертеді |
| [modx_virtualpage_update_route](API.md#modx_virtualpage_update_route) | VirtualPage маршрутын id бойынша жаңарту. | Сайтты өзгертеді |
| [modx_virtualpage_resolve_route](API.md#modx_virtualpage_resolve_route) | VirtualPage маршрут сәйкестігін модельдеп, өңдегіш пен vp.* орынбасарларын қайтару. | Оқу |
| [modx_virtualpage_delete_event](API.md#modx_virtualpage_delete_event) | VirtualPage оқиғасын id немесе атауы бойынша жою (VP схемасына сәйкес оның маршруттарын да жояды). | Сайтты өзгертеді |
| [modx_virtualpage_delete_handler](API.md#modx_virtualpage_delete_handler) | VirtualPage өңдегішін id немесе атауы бойынша жою. | Сайтты өзгертеді |
| [modx_virtualpage_delete_route](API.md#modx_virtualpage_delete_route) | VirtualPage маршрутын id немесе атауы бойынша жою. | Сайтты өзгертеді |
| [modx_virtualpage_clear_cache](API.md#modx_virtualpage_clear_cache) | VirtualPage маршрут/кэш файлдарын тазалап, MODX кэшін жаңарту. | Сайтты өзгертеді |

### miniShop2

| Құрал | Аударылған сипаттама | Әсері |
| --- | --- | --- |
| [modx_ms2_list_option_types](API.md#modx_ms2_list_option_types) | miniShop2 тауар опциялары өрістерінің қолжетімді түрлерін тізімдеу. | Сайтты өзгертеді |
| [modx_ms2_list_options](API.md#modx_ms2_list_options) | Баптаулардың опциялар қойындысынан miniShop2 тауар опцияларын тізімдеу. | Сайтты өзгертеді |
| [modx_ms2_get_option](API.md#modx_ms2_get_option) | Бір miniShop2 тауар опциясын id бойынша оқу. | Сайтты өзгертеді |
| [modx_ms2_create_option](API.md#modx_ms2_create_option) | Баптаулардың опциялар бөлімінде miniShop2 тауар опциясын жасау. | Сайтты өзгертеді |
| [modx_ms2_update_option](API.md#modx_ms2_update_option) | Баптаулардың опциялар бөлімінде miniShop2 тауар опциясын жаңарту. | Сайтты өзгертеді |
| [modx_ms2_assign_option_to_category](API.md#modx_ms2_assign_option_to_category) | Қолданыстағы miniShop2 опциясын msCategory санатына тағайындау. | Сайтты өзгертеді |
| [modx_ms2_get_product_options](API.md#modx_ms2_get_product_options) | Тауарға тағайындалған miniShop2 опция мәндерін оқу. | Сайтты өзгертеді |
| [modx_ms2_update_product_options](API.md#modx_ms2_update_product_options) | Тауардың miniShop2 опция мәндерін жасау, жаңарту немесе жою. | Сайтты өзгертеді |
| [modx_ms2_list_link_types](API.md#modx_ms2_list_link_types) | miniShop2 байланыс түрлерін (msLink) тізімдеу: id, type, name, description. | Сайтты өзгертеді |
| [modx_ms2_get_link_type](API.md#modx_ms2_get_link_type) | miniShop2 байланыс түрін id бойынша алу. | Сайтты өзгертеді |
| [modx_ms2_create_link_type](API.md#modx_ms2_create_link_type) | miniShop2 байланыс түрін жасау. 'type' — тауарлардың қалай байланысатынын анықтайтын қатынас түрі. | Сайтты өзгертеді |
| [modx_ms2_update_link_type](API.md#modx_ms2_update_link_type) | miniShop2 байланыс түрін жаңарту. | Сайтты өзгертеді |
| [modx_ms2_delete_link_type](API.md#modx_ms2_delete_link_type) | miniShop2 байланыс түрін id бойынша жою. | Сайтты өзгертеді |
| [modx_ms2_list_product_links](API.md#modx_ms2_list_product_links) | Тауарлар арасындағы байланыстарды (msProductLink) тізімдеу, қажет болса бір тауар үшін. | Сайтты өзгертеді |
| [modx_ms2_create_product_link](API.md#modx_ms2_create_product_link) | Екі тауарды байланыс түрі арқылы байланыстыру. Байланыс түрінің қатынасы кері байланыстың да жасалатынын анықтайды. | Сайтты өзгертеді |
| [modx_ms2_delete_product_link](API.md#modx_ms2_delete_product_link) | Тауар байланысын жою (байланыс түрі + master + slave бойынша). | Сайтты өзгертеді |
| [modx_ms2_list_categories](API.md#modx_ms2_list_categories) | miniShop2 санаттарын (msCategory) тізімдеу. | Сайтты өзгертеді |
| [modx_ms2_create_category](API.md#modx_ms2_create_category) | miniShop2 санатын (msCategory ресурсын) жасау. | Сайтты өзгертеді |
| [modx_ms2_update_category](API.md#modx_ms2_update_category) | miniShop2 санатын жаңарту. | Сайтты өзгертеді |
| [modx_ms2_list_orders](API.md#modx_ms2_list_orders) | miniShop2 тапсырыстарын (msOrder) тізімдеу; күйі/клиенті/контексті/күндер аралығы бойынша сүзуге болады. | Сайтты өзгертеді |
| [modx_ms2_get_order](API.md#modx_ms2_get_order) | miniShop2 тапсырысын id бойынша алу (тауар позицияларымен/клиентімен бірге). | Сайтты өзгертеді |
| [modx_ms2_update_order](API.md#modx_ms2_update_order) | miniShop2 тапсырысын жаңарту — әдетте күйін (ms2 күй өзгерту логикасын іске қосады), жеткізуін немесе төлемін өзгерту үшін. | Сайтты өзгертеді |

### MIGX

| Құрал | Аударылған сипаттама | Әсері |
| --- | --- | --- |
| [modx_migx_list_configs](API.md#modx_migx_list_configs) | MIGX конфигурацияларын (migxConfig) тізімдеу: id, name, category, published. | Сайтты өзгертеді |
| [modx_migx_get_config](API.md#modx_migx_get_config) | Толық MIGX конфигурациясын id бойынша алу (formtabs, columns, buttons, filters, permissions — сақталған JSON жолдары түрінде). | Сайтты өзгертеді |
| [modx_migx_create_config](API.md#modx_migx_create_config) | MIGX конфигурациясын жасау. formtabs/columns/contextmenus/actionbuttons/columnbuttons/filters — JSON жолдары (MIGX басқару панелінде сақтайтын пішіммен бірдей). | Сайтты өзгертеді |
| [modx_migx_update_config](API.md#modx_migx_update_config) | MIGX конфигурациясын жаңарту. Тек жіберілген өрістер өзгереді. | Сайтты өзгертеді |
| [modx_migx_delete_config](API.md#modx_migx_delete_config) | MIGX конфигурациясын id бойынша жою. | Сайтты өзгертеді |

### Пайдаланушылар және қолжетімділік құқықтары

| Құрал | Аударылған сипаттама | Әсері |
| --- | --- | --- |
| [modx_flush_permissions](API.md#modx_flush_permissions) | ACL өзгерістері қолданылуы үшін кэштелген қолжетімділік рұқсаттарын тазалау (басқару панеліндегі 'Flush Permissions'). | Сайтты өзгертеді |
| [modx_list_users](API.md#modx_list_users) | Басқару панелі/веб пайдаланушыларын (modUser) тізімдеу. | Оқу |
| [modx_get_user](API.md#modx_get_user) | Пайдаланушыны id бойынша алу. | Оқу |
| [modx_create_user](API.md#modx_create_user) | Пайдаланушы жасау. Құпиясөзді айқын орнату үшін 'password' жіберіңіз; автоматты жасау үшін жібермеңіз. Профиль өрістері (email, fullname, phone…) де бірге қабылданады. | Сайтты өзгертеді |
| [modx_update_user](API.md#modx_update_user) | Пайдаланушыны жаңарту (профиль өрістері, белсенділік күйі). | Сайтты өзгертеді |
| [modx_delete_user](API.md#modx_delete_user) | Пайдаланушыны id бойынша жою. | Сайтты өзгертеді |
| [modx_list_user_groups](API.md#modx_list_user_groups) | MODX пайдаланушы топтарын тізімдеу (id, name, parent, rank). | Оқу |
| [modx_get_user_group](API.md#modx_get_user_group) | Бір пайдаланушы тобын id бойынша алу. | Оқу |
| [modx_create_user_group](API.md#modx_create_user_group) | Пайдаланушы тобын жасау. | Сайтты өзгертеді |
| [modx_update_user_group](API.md#modx_update_user_group) | Пайдаланушы тобын жаңарту. | Сайтты өзгертеді |
| [modx_delete_user_group](API.md#modx_delete_user_group) | Пайдаланушы тобын id бойынша жою. | Сайтты өзгертеді |
| [modx_list_user_group_members](API.md#modx_list_user_group_members) | Пайдаланушы тобының мүшелерін тізімдеу. | Оқу |
| [modx_add_user_to_group](API.md#modx_add_user_to_group) | Пайдаланушыны топқа қосу, қажет болса рөлмен. | Сайтты өзгертеді |
| [modx_update_group_member](API.md#modx_update_group_member) | Пайдаланушының топтағы мүшелігін жаңарту (мысалы, рөлін өзгерту). | Сайтты өзгертеді |
| [modx_remove_user_from_group](API.md#modx_remove_user_from_group) | Пайдаланушыны топтан шығару. | Сайтты өзгертеді |
| [modx_list_roles](API.md#modx_list_roles) | Қолжетімділік рөлдерін тізімдеу (id, name, authority). | Оқу |
| [modx_get_role](API.md#modx_get_role) | Рөлді id бойынша алу. | Оқу |
| [modx_create_role](API.md#modx_create_role) | Рөл жасау. | Сайтты өзгертеді |
| [modx_update_role](API.md#modx_update_role) | Рөлді жаңарту. | Сайтты өзгертеді |
| [modx_delete_role](API.md#modx_delete_role) | Рөлді id бойынша жою. | Сайтты өзгертеді |
| [modx_list_access_policies](API.md#modx_list_access_policies) | Қолжетімділік саясаттарын тізімдеу (id, name, description, template). | Оқу |
| [modx_get_access_policy](API.md#modx_get_access_policy) | Қолжетімділік саясатын id бойынша алу. | Оқу |
| [modx_create_access_policy](API.md#modx_create_access_policy) | Қолжетімділік саясатын жасау. | Сайтты өзгертеді |
| [modx_update_access_policy](API.md#modx_update_access_policy) | Қолжетімділік саясатын жаңарту (мысалы, оның рұқсаттар картасын). | Әсері жоғары |
| [modx_delete_access_policy](API.md#modx_delete_access_policy) | Қолжетімділік саясатын id бойынша жою. | Сайтты өзгертеді |
| [modx_list_access_policy_templates](API.md#modx_list_access_policy_templates) | Қолжетімділік саясатының шаблондарын тізімдеу. | Оқу |
| [modx_get_access_policy_template](API.md#modx_get_access_policy_template) | Қолжетімділік саясатының шаблонын id бойынша алу. | Оқу |
| [modx_create_access_policy_template](API.md#modx_create_access_policy_template) | Қолжетімділік саясатының шаблонын жасау. | Сайтты өзгертеді |
| [modx_update_access_policy_template](API.md#modx_update_access_policy_template) | Қолжетімділік саясатының шаблонын жаңарту. | Сайтты өзгертеді |
| [modx_delete_access_policy_template](API.md#modx_delete_access_policy_template) | Қолжетімділік саясатының шаблонын id бойынша жою. | Сайтты өзгертеді |
| [modx_list_access_permissions](API.md#modx_list_access_permissions) | Саясат шаблоны анықтайтын рұқсаттарды тізімдеу (тек оқуға арналған каталог). | Оқу |
| [modx_list_resource_groups](API.md#modx_list_resource_groups) | Ресурс топтарын тізімдеу (id, name). | Оқу |
| [modx_get_resource_group](API.md#modx_get_resource_group) | Ресурс тобын id бойынша алу. | Оқу |
| [modx_list_resource_group_resources](API.md#modx_list_resource_group_resources) | Ресурс тобына тағайындалған ресурстарды тізімдеу. | Оқу |
| [modx_create_resource_group](API.md#modx_create_resource_group) | Ресурс тобын жасау. | Сайтты өзгертеді |
| [modx_update_resource_group](API.md#modx_update_resource_group) | Ресурс тобын жаңарту. | Сайтты өзгертеді |
| [modx_delete_resource_group](API.md#modx_delete_resource_group) | Ресурс тобын id бойынша жою. | Сайтты өзгертеді |
| [modx_assign_resource_to_group](API.md#modx_assign_resource_to_group) | Ресурсты (құжатты) ресурс тобына қосу. | Сайтты өзгертеді |
| [modx_remove_resource_from_group](API.md#modx_remove_resource_from_group) | Ресурсты (құжатты) ресурс тобынан шығару. | Сайтты өзгертеді |
| [modx_list_context_access](API.md#modx_list_context_access) | Контекстке қолжетімділіктің ACL жазбаларын тізімдеу, қажет болса usergroup/context/policy бойынша сүзу. | Оқу |
| [modx_grant_context_access](API.md#modx_grant_context_access) | Пайдаланушы тобына саясатпен және рөлдің ең төменгі authority деңгейімен контекстке қолжетімділік беру. | Сайтты өзгертеді |
| [modx_update_context_access](API.md#modx_update_context_access) | Контекстке қолжетімділіктің ACL жазбасын id бойынша жаңарту. | Сайтты өзгертеді |
| [modx_revoke_context_access](API.md#modx_revoke_context_access) | Контекстке қолжетімділіктің ACL жазбасын id бойынша жою. | Сайтты өзгертеді |
| [modx_list_resourcegroup_access](API.md#modx_list_resourcegroup_access) | Ресурс тобына қолжетімділіктің ACL жазбаларын тізімдеу. | Оқу |
| [modx_grant_resourcegroup_access](API.md#modx_grant_resourcegroup_access) | Пайдаланушы тобына саясатпен және рөлдің ең төменгі authority деңгейімен ресурс тобына қолжетімділік беру. | Сайтты өзгертеді |
| [modx_update_resourcegroup_access](API.md#modx_update_resourcegroup_access) | Ресурс тобына қолжетімділіктің ACL жазбасын id бойынша жаңарту. | Сайтты өзгертеді |
| [modx_revoke_resourcegroup_access](API.md#modx_revoke_resourcegroup_access) | Ресурс тобына қолжетімділіктің ACL жазбасын id бойынша жою. | Сайтты өзгертеді |

### ClientConfig

| Құрал | Аударылған сипаттама | Әсері |
| --- | --- | --- |
| [modx_clientconfig_list_settings](API.md#modx_clientconfig_list_settings) | ClientConfig баптауларын, соның ішінде контекстке тән мәндерді тізімдеу. | Сайтты өзгертеді |
| [modx_clientconfig_get_setting](API.md#modx_clientconfig_get_setting) | ClientConfig баптауын id немесе кілті бойынша алу. | Сайтты өзгертеді |
| [modx_clientconfig_create_setting](API.md#modx_clientconfig_create_setting) | ClientConfig баптауын жасау. | Сайтты өзгертеді |
| [modx_clientconfig_update_setting](API.md#modx_clientconfig_update_setting) | ClientConfig баптауын id немесе кілті бойынша жаңарту. | Сайтты өзгертеді |
| [modx_clientconfig_delete_setting](API.md#modx_clientconfig_delete_setting) | ClientConfig баптауын id немесе кілті бойынша жою. | Сайтты өзгертеді |

### Қасиеттер жиындары

| Құрал | Аударылған сипаттама | Әсері |
| --- | --- | --- |
| [modx_list_property_sets](API.md#modx_list_property_sets) | Қасиеттер жиындарын (modPropertySet) тізімдеу: id, name, description, category. | Оқу |
| [modx_get_property_set](API.md#modx_get_property_set) | Қасиеттер жиынын id бойынша алу (қасиеттерімен бірге). | Оқу |
| [modx_create_property_set](API.md#modx_create_property_set) | Қасиеттер жиынын жасау. 'properties' — қасиет анықтамалары бар JSON нысаны. | Сайтты өзгертеді |
| [modx_update_property_set](API.md#modx_update_property_set) | Қасиеттер жиынын жаңарту. | Сайтты өзгертеді |
| [modx_delete_property_set](API.md#modx_delete_property_set) | Қасиеттер жиынын id бойынша жою. | Сайтты өзгертеді |
| [modx_assign_property_set](API.md#modx_assign_property_set) | Қасиеттер жиынын элементке бекіту. Элементті оның id + element_class (мысалы, 'modSnippet') немесе element_type ('snippet'/'chunk'/'template'/'plugin'/'tv') арқылы анықтаңыз. | Сайтты өзгертеді |
| [modx_unassign_property_set](API.md#modx_unassign_property_set) | Қасиеттер жиынын элементтен ажырату (анықтау тәсілі бекітудегідей). | Сайтты өзгертеді |

### Контекстер және контекст баптаулары

| Құрал | Аударылған сипаттама | Әсері |
| --- | --- | --- |
| [modx_list_contexts](API.md#modx_list_contexts) | Контекстерді (modContext) тізімдеу: key, name, description, rank. | Оқу |
| [modx_get_context](API.md#modx_get_context) | Контекстті кілті бойынша алу. | Оқу |
| [modx_create_context](API.md#modx_create_context) | Контекст жасау. 'key' — бірегей сәйкестендіргіш (мысалы, 'mobile'). | Сайтты өзгертеді |
| [modx_update_context](API.md#modx_update_context) | Контекстті жаңарту (name/description/rank). 'settings' контекст баптауларының JSON массиві болуы мүмкін (кеңейтілген қолдану). | Сайтты өзгертеді |
| [modx_delete_context](API.md#modx_delete_context) | Контекстті кілті бойынша жою. | Әсері жоғары |
| [modx_list_context_settings](API.md#modx_list_context_settings) | Контекст баптауларын (modContextSetting) тізімдеу. | Оқу |
| [modx_get_context_setting](API.md#modx_get_context_setting) | Бір контекст баптауын алу (context_key + key бойынша). | Оқу |
| [modx_create_context_setting](API.md#modx_create_context_setting) | Контекст деңгейіндегі баптау жасау (сол контекст үшін жүйелік баптауды алмастырады). | Сайтты өзгертеді |
| [modx_update_context_setting](API.md#modx_update_context_setting) | Контекст баптауын жаңарту (context_key + key бойынша). | Сайтты өзгертеді |
| [modx_delete_context_setting](API.md#modx_delete_context_setting) | Контекст баптауын жою (context_key + key бойынша). | Сайтты өзгертеді |

### Пакеттер және провайдерлер

| Құрал | Аударылған сипаттама | Әсері |
| --- | --- | --- |
| [modx_install_package](API.md#modx_install_package) | Провайдерден (әдепкіде modx.com) тасымалдау пакетін атауы бойынша орнату, мысалы {package:'MIGX'}. | Әсері жоғары |
| [modx_uninstall_package](API.md#modx_uninstall_package) | Тасымалдау пакетін сигнатурасы бойынша жою (мысалы, 'migx-2.13.0-pl'). | Әсері жоғары |
| [modx_list_providers](API.md#modx_list_providers) | Тасымалдау провайдерлерін (modx.com және арнайы, мысалы modstore.pro) тізімдеу: id, name, service_url. | Оқу |
| [modx_search_packages](API.md#modx_search_packages) | Провайдер каталогынан іздеу (install_package алдында пайдаланыңыз). | Оқу |
| [modx_create_provider](API.md#modx_create_provider) | Тасымалдау провайдерін қосу (мысалы, modstore.pro). | Сайтты өзгертеді |
| [modx_update_provider](API.md#modx_update_provider) | Тасымалдау провайдерін жаңарту. | Сайтты өзгертеді |
| [modx_delete_provider](API.md#modx_delete_provider) | Тасымалдау провайдерін id бойынша жою. | Сайтты өзгертеді |

### Атаулар кеңістіктері

| Құрал | Аударылған сипаттама | Әсері |
| --- | --- | --- |
| [modx_list_namespaces](API.md#modx_list_namespaces) | Атаулар кеңістіктерін (modNamespace) тізімдеу: name, path, assets_path. | Оқу |
| [modx_create_namespace](API.md#modx_create_namespace) | Атаулар кеңістігін жасау. | Сайтты өзгертеді |
| [modx_update_namespace](API.md#modx_update_namespace) | Атаулар кеңістігін жаңарту (атауы бойынша). | Сайтты өзгертеді |
| [modx_delete_namespace](API.md#modx_delete_namespace) | Атаулар кеңістігін атауы бойынша жою ('core' жойылмайды). | Сайтты өзгертеді |

### Лексикон және аудармалар

| Құрал | Аударылған сипаттама | Әсері |
| --- | --- | --- |
| [modx_list_lexicon_entries](API.md#modx_list_lexicon_entries) | Лексикон жазбаларын namespace + topic + language бойынша сүзіп тізімдеу (қажет болса іздеумен). | Оқу |
| [modx_list_lexicon_topics](API.md#modx_list_lexicon_topics) | Атаулар кеңістігінің/тілдің лексикон тақырыптарын тізімдеу. | Оқу |
| [modx_set_lexicon_entry](API.md#modx_set_lexicon_entry) | Лексикон жазбасын жасау немесе қайта анықтау (файл мәнін дерекқорда алмастыру). | Сайтты өзгертеді |
| [modx_revert_lexicon_entry](API.md#modx_revert_lexicon_entry) | Лексикон жазбасын бастапқы күйіне қайтару — файл мәні қолданылуы үшін дерекқордағы қайта анықтамасын жою. | Сайтты өзгертеді |

### Қызмет көрсету және диагностика

| Құрал | Аударылған сипаттама | Әсері |
| --- | --- | --- |
| [modx_get_site_state](API.md#modx_get_site_state) | Сыртқы құралдар пайдаланатын, Connector беделді дереккөзі болып саналатын сайт ревизиясын және атомарлық алғышарттар мүмкіндігін оқу. | Оқу |
| [modx_describe_object](API.md#modx_describe_object) | Схеманы зерттеу: жорамалдамай, НАҚТЫ өріс атауларын пайдалану үшін xPDO класының өрістерін (атауы + php/db түрі, null, default) және бастапқы кілтін тізімдеу. | Оқу |
| [modx_read_error_log](API.md#modx_read_error_log) | Диагностика үшін MODX қате журналының (core/cache/logs/error.log) соңын оқу. | Оқу |
| [modx_refresh_uris](API.md#modx_refresh_uris) | Барлық ресурс URI мекенжайларын қайта жасау (ескірген URI қалдырған alias/құрылым жаппай өзгерістерінен кейін іске қосыңыз). | Сайтты өзгертеді |
| [modx_remove_locks](API.md#modx_remove_locks) | Басқару панеліндегі ескірген өңдеу бұғаттауларын тазалау (элемент/ресурс аяқталған сессиямен бұғатталған деп көрсетілгенде). | Сайтты өзгертеді |
| [modx_project_overview](API.md#modx_project_overview) | Бүкіл орнатылған сайт туралы БІР ықшам, шығыны аз шақырумен түсінік алу. | Оқу |
| [modx_system_info](API.md#modx_system_info) | Орта/диагностика ақпараты: MODX нұсқасы, modxMCP нұсқасы, PHP нұсқасы, дерекқор түрі, негізгі жолдар. | Оқу |
| [modx_regenerate_token](API.md#modx_regenerate_token) | modxmcp.api_token мәнін жаңа кездейсоқ мәнге ауыстырып, оны қайтару. | Әсері жоғары |
| [modx_help](API.md#modx_help) | Кіріктірілген құжаттама. Тақырыптар тізімі үшін аргументсіз шақырыңыз; нұсқаулық алу үшін {topic} жіберіңіз (мысалы, 'tv_input_types', 'study_component', 'minishop2', 'migx', 'acl', 'getting_started'). Бейтаныс жұмыс алдында немесе жаңа орнатылған компонентті пайдалануды үйрену үшін тиісті тақырыпты оқыңыз. | Оқу |
| [modx_list_actions](API.md#modx_list_actions) | Осы modxMCP сервер құрастырылымы қолдайтын әрекет атауларын салалар бойынша топтап тізімдеу. | Оқу |
| [modx_clear_cache](API.md#modx_clear_cache) | MODX кэшін жаңарту. Тек белгілі бөлімдерді жаңарту үшін partitions жіберіңіз (мысалы, ['resource','context_settings']); толық жаңарту үшін жібермеңіз. | Сайтты өзгертеді |
| [modx_read_audit_log](API.md#modx_read_audit_log) | modxMCP жазу операцияларының аудит журналын оқу (ең жаңасы соңында). | Оқу |
| [modx_run_processor](API.md#modx_run_processor) | КЕЗ КЕЛГЕН MODX процессорын тікелей іске қосу (жалпы құралдардан тыс тікелей шақыру жолы). | Әсері жоғары |
