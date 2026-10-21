# Довідник API — MODX MCP

[English](API.md) · [Русский](API.ru.md) · [Español](API.es.md) · [Deutsch](API.de.md) · [Français](API.fr.md) · [Українська](API.uk.md) · [Қазақша](API.kk.md) · [Nederlands](API.nl.md) · [Polski](API.pl.md)

> Примітка: це перекладений стислий огляд. Повні таблиці параметрів усіх 192 MCP-інструментів наразі доступні англійською та російською мовами.
Публічні MCP-інструменти мають префікс `modx_`, а HTTP-дії PHP використовують назви без цього префікса. Повні таблиці параметрів та JSON-схеми доступні в англомовному довіднику.

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

Надсилайте JSON POST із заголовком `X-MCP-Token` через HTTPS. `api.php` — не MCP JSON-RPC endpoint; не надсилайте туди `tools/call` напряму.

**Повні технічні довідники:** [параметри API (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/API.md) · [безпека (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/SECURITY.md) · [розробка (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/DEVELOPMENT.md). Детальні технічні таблиці поки доступні англійською.


## Каталог 192 інструментів MCP

> Опис перекладено; обов’язкові поля, типи, обмеження та перелічення перевіряйте в англомовному або російськомовному технічному довіднику за посиланням.

[Докладні можливості українською](CAPABILITIES.uk.md) · [English API schemas](API.md) · [Русский API](API.ru.md)

### Елементи та ресурси

| Інструмент | Перекладений опис | Вплив |
| --- | --- | --- |
| [modx_list_elements](API.md#modx_list_elements) | Перелічує елементи MODX заданого типу (id + name). | Читання |
| [modx_make_static](API.md#modx_make_static) | Перетворює чанк/сніпет/шаблон/плагін на статичний файл у core/elements/ (записує файл, установлює static=1 + source=Filesystem). | Значний вплив |
| [modx_get_element](API.md#modx_get_element) | Читає елемент MODX (повертає його поля + повний код). | Читання |
| [modx_update_element_property](API.md#modx_update_element_property) | Безпечно змінює одну наявну властивість за замовчуванням із CAS-перевіркою очікуваного значення; ніколи не замінює код або інші властивості. | Змінює сайт |
| [modx_update_element](API.md#modx_update_element) | Оновлює елемент MODX, ЗАМІНЮЮЧИ весь його вміст (надішліть повний новий вміст). | Значний вплив |
| [modx_create_element](API.md#modx_create_element) | Створює новий елемент MODX. | Значний вплив |
| [modx_delete_element](API.md#modx_delete_element) | Видаляє елемент MODX. СПОЧАТКУ запустіть із dry_run:true — без видалення повертає перелік того, що буде видалено, та місця, де ще є посилання на елемент (для ресурсів: кількість дочірніх ресурсів). Перегляньте результат перед фактичним видаленням. | Значний вплив |
| [modx_view_element](API.md#modx_view_element) | Показує чанк/сніпет/шаблон/плагін як ПРОНУМЕРОВАНІ рядки (як cat -n), за потреби обмежуючи діапазон через start_line/end_line. | Змінює сайт |
| [modx_edit_element_lines](API.md#modx_edit_element_lines) | Редагує чанк/сніпет/шаблон/плагін ПО РЯДКАХ, передаючи лише змінені рядки (не потрібно повторно надсилати весь елемент). | Значний вплив |
| [modx_bulk_resources](API.md#modx_bulk_resources) | Застосовує ОДНУ операцію до багатьох ресурсів одночасно: publish, unpublish, set_template (потрібен template), move (потрібні parent_to та/або context_to) або delete. | Значний вплив |
| [modx_duplicate_resource](API.md#modx_duplicate_resource) | Дублює ресурс. id = вихідний ресурс; необов’язкові name (новий pagetitle), duplicate_children (bool), published_mode (preserve\|publish\|unpublish). | Змінює сайт |
| [modx_duplicate_element](API.md#modx_duplicate_element) | Дублює елемент (chunk/snippet/template/plugin/tv). type + id; необов’язкове name для копії. | Змінює сайт |
| [modx_undelete_resource](API.md#modx_undelete_resource) | Відновлює логічно видалений ресурс із кошика (скасовує видалення). id = ідентифікатор ресурсу. | Змінює сайт |
| [modx_empty_recycle_bin](API.md#modx_empty_recycle_bin) | Остаточно видаляє ВСІ ресурси в кошику (логічно видалені). | Значний вплив |
| [modx_reorder_resources](API.md#modx_reorder_resources) | Змінює порядок ресурсів у дереві, задаючи menuindex (і за потреби parent). items = [{id, menuindex, parent?}], застосовується до кожного ресурсу. | Змінює сайт |

### TV ресурсів

| Інструмент | Перекладений опис | Вплив |
| --- | --- | --- |
| [modx_get_resource_tvs](API.md#modx_get_resource_tvs) | Отримує значення змінних шаблону (TV) ресурсу. | Читання |
| [modx_update_resource_tvs](API.md#modx_update_resource_tvs) | Оновлює значення змінних шаблону (TV) ресурсу. | Змінює сайт |
| [modx_list_tv_values](API.md#modx_list_tv_values) | Перелічує явно збережені значення одного TV на всіх ресурсах, включно із застарілими значеннями на ресурсах, поточний шаблон яких може більше не використовувати цей TV. | Читання |
| [modx_clear_tv_values](API.md#modx_clear_tv_values) | Попередньо показує або очищає всі явно збережені значення одного TV. | Змінює сайт |

### Типи полів TV

| Інструмент | Перекладений опис | Вплив |
| --- | --- | --- |
| [modx_suggest_tv_type](API.md#modx_suggest_tv_type) | Не впевнені, який тип введення TV відповідає потребі? Опишіть її (англійською або російською) та отримайте ранжовані варіанти field_types з обґрунтуваннями + готову до редагування заготовку create_element для найкращого варіанта (із додатковими ключами, потрібними цьому типу). | Читання |
| [modx_list_tv_input_types](API.md#modx_list_tv_input_types) | Перелічує типи введення (віджетів) TV, доступні на цьому сайті. | Читання |

### Системні налаштування

| Інструмент | Перекладений опис | Вплив |
| --- | --- | --- |
| [modx_list_system_settings](API.md#modx_list_system_settings) | Перелічує системні налаштування MODX. | Читання |
| [modx_get_system_setting](API.md#modx_get_system_setting) | Отримує одне системне налаштування MODX за ключем. | Читання |
| [modx_create_system_setting](API.md#modx_create_system_setting) | Створює системне налаштування MODX. | Змінює сайт |
| [modx_update_system_setting](API.md#modx_update_system_setting) | Оновлює системне налаштування MODX за ключем. | Значний вплив |
| [modx_delete_system_setting](API.md#modx_delete_system_setting) | Видаляє системне налаштування MODX за ключем. | Значний вплив |

### Медіаджерела та файли

| Інструмент | Перекладений опис | Вплив |
| --- | --- | --- |
| [modx_list_media_sources](API.md#modx_list_media_sources) | Перелічує медіаджерела MODX. | Читання |
| [modx_get_media_source](API.md#modx_get_media_source) | Отримує одне медіаджерело MODX. | Читання |
| [modx_create_media_source](API.md#modx_create_media_source) | Створює медіаджерело (джерело файлів). class_key за замовчуванням — sources.modFileMediaSource. 'properties' — мапа {name:value} параметрів джерела (наприклад, {"basePath":"assets/files/","baseUrl":"assets/files/"}), що об’єднується з параметрами джерела. | Змінює сайт |
| [modx_update_media_source](API.md#modx_update_media_source) | Оновлює медіаджерело. 'properties' — мапа {name:value}, що об’єднується з параметрами джерела (інші зберігаються); використовуйте її для встановлення basePath/baseUrl/тощо. | Змінює сайт |
| [modx_delete_media_source](API.md#modx_delete_media_source) | Видаляє медіаджерело за id. | Змінює сайт |
| [modx_list_media_source_files](API.md#modx_list_media_source_files) | Перелічує файли й папки всередині медіаджерела. | Читання |
| [modx_read_media_source_file](API.md#modx_read_media_source_file) | Читає файл усередині медіаджерела. | Читання |
| [modx_create_media_file](API.md#modx_create_media_file) | Створює файл усередині медіаджерела (записує вміст). source = id або назва джерела, path = цільовий каталог (відносно базового каталогу джерела), name = назва файлу, content = тіло файлу. | Змінює сайт |
| [modx_update_media_file](API.md#modx_update_media_file) | Перезаписує вміст файлу всередині медіаджерела. source = id/назва, path = шлях файлу в межах джерела, content = нове тіло. | Змінює сайт |
| [modx_delete_media_file](API.md#modx_delete_media_file) | Видаляє файл усередині медіаджерела. source = id/назва, path = шлях файлу в межах джерела. | Значний вплив |
| [modx_rename_media_file](API.md#modx_rename_media_file) | Перейменовує файл усередині медіаджерела. source = id/назва, path = поточний шлях файлу, new_name = нова назва файлу. | Змінює сайт |
| [modx_create_media_folder](API.md#modx_create_media_folder) | Створює папку всередині медіаджерела. source = id/назва, parent = батьківський каталог (за замовчуванням кореневий), name = назва папки. | Змінює сайт |
| [modx_delete_media_folder](API.md#modx_delete_media_folder) | Видаляє папку (та її вміст) усередині медіаджерела. source = id/назва, path = шлях папки в межах джерела. | Значний вплив |

### Компоненти та файли

| Інструмент | Перекладений опис | Вплив |
| --- | --- | --- |
| [modx_list_installed_components](API.md#modx_list_installed_components) | Перелічує встановлені компоненти MODX із core/components і assets/components. | Читання |
| [modx_get_component_files](API.md#modx_get_component_files) | Перелічує файли й папки всередині встановленого компонента MODX. | Читання |
| [modx_read_component_file](API.md#modx_read_component_file) | Читає файл установленого компонента MODX. | Читання |
| [modx_check_integrations](API.md#modx_check_integrations) | Повідомляє, які популярні доповнення MODX (miniShop2, MIGX, pdoTools, Tickets тощо) встановлено на цьому сайті та що modxMCP може робити з кожним із них. | Читання |

### Пошук коду та залежності

| Інструмент | Перекладений опис | Вплив |
| --- | --- | --- |
| [modx_search_code](API.md#modx_search_code) | Повнотекстовий пошук у ВМІСТІ елементів і ресурсів (та їхніх назвах). | Читання |
| [modx_find_usages](API.md#modx_find_usages) | Знаходить місця використання елемента: збіги його назви у вмісті всього коду, а для шаблону — також ресурси, яким цей шаблон призначено. | Читання |
| [modx_list_resources](API.md#modx_list_resources) | Перелічує ресурси (дерево вмісту), за потреби фільтруючи за parent, context, станом deleted або запитом pagetitle/alias/uri. | Читання |
| [modx_replace_across](API.md#modx_replace_across) | Пошук і заміна по всьому сайту в елементах коду (chunk/snippet/template/plugin): знаходить кожен елемент, ВМІСТ якого містить find, і замінює ВСІ входження на replacement за ОДИН виклик (без окремого читання/запису кожного елемента з вашого боку). | Значний вплив |
| [modx_dependency_graph](API.md#modx_dependency_graph) | За один виклик відображає, як елементи сайту ПОВ’ЯЗАНІ МІЖ СОБОЮ: який шаблон підключає який чанк/сніпет/TV, який сніпет виводить який чанк, де прив’язано кожен TV. | Читання |

### VersionX

| Інструмент | Перекладений опис | Вплив |
| --- | --- | --- |
| [modx_versionx_list_versions](API.md#modx_versionx_list_versions) | Перелічує версії VersionX для ресурсу, чанка, сніпета, шаблону, плагіна або TV MODX. | Читання |
| [modx_versionx_get_version](API.md#modx_versionx_get_version) | Читає дані однієї версії VersionX перед ухваленням рішення про повернення до неї. | Змінює сайт |
| [modx_versionx_revert_version](API.md#modx_versionx_revert_version) | Повертає об’єкт MODX до певної версії VersionX. | Змінює сайт |

### VirtualPage

| Інструмент | Перекладений опис | Вплив |
| --- | --- | --- |
| [modx_virtualpage_list_events](API.md#modx_virtualpage_list_events) | Перелічує події VirtualPage, що прив’язують групи маршрутів до подій MODX. | Змінює сайт |
| [modx_virtualpage_get_event](API.md#modx_virtualpage_get_event) | Читає одну подію VirtualPage за id або name, включно з її маршрутами. | Змінює сайт |
| [modx_virtualpage_create_event](API.md#modx_virtualpage_create_event) | Створює подію VirtualPage, зазвичай OnPageNotFound або OnHandleRequest. | Змінює сайт |
| [modx_virtualpage_update_event](API.md#modx_virtualpage_update_event) | Оновлює подію VirtualPage за id або name. | Змінює сайт |
| [modx_virtualpage_list_handlers](API.md#modx_virtualpage_list_handlers) | Перелічує обробники VirtualPage, що виводять ресурси, сніпети, чанки або динамічні ресурси. | Змінює сайт |
| [modx_virtualpage_get_handler](API.md#modx_virtualpage_get_handler) | Читає один обробник VirtualPage за id або name, включно з маршрутами, що його використовують. | Змінює сайт |
| [modx_virtualpage_create_handler](API.md#modx_virtualpage_create_handler) | Створює обробник VirtualPage. type може бути resource, snippet, chunk, dynamic_resource/template або 0..3. | Змінює сайт |
| [modx_virtualpage_update_handler](API.md#modx_virtualpage_update_handler) | Оновлює обробник VirtualPage за id або name. | Змінює сайт |
| [modx_virtualpage_list_routes](API.md#modx_virtualpage_list_routes) | Перелічує маршрути VirtualPage з назвами їхніх подій і обробників. | Змінює сайт |
| [modx_virtualpage_get_route](API.md#modx_virtualpage_get_route) | Читає один маршрут VirtualPage за id або за route і необов’язковим method. | Змінює сайт |
| [modx_virtualpage_create_route](API.md#modx_virtualpage_create_route) | Створює маршрут VirtualPage та за потреби прив’язує плагін його події. | Змінює сайт |
| [modx_virtualpage_update_route](API.md#modx_virtualpage_update_route) | Оновлює маршрут VirtualPage за id. | Змінює сайт |
| [modx_virtualpage_resolve_route](API.md#modx_virtualpage_resolve_route) | Моделює зіставлення маршруту VirtualPage та повертає обробник і плейсхолдери vp.*. | Читання |
| [modx_virtualpage_delete_event](API.md#modx_virtualpage_delete_event) | Видаляє подію VirtualPage за id або name (також видаляє її маршрути відповідно до схеми VP). | Змінює сайт |
| [modx_virtualpage_delete_handler](API.md#modx_virtualpage_delete_handler) | Видаляє обробник VirtualPage за id або name. | Змінює сайт |
| [modx_virtualpage_delete_route](API.md#modx_virtualpage_delete_route) | Видаляє маршрут VirtualPage за id або name. | Змінює сайт |
| [modx_virtualpage_clear_cache](API.md#modx_virtualpage_clear_cache) | Очищає файли маршрутів/кешу VirtualPage та оновлює кеш MODX. | Змінює сайт |

### miniShop2

| Інструмент | Перекладений опис | Вплив |
| --- | --- | --- |
| [modx_ms2_list_option_types](API.md#modx_ms2_list_option_types) | Перелічує доступні типи полів опцій товарів miniShop2. | Змінює сайт |
| [modx_ms2_list_options](API.md#modx_ms2_list_options) | Перелічує опції товарів miniShop2 із вкладки опцій у налаштуваннях. | Змінює сайт |
| [modx_ms2_get_option](API.md#modx_ms2_get_option) | Читає одну опцію товару miniShop2 за id. | Змінює сайт |
| [modx_ms2_create_option](API.md#modx_ms2_create_option) | Створює опцію товару miniShop2 у розділі опцій налаштувань. | Змінює сайт |
| [modx_ms2_update_option](API.md#modx_ms2_update_option) | Оновлює опцію товару miniShop2 у розділі опцій налаштувань. | Змінює сайт |
| [modx_ms2_assign_option_to_category](API.md#modx_ms2_assign_option_to_category) | Призначає наявну опцію miniShop2 категорії msCategory. | Змінює сайт |
| [modx_ms2_get_product_options](API.md#modx_ms2_get_product_options) | Читає значення опцій miniShop2, призначених товару. | Змінює сайт |
| [modx_ms2_update_product_options](API.md#modx_ms2_update_product_options) | Створює, оновлює або видаляє значення опцій miniShop2 для товару. | Змінює сайт |
| [modx_ms2_list_link_types](API.md#modx_ms2_list_link_types) | Перелічує типи зв’язків miniShop2 (msLink): id, type, name, description. | Змінює сайт |
| [modx_ms2_get_link_type](API.md#modx_ms2_get_link_type) | Отримує тип зв’язку miniShop2 за id. | Змінює сайт |
| [modx_ms2_create_link_type](API.md#modx_ms2_create_link_type) | Створює тип зв’язку miniShop2. 'type' — вид відношення, що визначає спосіб зв’язування товарів. | Змінює сайт |
| [modx_ms2_update_link_type](API.md#modx_ms2_update_link_type) | Оновлює тип зв’язку miniShop2. | Змінює сайт |
| [modx_ms2_delete_link_type](API.md#modx_ms2_delete_link_type) | Видаляє тип зв’язку miniShop2 за id. | Змінює сайт |
| [modx_ms2_list_product_links](API.md#modx_ms2_list_product_links) | Перелічує зв’язки між товарами (msProductLink), за потреби для одного товару. | Змінює сайт |
| [modx_ms2_create_product_link](API.md#modx_ms2_create_product_link) | Зв’язує два товари за певним типом зв’язку. Вид відношення цього типу визначає, чи створюється також зворотний зв’язок. | Змінює сайт |
| [modx_ms2_delete_product_link](API.md#modx_ms2_delete_product_link) | Видаляє зв’язок товарів (за типом зв’язку + master + slave). | Змінює сайт |
| [modx_ms2_list_categories](API.md#modx_ms2_list_categories) | Перелічує категорії miniShop2 (msCategory). | Змінює сайт |
| [modx_ms2_create_category](API.md#modx_ms2_create_category) | Створює категорію miniShop2 (ресурс msCategory). | Змінює сайт |
| [modx_ms2_update_category](API.md#modx_ms2_update_category) | Оновлює категорію miniShop2. | Змінює сайт |
| [modx_ms2_list_orders](API.md#modx_ms2_list_orders) | Перелічує замовлення miniShop2 (msOrder) із можливістю фільтрації за статусом/клієнтом/контекстом/діапазоном дат. | Змінює сайт |
| [modx_ms2_get_order](API.md#modx_ms2_get_order) | Отримує замовлення miniShop2 за id (із позиціями/клієнтом). | Змінює сайт |
| [modx_ms2_update_order](API.md#modx_ms2_update_order) | Оновлює замовлення miniShop2 — зазвичай для зміни статусу (запускає логіку зміни статусу ms2), доставки або оплати. | Змінює сайт |

### MIGX

| Інструмент | Перекладений опис | Вплив |
| --- | --- | --- |
| [modx_migx_list_configs](API.md#modx_migx_list_configs) | Перелічує конфігурації MIGX (migxConfig): id, name, category, published. | Змінює сайт |
| [modx_migx_get_config](API.md#modx_migx_get_config) | Отримує повну конфігурацію MIGX за id (formtabs, columns, buttons, filters, permissions — у вигляді збережених рядків JSON). | Змінює сайт |
| [modx_migx_create_config](API.md#modx_migx_create_config) | Створює конфігурацію MIGX. formtabs/columns/contextmenus/actionbuttons/columnbuttons/filters — рядки JSON (у тому самому форматі, у якому MIGX зберігає їх у менеджері). | Змінює сайт |
| [modx_migx_update_config](API.md#modx_migx_update_config) | Оновлює конфігурацію MIGX. Змінюються лише передані поля. | Змінює сайт |
| [modx_migx_delete_config](API.md#modx_migx_delete_config) | Видаляє конфігурацію MIGX за id. | Змінює сайт |

### Користувачі та права доступу

| Інструмент | Перекладений опис | Вплив |
| --- | --- | --- |
| [modx_flush_permissions](API.md#modx_flush_permissions) | Очищає кешовані права доступу (команда менеджера 'Flush Permissions'), щоб зміни ACL набули чинності. | Змінює сайт |
| [modx_list_users](API.md#modx_list_users) | Перелічує користувачів менеджера/вебкористувачів (modUser). | Читання |
| [modx_get_user](API.md#modx_get_user) | Отримує користувача за id. | Читання |
| [modx_create_user](API.md#modx_create_user) | Створює користувача. Передайте 'password', щоб задати пароль явно; пропустіть для автоматичної генерації. Разом із цим приймаються поля профілю (email, fullname, phone…). | Змінює сайт |
| [modx_update_user](API.md#modx_update_user) | Оновлює користувача (поля профілю, стан активності). | Змінює сайт |
| [modx_delete_user](API.md#modx_delete_user) | Видаляє користувача за id. | Змінює сайт |
| [modx_list_user_groups](API.md#modx_list_user_groups) | Перелічує групи користувачів MODX (id, name, parent, rank). | Читання |
| [modx_get_user_group](API.md#modx_get_user_group) | Отримує одну групу користувачів за id. | Читання |
| [modx_create_user_group](API.md#modx_create_user_group) | Створює групу користувачів. | Змінює сайт |
| [modx_update_user_group](API.md#modx_update_user_group) | Оновлює групу користувачів. | Змінює сайт |
| [modx_delete_user_group](API.md#modx_delete_user_group) | Видаляє групу користувачів за id. | Змінює сайт |
| [modx_list_user_group_members](API.md#modx_list_user_group_members) | Перелічує учасників групи користувачів. | Читання |
| [modx_add_user_to_group](API.md#modx_add_user_to_group) | Додає користувача до групи користувачів, за потреби з роллю. | Змінює сайт |
| [modx_update_group_member](API.md#modx_update_group_member) | Оновлює членство користувача в групі (наприклад, змінює роль). | Змінює сайт |
| [modx_remove_user_from_group](API.md#modx_remove_user_from_group) | Вилучає користувача з групи користувачів. | Змінює сайт |
| [modx_list_roles](API.md#modx_list_roles) | Перелічує ролі доступу (id, name, authority). | Читання |
| [modx_get_role](API.md#modx_get_role) | Отримує роль за id. | Читання |
| [modx_create_role](API.md#modx_create_role) | Створює роль. | Змінює сайт |
| [modx_update_role](API.md#modx_update_role) | Оновлює роль. | Змінює сайт |
| [modx_delete_role](API.md#modx_delete_role) | Видаляє роль за id. | Змінює сайт |
| [modx_list_access_policies](API.md#modx_list_access_policies) | Перелічує політики доступу (id, name, description, template). | Читання |
| [modx_get_access_policy](API.md#modx_get_access_policy) | Отримує політику доступу за id. | Читання |
| [modx_create_access_policy](API.md#modx_create_access_policy) | Створює політику доступу. | Змінює сайт |
| [modx_update_access_policy](API.md#modx_update_access_policy) | Оновлює політику доступу (наприклад, її мапу дозволів). | Значний вплив |
| [modx_delete_access_policy](API.md#modx_delete_access_policy) | Видаляє політику доступу за id. | Змінює сайт |
| [modx_list_access_policy_templates](API.md#modx_list_access_policy_templates) | Перелічує шаблони політик доступу. | Читання |
| [modx_get_access_policy_template](API.md#modx_get_access_policy_template) | Отримує шаблон політики доступу за id. | Читання |
| [modx_create_access_policy_template](API.md#modx_create_access_policy_template) | Створює шаблон політики доступу. | Змінює сайт |
| [modx_update_access_policy_template](API.md#modx_update_access_policy_template) | Оновлює шаблон політики доступу. | Змінює сайт |
| [modx_delete_access_policy_template](API.md#modx_delete_access_policy_template) | Видаляє шаблон політики доступу за id. | Змінює сайт |
| [modx_list_access_permissions](API.md#modx_list_access_permissions) | Перелічує дозволи, визначені шаблоном політики (каталог лише для читання). | Читання |
| [modx_list_resource_groups](API.md#modx_list_resource_groups) | Перелічує групи ресурсів (id, name). | Читання |
| [modx_get_resource_group](API.md#modx_get_resource_group) | Отримує групу ресурсів за id. | Читання |
| [modx_list_resource_group_resources](API.md#modx_list_resource_group_resources) | Перелічує ресурси, призначені групі ресурсів. | Читання |
| [modx_create_resource_group](API.md#modx_create_resource_group) | Створює групу ресурсів. | Змінює сайт |
| [modx_update_resource_group](API.md#modx_update_resource_group) | Оновлює групу ресурсів. | Змінює сайт |
| [modx_delete_resource_group](API.md#modx_delete_resource_group) | Видаляє групу ресурсів за id. | Змінює сайт |
| [modx_assign_resource_to_group](API.md#modx_assign_resource_to_group) | Додає ресурс (документ) до групи ресурсів. | Змінює сайт |
| [modx_remove_resource_from_group](API.md#modx_remove_resource_from_group) | Вилучає ресурс (документ) із групи ресурсів. | Змінює сайт |
| [modx_list_context_access](API.md#modx_list_context_access) | Перелічує записи ACL доступу до контекстів, за потреби фільтруючи за usergroup/context/policy. | Читання |
| [modx_grant_context_access](API.md#modx_grant_context_access) | Надає групі користувачів доступ до контексту з політикою та мінімальним рівнем повноважень ролі. | Змінює сайт |
| [modx_update_context_access](API.md#modx_update_context_access) | Оновлює запис ACL доступу до контексту за id. | Змінює сайт |
| [modx_revoke_context_access](API.md#modx_revoke_context_access) | Видаляє запис ACL доступу до контексту за id. | Змінює сайт |
| [modx_list_resourcegroup_access](API.md#modx_list_resourcegroup_access) | Перелічує записи ACL доступу до груп ресурсів. | Читання |
| [modx_grant_resourcegroup_access](API.md#modx_grant_resourcegroup_access) | Надає групі користувачів доступ до групи ресурсів із політикою та мінімальним рівнем повноважень ролі. | Змінює сайт |
| [modx_update_resourcegroup_access](API.md#modx_update_resourcegroup_access) | Оновлює запис ACL доступу до групи ресурсів за id. | Змінює сайт |
| [modx_revoke_resourcegroup_access](API.md#modx_revoke_resourcegroup_access) | Видаляє запис ACL доступу до групи ресурсів за id. | Змінює сайт |

### ClientConfig

| Інструмент | Перекладений опис | Вплив |
| --- | --- | --- |
| [modx_clientconfig_list_settings](API.md#modx_clientconfig_list_settings) | Перелічує налаштування ClientConfig, включно зі значеннями для окремих контекстів. | Змінює сайт |
| [modx_clientconfig_get_setting](API.md#modx_clientconfig_get_setting) | Отримує налаштування ClientConfig за id або key. | Змінює сайт |
| [modx_clientconfig_create_setting](API.md#modx_clientconfig_create_setting) | Створює налаштування ClientConfig. | Змінює сайт |
| [modx_clientconfig_update_setting](API.md#modx_clientconfig_update_setting) | Оновлює налаштування ClientConfig за id або key. | Змінює сайт |
| [modx_clientconfig_delete_setting](API.md#modx_clientconfig_delete_setting) | Видаляє налаштування ClientConfig за id або key. | Змінює сайт |

### Набори властивостей

| Інструмент | Перекладений опис | Вплив |
| --- | --- | --- |
| [modx_list_property_sets](API.md#modx_list_property_sets) | Перелічує набори властивостей (modPropertySet): id, name, description, category. | Читання |
| [modx_get_property_set](API.md#modx_get_property_set) | Отримує набір властивостей за id (включно з його властивостями). | Читання |
| [modx_create_property_set](API.md#modx_create_property_set) | Створює набір властивостей. 'properties' — об’єкт JSON із визначеннями властивостей. | Змінює сайт |
| [modx_update_property_set](API.md#modx_update_property_set) | Оновлює набір властивостей. | Змінює сайт |
| [modx_delete_property_set](API.md#modx_delete_property_set) | Видаляє набір властивостей за id. | Змінює сайт |
| [modx_assign_property_set](API.md#modx_assign_property_set) | Приєднує набір властивостей до елемента. Вкажіть елемент через його id + element_class (наприклад, 'modSnippet') або element_type ('snippet'/'chunk'/'template'/'plugin'/'tv'). | Змінює сайт |
| [modx_unassign_property_set](API.md#modx_unassign_property_set) | Від’єднує набір властивостей від елемента (та сама ідентифікація, що й для приєднання). | Змінює сайт |

### Контексти та налаштування контекстів

| Інструмент | Перекладений опис | Вплив |
| --- | --- | --- |
| [modx_list_contexts](API.md#modx_list_contexts) | Перелічує контексти (modContext): key, name, description, rank. | Читання |
| [modx_get_context](API.md#modx_get_context) | Отримує контекст за key. | Читання |
| [modx_create_context](API.md#modx_create_context) | Створює контекст. 'key' — унікальний ідентифікатор (наприклад, 'mobile'). | Змінює сайт |
| [modx_update_context](API.md#modx_update_context) | Оновлює контекст (name/description/rank). 'settings' може бути масивом JSON із налаштуваннями контексту (розширене використання). | Змінює сайт |
| [modx_delete_context](API.md#modx_delete_context) | Видаляє контекст за key. | Значний вплив |
| [modx_list_context_settings](API.md#modx_list_context_settings) | Перелічує налаштування контексту (modContextSetting). | Читання |
| [modx_get_context_setting](API.md#modx_get_context_setting) | Отримує одне налаштування контексту (за context_key + key). | Читання |
| [modx_create_context_setting](API.md#modx_create_context_setting) | Створює налаштування рівня контексту (перевизначає системне налаштування для цього контексту). | Змінює сайт |
| [modx_update_context_setting](API.md#modx_update_context_setting) | Оновлює налаштування контексту (за context_key + key). | Змінює сайт |
| [modx_delete_context_setting](API.md#modx_delete_context_setting) | Видаляє налаштування контексту (за context_key + key). | Змінює сайт |

### Пакети та постачальники

| Інструмент | Перекладений опис | Вплив |
| --- | --- | --- |
| [modx_install_package](API.md#modx_install_package) | Установлює транспортний пакет від постачальника (за замовчуванням modx.com) за назвою, наприклад {package:'MIGX'}. | Значний вплив |
| [modx_uninstall_package](API.md#modx_uninstall_package) | Видаляє транспортний пакет за сигнатурою (наприклад, 'migx-2.13.0-pl'). | Значний вплив |
| [modx_list_providers](API.md#modx_list_providers) | Перелічує постачальників транспортних пакетів (modx.com і користувацьких, наприклад modstore.pro): id, name, service_url. | Читання |
| [modx_search_packages](API.md#modx_search_packages) | Шукає в каталозі постачальника (використовуйте перед install_package). | Читання |
| [modx_create_provider](API.md#modx_create_provider) | Додає постачальника транспортних пакетів (наприклад, modstore.pro). | Змінює сайт |
| [modx_update_provider](API.md#modx_update_provider) | Оновлює постачальника транспортних пакетів. | Змінює сайт |
| [modx_delete_provider](API.md#modx_delete_provider) | Видаляє постачальника транспортних пакетів за id. | Змінює сайт |

### Простори імен

| Інструмент | Перекладений опис | Вплив |
| --- | --- | --- |
| [modx_list_namespaces](API.md#modx_list_namespaces) | Перелічує простори імен (modNamespace): name, path, assets_path. | Читання |
| [modx_create_namespace](API.md#modx_create_namespace) | Створює простір імен. | Змінює сайт |
| [modx_update_namespace](API.md#modx_update_namespace) | Оновлює простір імен (за name). | Змінює сайт |
| [modx_delete_namespace](API.md#modx_delete_namespace) | Видаляє простір імен за name ('core' видалити неможливо). | Змінює сайт |

### Лексикон і переклади

| Інструмент | Перекладений опис | Вплив |
| --- | --- | --- |
| [modx_list_lexicon_entries](API.md#modx_list_lexicon_entries) | Перелічує записи лексикону з фільтрацією за namespace + topic + language (і необов’язковим пошуком). | Читання |
| [modx_list_lexicon_topics](API.md#modx_list_lexicon_topics) | Перелічує теми лексикону для namespace/language. | Читання |
| [modx_set_lexicon_entry](API.md#modx_set_lexicon_entry) | Створює або перевизначає запис лексикону (перевизначення файлового значення в БД). | Змінює сайт |
| [modx_revert_lexicon_entry](API.md#modx_revert_lexicon_entry) | Відновлює запис лексикону — видаляє його перевизначення в БД, щоб застосовувалося значення з файлу. | Змінює сайт |

### Обслуговування та діагностика

| Інструмент | Перекладений опис | Вплив |
| --- | --- | --- |
| [modx_get_site_state](API.md#modx_get_site_state) | Читає ревізію сайту, для якої Connector є авторитетним джерелом, і відомості про підтримку атомарних передумов, які використовують зовнішні інструменти. | Читання |
| [modx_describe_object](API.md#modx_describe_object) | Дослідження схеми: перелічує поля класу xPDO (name + тип php/db, null, default) та його первинний ключ, щоб використовувати РЕАЛЬНІ назви полів, а не вгадувати їх. | Читання |
| [modx_read_error_log](API.md#modx_read_error_log) | Читає кінець журналу помилок MODX (core/cache/logs/error.log) для діагностики. | Читання |
| [modx_refresh_uris](API.md#modx_refresh_uris) | Повторно генерує всі URI ресурсів (запускайте після масових змін псевдонімів/структури, які залишили застарілі URI). | Змінює сайт |
| [modx_remove_locks](API.md#modx_remove_locks) | Очищає застарілі блокування редагування в менеджері (коли повідомляється, що елемент/ресурс заблоковано сеансом, який уже не працює). | Змінює сайт |
| [modx_project_overview](API.md#modx_project_overview) | Дає змогу зорієнтуватися в усьому встановленому сайті за ОДИН компактний виклик із малими витратами. | Читання |
| [modx_system_info](API.md#modx_system_info) | Інформація про середовище/діагностика: версія MODX, версія modxMCP, версія PHP, тип БД, основні шляхи. | Читання |
| [modx_regenerate_token](API.md#modx_regenerate_token) | Замінює modxmcp.api_token новим випадковим значенням і повертає його. | Значний вплив |
| [modx_help](API.md#modx_help) | Вбудована документація. Викличте без аргументів для списку тем; передайте {topic} для посібника (наприклад, 'tv_input_types', 'study_component', 'minishop2', 'migx', 'acl', 'getting_started'). Прочитайте відповідну тему перед незнайомою роботою або щоб дізнатися, як використовувати щойно встановлений компонент. | Читання |
| [modx_list_actions](API.md#modx_list_actions) | Перелічує назви дій, які підтримує ця збірка сервера modxMCP, згруповані за напрямами. | Читання |
| [modx_clear_cache](API.md#modx_clear_cache) | Оновлює кеш MODX. Передайте partitions (наприклад, ['resource','context_settings']), щоб оновити лише ці розділи; пропустіть для повного оновлення. | Змінює сайт |
| [modx_read_audit_log](API.md#modx_read_audit_log) | Читає журнал аудиту записів modxMCP (найновіші записи в кінці). | Читання |
| [modx_run_processor](API.md#modx_run_processor) | Запускає БУДЬ-ЯКИЙ процесор MODX безпосередньо (обхід стандартних обмежень інструментів). | Значний вплив |
