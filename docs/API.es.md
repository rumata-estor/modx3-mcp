# Referencia de API — MODX MCP

[English](API.md) · [Русский](API.ru.md) · [Español](API.es.md) · [Deutsch](API.de.md) · [Français](API.fr.md) · [Українська](API.uk.md) · [Қазақша](API.kk.md) · [Nederlands](API.nl.md) · [Polski](API.pl.md)

> Nota: esta es una guía traducida de consulta rápida. Las tablas detalladas de los 198 instrumentos MCP se encuentran en los documentos inglés y ruso.
Las herramientas MCP públicas usan el prefijo `modx_`; las acciones HTTP de PHP no lo llevan. Las tablas exhaustivas de parámetros y los esquemas JSON están disponibles en la referencia inglesa.

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

Envíe solicitudes `POST` con JSON y `X-MCP-Token` mediante HTTPS. `api.php` no es un endpoint MCP JSON-RPC; no envíe mensajes `tools/call` directamente.

**Referencias técnicas completas:** [parámetros API (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/API.md) · [seguridad (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/SECURITY.md) · [desarrollo (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/DEVELOPMENT.md). Las tablas técnicas detalladas se mantienen en inglés.


## Índice de las 198 herramientas MCP

> Este índice está localizado; para campos obligatorios, tipos, límites y enumeraciones consulte el esquema técnico enlazado en inglés o ruso.

[Guía completa en español](CAPABILITIES.es.md) · [English API schemas](API.md) · [Русский API](API.ru.md)

### Elementos y recursos

| Herramienta | Descripción traducida | Tipo de operación |
| --- | --- | --- |
| [modx_list_elements](API.md#modx_list_elements) | Enumera elementos MODX de un tipo (id + name). | Lectura |
| [modx_list_categories](API.md#modx_list_categories) | Listar categorías de elementos. | Lectura |
| [modx_get_category](API.md#modx_get_category) | Ver categoría y su uso. | Lectura |
| [modx_create_category](API.md#modx_create_category) | Crear categoría de elementos. | Alto impacto |
| [modx_update_category](API.md#modx_update_category) | Renombrar o mover categoría. | Alto impacto |
| [modx_delete_category](API.md#modx_delete_category) | Eliminar categoría vacía tras revisión. | Alto impacto |
| [modx_assign_element_category](API.md#modx_assign_element_category) | Asignar categoría a un elemento. | Modifica el sitio |
| [modx_make_static](API.md#modx_make_static) | Convierte un chunk/snippet/template/plugin en un archivo estático bajo core/elements/ (escribe el archivo y establece static=1 + source=Filesystem). | Alto impacto |
| [modx_get_element](API.md#modx_get_element) | Lee un elemento MODX (devuelve sus campos + el código completo). | Lectura |
| [modx_update_element_property](API.md#modx_update_element_property) | Modifica de forma segura una propiedad predeterminada existente mediante una comprobación CAS del valor esperado; nunca reemplaza el código ni otras propiedades. | Modifica el sitio |
| [modx_update_element](API.md#modx_update_element) | Actualiza un elemento MODX REEMPLAZANDO todo su contenido (envíe el contenido nuevo completo). | Alto impacto |
| [modx_create_element](API.md#modx_create_element) | Crea un nuevo elemento MODX. | Alto impacto |
| [modx_delete_element](API.md#modx_delete_element) | Elimina un elemento MODX. Ejecute PRIMERO con dry_run:true: devuelve lo que se eliminaría y dónde sigue habiendo referencias al elemento (para recursos: número de recursos hijos), sin eliminar nada. Revíselo antes de la eliminación real. | Alto impacto |
| [modx_view_element](API.md#modx_view_element) | Muestra un chunk/snippet/template/plugin como líneas NUMERADAS (como cat -n), opcionalmente limitadas por start_line/end_line. | Modifica el sitio |
| [modx_edit_element_lines](API.md#modx_edit_element_lines) | Edita un chunk/snippet/template/plugin POR LÍNEAS, enviando solo las líneas modificadas (no hace falta reenviar todo el elemento). | Alto impacto |
| [modx_bulk_resources](API.md#modx_bulk_resources) | Aplica UNA operación a muchos recursos a la vez: publish, unpublish, set_template (requiere template), move (requiere parent_to y/o context_to) o delete. | Alto impacto |
| [modx_duplicate_resource](API.md#modx_duplicate_resource) | Duplica un recurso. id = recurso de origen; opcionales: name (nuevo pagetitle), duplicate_children (bool), published_mode (preserve\|publish\|unpublish). | Modifica el sitio |
| [modx_duplicate_element](API.md#modx_duplicate_element) | Duplica un elemento (chunk/snippet/template/plugin/tv). type + id; name opcional para la copia. | Modifica el sitio |
| [modx_undelete_resource](API.md#modx_undelete_resource) | Restaura un recurso eliminado de forma lógica desde la papelera (revierte su eliminación). id = id del recurso. | Modifica el sitio |
| [modx_empty_recycle_bin](API.md#modx_empty_recycle_bin) | Purga permanentemente TODOS los recursos de la papelera (eliminados de forma lógica). | Alto impacto |
| [modx_reorder_resources](API.md#modx_reorder_resources) | Reordena recursos en el árbol estableciendo menuindex (y, opcionalmente, parent). items = [{id, menuindex, parent?}], aplicado por recurso. | Modifica el sitio |

### TV de recursos

| Herramienta | Descripción traducida | Tipo de operación |
| --- | --- | --- |
| [modx_get_resource_tvs](API.md#modx_get_resource_tvs) | Obtiene los valores de las variables de plantilla (TV) de un recurso. | Lectura |
| [modx_update_resource_tvs](API.md#modx_update_resource_tvs) | Actualiza los valores de las variables de plantilla (TV) de un recurso. | Modifica el sitio |
| [modx_list_tv_values](API.md#modx_list_tv_values) | Enumera los valores almacenados explícitamente para una TV en todos los recursos, incluidos valores obsoletos en recursos cuya plantilla actual puede haber dejado de utilizar esa TV. | Lectura |
| [modx_clear_tv_values](API.md#modx_clear_tv_values) | Previsualiza o borra todos los valores almacenados explícitamente para una TV. | Modifica el sitio |

### Tipos de campo de TV

| Herramienta | Descripción traducida | Tipo de operación |
| --- | --- | --- |
| [modx_suggest_tv_type](API.md#modx_suggest_tv_type) | ¿No sabe qué tipo de entrada de TV se ajusta a una necesidad? Descríbala (en inglés o ruso) y obtenga candidatos field_types ordenados con sus motivos + un esquema create_element listo para editar para la mejor opción (con las claves adicionales que necesita ese tipo). | Lectura |
| [modx_list_tv_input_types](API.md#modx_list_tv_input_types) | Enumera los tipos de entrada (widget) de TV disponibles en este sitio. | Lectura |

### Ajustes del sistema

| Herramienta | Descripción traducida | Tipo de operación |
| --- | --- | --- |
| [modx_list_system_settings](API.md#modx_list_system_settings) | Enumera los ajustes del sistema de MODX. | Lectura |
| [modx_get_system_setting](API.md#modx_get_system_setting) | Obtiene un ajuste del sistema de MODX por su clave. | Lectura |
| [modx_create_system_setting](API.md#modx_create_system_setting) | Crea un ajuste del sistema de MODX. | Modifica el sitio |
| [modx_update_system_setting](API.md#modx_update_system_setting) | Actualiza un ajuste del sistema de MODX por su clave. | Alto impacto |
| [modx_delete_system_setting](API.md#modx_delete_system_setting) | Elimina un ajuste del sistema de MODX por su clave. | Alto impacto |

### Orígenes multimedia y archivos

| Herramienta | Descripción traducida | Tipo de operación |
| --- | --- | --- |
| [modx_list_media_sources](API.md#modx_list_media_sources) | Enumera los orígenes multimedia de MODX. | Lectura |
| [modx_get_media_source](API.md#modx_get_media_source) | Obtiene un origen multimedia de MODX. | Lectura |
| [modx_create_media_source](API.md#modx_create_media_source) | Crea un origen multimedia (de archivos). class_key tiene como valor predeterminado sources.modFileMediaSource. 'properties' es un mapa {name:value} de parámetros del origen (p. ej. {"basePath":"assets/files/","baseUrl":"assets/files/"}) que se combina con el origen. | Modifica el sitio |
| [modx_update_media_source](API.md#modx_update_media_source) | Actualiza un origen multimedia. 'properties' es un mapa {name:value} que se combina con los parámetros del origen (se conservan los demás); utilícelo para establecer basePath/baseUrl/etc. | Modifica el sitio |
| [modx_delete_media_source](API.md#modx_delete_media_source) | Elimina un origen multimedia por id. | Modifica el sitio |
| [modx_list_media_source_files](API.md#modx_list_media_source_files) | Enumera archivos y carpetas dentro de un origen multimedia. | Lectura |
| [modx_read_media_source_file](API.md#modx_read_media_source_file) | Lee un archivo dentro de un origen multimedia. | Lectura |
| [modx_create_media_file](API.md#modx_create_media_file) | Crea un archivo dentro de un origen multimedia (escribe contenido). source = id o nombre del origen, path = directorio de destino (relativo a la base del origen), name = nombre del archivo, content = cuerpo del archivo. | Modifica el sitio |
| [modx_update_media_file](API.md#modx_update_media_file) | Sobrescribe el contenido de un archivo dentro de un origen multimedia. source = id/nombre, path = ruta del archivo dentro del origen, content = cuerpo nuevo. | Modifica el sitio |
| [modx_delete_media_file](API.md#modx_delete_media_file) | Elimina un archivo dentro de un origen multimedia. source = id/nombre, path = ruta del archivo dentro del origen. | Alto impacto |
| [modx_rename_media_file](API.md#modx_rename_media_file) | Renombra un archivo dentro de un origen multimedia. source = id/nombre, path = ruta actual del archivo, new_name = nuevo nombre del archivo. | Modifica el sitio |
| [modx_create_media_folder](API.md#modx_create_media_folder) | Crea una carpeta dentro de un origen multimedia. source = id/nombre, parent = directorio padre (raíz de forma predeterminada), name = nombre de la carpeta. | Modifica el sitio |
| [modx_delete_media_folder](API.md#modx_delete_media_folder) | Elimina una carpeta (y su contenido) dentro de un origen multimedia. source = id/nombre, path = ruta de la carpeta dentro del origen. | Alto impacto |

### Componentes y archivos

| Herramienta | Descripción traducida | Tipo de operación |
| --- | --- | --- |
| [modx_list_installed_components](API.md#modx_list_installed_components) | Enumera los componentes MODX instalados en core/components y assets/components. | Lectura |
| [modx_get_component_files](API.md#modx_get_component_files) | Enumera archivos y carpetas dentro de un componente MODX instalado. | Lectura |
| [modx_read_component_file](API.md#modx_read_component_file) | Lee un archivo de un componente MODX instalado. | Lectura |
| [modx_check_integrations](API.md#modx_check_integrations) | Informa de qué complementos populares de MODX (miniShop2, MIGX, pdoTools, Tickets, etc.) están instalados en este sitio y qué puede hacer modxMCP con cada uno. | Lectura |

### Búsqueda de código y dependencias

| Herramienta | Descripción traducida | Tipo de operación |
| --- | --- | --- |
| [modx_search_code](API.md#modx_search_code) | Busca texto completo en el CONTENIDO (y los nombres) de elementos y recursos. | Lectura |
| [modx_find_usages](API.md#modx_find_usages) | Encuentra dónde se utiliza un elemento: coincidencias de su nombre en el contenido de todo el código y, si es una plantilla, los recursos que tienen asignada esa plantilla. | Lectura |
| [modx_list_resources](API.md#modx_list_resources) | Enumera recursos (el árbol de contenido), opcionalmente filtrados por padre, contexto, estado de eliminación o una consulta pagetitle/alias/uri. | Lectura |
| [modx_replace_across](API.md#modx_replace_across) | Busca y reemplaza en todo el sitio dentro de elementos de código (chunk/snippet/template/plugin): encuentra todos los elementos cuyo CONTENIDO contiene find y reemplaza TODAS las apariciones por replacement, en UNA llamada (sin lecturas/escrituras por elemento por su parte). | Alto impacto |
| [modx_dependency_graph](API.md#modx_dependency_graph) | Representa cómo SE CONECTAN los elementos del sitio, en una llamada: qué plantilla incorpora qué chunk/snippet/TV, qué snippet renderiza qué chunk y dónde está asociada cada TV. | Lectura |

### VersionX

| Herramienta | Descripción traducida | Tipo de operación |
| --- | --- | --- |
| [modx_versionx_list_versions](API.md#modx_versionx_list_versions) | Enumera las versiones de VersionX de un recurso, chunk, snippet, plantilla, plugin o TV de MODX. | Lectura |
| [modx_versionx_get_version](API.md#modx_versionx_get_version) | Lee el contenido de una versión de VersionX antes de decidir si se revierte a ella. | Modifica el sitio |
| [modx_versionx_revert_version](API.md#modx_versionx_revert_version) | Revierte un objeto MODX a una versión específica de VersionX. | Modifica el sitio |

### VirtualPage

| Herramienta | Descripción traducida | Tipo de operación |
| --- | --- | --- |
| [modx_virtualpage_list_events](API.md#modx_virtualpage_list_events) | Enumera los eventos de VirtualPage que vinculan grupos de rutas con eventos MODX. | Modifica el sitio |
| [modx_virtualpage_get_event](API.md#modx_virtualpage_get_event) | Lee un evento de VirtualPage por id o nombre, incluidas sus rutas. | Modifica el sitio |
| [modx_virtualpage_create_event](API.md#modx_virtualpage_create_event) | Crea un evento de VirtualPage, normalmente OnPageNotFound u OnHandleRequest. | Modifica el sitio |
| [modx_virtualpage_update_event](API.md#modx_virtualpage_update_event) | Actualiza un evento de VirtualPage por id o nombre. | Modifica el sitio |
| [modx_virtualpage_list_handlers](API.md#modx_virtualpage_list_handlers) | Enumera los manejadores de VirtualPage que renderizan recursos, snippets, chunks o recursos dinámicos. | Modifica el sitio |
| [modx_virtualpage_get_handler](API.md#modx_virtualpage_get_handler) | Lee un manejador de VirtualPage por id o nombre, incluidas las rutas que lo utilizan. | Modifica el sitio |
| [modx_virtualpage_create_handler](API.md#modx_virtualpage_create_handler) | Crea un manejador de VirtualPage. type puede ser resource, snippet, chunk, dynamic_resource/template o 0..3. | Modifica el sitio |
| [modx_virtualpage_update_handler](API.md#modx_virtualpage_update_handler) | Actualiza un manejador de VirtualPage por id o nombre. | Modifica el sitio |
| [modx_virtualpage_list_routes](API.md#modx_virtualpage_list_routes) | Enumera las rutas de VirtualPage con los nombres de sus eventos y manejadores. | Modifica el sitio |
| [modx_virtualpage_get_route](API.md#modx_virtualpage_get_route) | Lee una ruta de VirtualPage por id, o por route y method opcional. | Modifica el sitio |
| [modx_virtualpage_create_route](API.md#modx_virtualpage_create_route) | Crea una ruta de VirtualPage y vincula su plugin de evento si es necesario. | Modifica el sitio |
| [modx_virtualpage_update_route](API.md#modx_virtualpage_update_route) | Actualiza una ruta de VirtualPage por id. | Modifica el sitio |
| [modx_virtualpage_resolve_route](API.md#modx_virtualpage_resolve_route) | Simula la coincidencia de rutas de VirtualPage y devuelve el manejador junto con los marcadores vp.*. | Lectura |
| [modx_virtualpage_delete_event](API.md#modx_virtualpage_delete_event) | Elimina un evento de VirtualPage por id o nombre (también elimina sus rutas, según el esquema de VP). | Modifica el sitio |
| [modx_virtualpage_delete_handler](API.md#modx_virtualpage_delete_handler) | Elimina un manejador de VirtualPage por id o nombre. | Modifica el sitio |
| [modx_virtualpage_delete_route](API.md#modx_virtualpage_delete_route) | Elimina una ruta de VirtualPage por id o nombre. | Modifica el sitio |
| [modx_virtualpage_clear_cache](API.md#modx_virtualpage_clear_cache) | Borra los archivos de rutas/caché de VirtualPage y actualiza la caché de MODX. | Modifica el sitio |

### miniShop2

| Herramienta | Descripción traducida | Tipo de operación |
| --- | --- | --- |
| [modx_ms2_list_option_types](API.md#modx_ms2_list_option_types) | Enumera los tipos de campo disponibles para opciones de producto de miniShop2. | Modifica el sitio |
| [modx_ms2_list_options](API.md#modx_ms2_list_options) | Enumera las opciones de producto de miniShop2 de la pestaña de opciones de los ajustes. | Modifica el sitio |
| [modx_ms2_get_option](API.md#modx_ms2_get_option) | Lee una opción de producto de miniShop2 por id. | Modifica el sitio |
| [modx_ms2_create_option](API.md#modx_ms2_create_option) | Crea una opción de producto de miniShop2 en las opciones de los ajustes. | Modifica el sitio |
| [modx_ms2_update_option](API.md#modx_ms2_update_option) | Actualiza una opción de producto de miniShop2 en las opciones de los ajustes. | Modifica el sitio |
| [modx_ms2_assign_option_to_category](API.md#modx_ms2_assign_option_to_category) | Asigna una opción existente de miniShop2 a una msCategory. | Modifica el sitio |
| [modx_ms2_get_product_options](API.md#modx_ms2_get_product_options) | Lee los valores de opciones de miniShop2 asignados a un producto. | Modifica el sitio |
| [modx_ms2_update_product_options](API.md#modx_ms2_update_product_options) | Crea, actualiza o elimina valores de opciones de miniShop2 para un producto. | Modifica el sitio |
| [modx_ms2_list_link_types](API.md#modx_ms2_list_link_types) | Enumera los tipos de vínculo de miniShop2 (msLink): id, type, name, description. | Modifica el sitio |
| [modx_ms2_get_link_type](API.md#modx_ms2_get_link_type) | Obtiene un tipo de vínculo de miniShop2 por id. | Modifica el sitio |
| [modx_ms2_create_link_type](API.md#modx_ms2_create_link_type) | Crea un tipo de vínculo de miniShop2. 'type' es la clase de relación que determina cómo se vinculan los productos. | Modifica el sitio |
| [modx_ms2_update_link_type](API.md#modx_ms2_update_link_type) | Actualiza un tipo de vínculo de miniShop2. | Modifica el sitio |
| [modx_ms2_delete_link_type](API.md#modx_ms2_delete_link_type) | Elimina un tipo de vínculo de miniShop2 por id. | Modifica el sitio |
| [modx_ms2_list_product_links](API.md#modx_ms2_list_product_links) | Enumera los vínculos entre productos (msProductLink), opcionalmente para un solo producto. | Modifica el sitio |
| [modx_ms2_create_product_link](API.md#modx_ms2_create_product_link) | Vincula dos productos bajo un tipo de vínculo. La clase de relación del tipo de vínculo determina si también se crea el vínculo inverso. | Modifica el sitio |
| [modx_ms2_delete_product_link](API.md#modx_ms2_delete_product_link) | Elimina un vínculo de producto (por tipo de vínculo + master + slave). | Modifica el sitio |
| [modx_ms2_list_categories](API.md#modx_ms2_list_categories) | Enumera las categorías de miniShop2 (msCategory). | Modifica el sitio |
| [modx_ms2_create_category](API.md#modx_ms2_create_category) | Crea una categoría de miniShop2 (recurso msCategory). | Modifica el sitio |
| [modx_ms2_update_category](API.md#modx_ms2_update_category) | Actualiza una categoría de miniShop2. | Modifica el sitio |
| [modx_ms2_list_orders](API.md#modx_ms2_list_orders) | Enumera los pedidos de miniShop2 (msOrder), filtrables por status/customer/context/rango de fechas. | Modifica el sitio |
| [modx_ms2_get_order](API.md#modx_ms2_get_order) | Obtiene un pedido de miniShop2 por id (con items/customer). | Modifica el sitio |
| [modx_ms2_update_order](API.md#modx_ms2_update_order) | Actualiza un pedido de miniShop2, normalmente para cambiar su estado (activa la lógica de cambio de estado de ms2), entrega o pago. | Modifica el sitio |

### MIGX

| Herramienta | Descripción traducida | Tipo de operación |
| --- | --- | --- |
| [modx_migx_list_configs](API.md#modx_migx_list_configs) | Enumera las configuraciones MIGX (migxConfig): id, name, category, published. | Modifica el sitio |
| [modx_migx_get_config](API.md#modx_migx_get_config) | Obtiene una configuración MIGX completa por id (formtabs, columns, buttons, filters, permissions, como cadenas JSON almacenadas). | Modifica el sitio |
| [modx_migx_create_config](API.md#modx_migx_create_config) | Crea una configuración MIGX. formtabs/columns/contextmenus/actionbuttons/columnbuttons/filters son cadenas JSON (el mismo formato que MIGX almacena en el administrador). | Modifica el sitio |
| [modx_migx_update_config](API.md#modx_migx_update_config) | Actualiza una configuración MIGX. Solo se modifican los campos que se envían. | Modifica el sitio |
| [modx_migx_delete_config](API.md#modx_migx_delete_config) | Elimina una configuración MIGX por id. | Modifica el sitio |

### Usuarios y derechos de acceso

| Herramienta | Descripción traducida | Tipo de operación |
| --- | --- | --- |
| [modx_flush_permissions](API.md#modx_flush_permissions) | Vacía los permisos de acceso en caché ('Flush Permissions' del administrador) para que se apliquen los cambios de ACL. | Modifica el sitio |
| [modx_list_users](API.md#modx_list_users) | Enumera los usuarios del administrador/web (modUser). | Lectura |
| [modx_get_user](API.md#modx_get_user) | Obtiene un usuario por id. | Lectura |
| [modx_create_user](API.md#modx_create_user) | Crea un usuario. Envíe 'password' para establecerla explícitamente; omítala para generarla automáticamente. También se aceptan campos del perfil (email, fullname, phone…). | Modifica el sitio |
| [modx_update_user](API.md#modx_update_user) | Actualiza un usuario (campos del perfil, estado activo). | Modifica el sitio |
| [modx_delete_user](API.md#modx_delete_user) | Elimina un usuario por id. | Modifica el sitio |
| [modx_list_user_groups](API.md#modx_list_user_groups) | Enumera los grupos de usuarios de MODX (id, name, parent, rank). | Lectura |
| [modx_get_user_group](API.md#modx_get_user_group) | Obtiene un grupo de usuarios por id. | Lectura |
| [modx_create_user_group](API.md#modx_create_user_group) | Crea un grupo de usuarios. | Modifica el sitio |
| [modx_update_user_group](API.md#modx_update_user_group) | Actualiza un grupo de usuarios. | Modifica el sitio |
| [modx_delete_user_group](API.md#modx_delete_user_group) | Elimina un grupo de usuarios por id. | Modifica el sitio |
| [modx_list_user_group_members](API.md#modx_list_user_group_members) | Enumera los miembros de un grupo de usuarios. | Lectura |
| [modx_add_user_to_group](API.md#modx_add_user_to_group) | Añade un usuario a un grupo de usuarios, opcionalmente con un rol. | Modifica el sitio |
| [modx_update_group_member](API.md#modx_update_group_member) | Actualiza la pertenencia de un usuario a un grupo (p. ej. cambia el rol). | Modifica el sitio |
| [modx_remove_user_from_group](API.md#modx_remove_user_from_group) | Retira un usuario de un grupo de usuarios. | Modifica el sitio |
| [modx_list_roles](API.md#modx_list_roles) | Enumera los roles de acceso (id, name, authority). | Lectura |
| [modx_get_role](API.md#modx_get_role) | Obtiene un rol por id. | Lectura |
| [modx_create_role](API.md#modx_create_role) | Crea un rol. | Modifica el sitio |
| [modx_update_role](API.md#modx_update_role) | Actualiza un rol. | Modifica el sitio |
| [modx_delete_role](API.md#modx_delete_role) | Elimina un rol por id. | Modifica el sitio |
| [modx_list_access_policies](API.md#modx_list_access_policies) | Enumera las políticas de acceso (id, name, description, template). | Lectura |
| [modx_get_access_policy](API.md#modx_get_access_policy) | Obtiene una política de acceso por id. | Lectura |
| [modx_create_access_policy](API.md#modx_create_access_policy) | Crea una política de acceso. | Modifica el sitio |
| [modx_update_access_policy](API.md#modx_update_access_policy) | Actualiza una política de acceso (p. ej. su mapa de permisos). | Alto impacto |
| [modx_delete_access_policy](API.md#modx_delete_access_policy) | Elimina una política de acceso por id. | Modifica el sitio |
| [modx_list_access_policy_templates](API.md#modx_list_access_policy_templates) | Enumera las plantillas de políticas de acceso. | Lectura |
| [modx_get_access_policy_template](API.md#modx_get_access_policy_template) | Obtiene una plantilla de política de acceso por id. | Lectura |
| [modx_create_access_policy_template](API.md#modx_create_access_policy_template) | Crea una plantilla de política de acceso. | Modifica el sitio |
| [modx_update_access_policy_template](API.md#modx_update_access_policy_template) | Actualiza una plantilla de política de acceso. | Modifica el sitio |
| [modx_delete_access_policy_template](API.md#modx_delete_access_policy_template) | Elimina una plantilla de política de acceso por id. | Modifica el sitio |
| [modx_list_access_permissions](API.md#modx_list_access_permissions) | Enumera los permisos definidos por una plantilla de política (catálogo de solo lectura). | Lectura |
| [modx_list_resource_groups](API.md#modx_list_resource_groups) | Enumera los grupos de recursos (id, name). | Lectura |
| [modx_get_resource_group](API.md#modx_get_resource_group) | Obtiene un grupo de recursos por id. | Lectura |
| [modx_list_resource_group_resources](API.md#modx_list_resource_group_resources) | Enumera los recursos asignados a un grupo de recursos. | Lectura |
| [modx_create_resource_group](API.md#modx_create_resource_group) | Crea un grupo de recursos. | Modifica el sitio |
| [modx_update_resource_group](API.md#modx_update_resource_group) | Actualiza un grupo de recursos. | Modifica el sitio |
| [modx_delete_resource_group](API.md#modx_delete_resource_group) | Elimina un grupo de recursos por id. | Modifica el sitio |
| [modx_assign_resource_to_group](API.md#modx_assign_resource_to_group) | Añade un recurso (documento) a un grupo de recursos. | Modifica el sitio |
| [modx_remove_resource_from_group](API.md#modx_remove_resource_from_group) | Retira un recurso (documento) de un grupo de recursos. | Modifica el sitio |
| [modx_list_context_access](API.md#modx_list_context_access) | Enumera las entradas ACL de acceso a contextos, opcionalmente filtradas por usergroup/context/policy. | Lectura |
| [modx_grant_context_access](API.md#modx_grant_context_access) | Concede a un grupo de usuarios acceso a un contexto con una política y una autoridad mínima de rol. | Modifica el sitio |
| [modx_update_context_access](API.md#modx_update_context_access) | Actualiza una entrada ACL de acceso a contexto por id. | Modifica el sitio |
| [modx_revoke_context_access](API.md#modx_revoke_context_access) | Elimina una entrada ACL de acceso a contexto por id. | Modifica el sitio |
| [modx_list_resourcegroup_access](API.md#modx_list_resourcegroup_access) | Enumera las entradas ACL de acceso a grupos de recursos. | Lectura |
| [modx_grant_resourcegroup_access](API.md#modx_grant_resourcegroup_access) | Concede a un grupo de usuarios acceso a un grupo de recursos con una política y una autoridad mínima de rol. | Modifica el sitio |
| [modx_update_resourcegroup_access](API.md#modx_update_resourcegroup_access) | Actualiza una entrada ACL de acceso a grupo de recursos por id. | Modifica el sitio |
| [modx_revoke_resourcegroup_access](API.md#modx_revoke_resourcegroup_access) | Elimina una entrada ACL de acceso a grupo de recursos por id. | Modifica el sitio |

### ClientConfig

| Herramienta | Descripción traducida | Tipo de operación |
| --- | --- | --- |
| [modx_clientconfig_list_settings](API.md#modx_clientconfig_list_settings) | Enumera los ajustes de ClientConfig, incluidos los valores específicos de cada contexto. | Modifica el sitio |
| [modx_clientconfig_get_setting](API.md#modx_clientconfig_get_setting) | Obtiene un ajuste de ClientConfig por id o clave. | Modifica el sitio |
| [modx_clientconfig_create_setting](API.md#modx_clientconfig_create_setting) | Crea un ajuste de ClientConfig. | Modifica el sitio |
| [modx_clientconfig_update_setting](API.md#modx_clientconfig_update_setting) | Actualiza un ajuste de ClientConfig por id o clave. | Modifica el sitio |
| [modx_clientconfig_delete_setting](API.md#modx_clientconfig_delete_setting) | Elimina un ajuste de ClientConfig por id o clave. | Modifica el sitio |

### Conjuntos de propiedades

| Herramienta | Descripción traducida | Tipo de operación |
| --- | --- | --- |
| [modx_list_property_sets](API.md#modx_list_property_sets) | Enumera los conjuntos de propiedades (modPropertySet): id, name, description, category. | Lectura |
| [modx_get_property_set](API.md#modx_get_property_set) | Obtiene un conjunto de propiedades por id (incluidas sus propiedades). | Lectura |
| [modx_create_property_set](API.md#modx_create_property_set) | Crea un conjunto de propiedades. 'properties' es un objeto JSON de definiciones de propiedades. | Modifica el sitio |
| [modx_update_property_set](API.md#modx_update_property_set) | Actualiza un conjunto de propiedades. | Modifica el sitio |
| [modx_delete_property_set](API.md#modx_delete_property_set) | Elimina un conjunto de propiedades por id. | Modifica el sitio |
| [modx_assign_property_set](API.md#modx_assign_property_set) | Asocia un conjunto de propiedades a un elemento. Identifique el elemento mediante su id + element_class (p. ej. 'modSnippet') o element_type ('snippet'/'chunk'/'template'/'plugin'/'tv'). | Modifica el sitio |
| [modx_unassign_property_set](API.md#modx_unassign_property_set) | Desasocia un conjunto de propiedades de un elemento (la misma identificación que al asociarlo). | Modifica el sitio |

### Contextos y ajustes de contexto

| Herramienta | Descripción traducida | Tipo de operación |
| --- | --- | --- |
| [modx_list_contexts](API.md#modx_list_contexts) | Enumera los contextos (modContext): key, name, description, rank. | Lectura |
| [modx_get_context](API.md#modx_get_context) | Obtiene un contexto por su clave. | Lectura |
| [modx_create_context](API.md#modx_create_context) | Crea un contexto. 'key' es el identificador único (p. ej. 'mobile'). | Modifica el sitio |
| [modx_update_context](API.md#modx_update_context) | Actualiza un contexto (name/description/rank). 'settings' puede ser una matriz JSON de ajustes de contexto (avanzado). | Modifica el sitio |
| [modx_delete_context](API.md#modx_delete_context) | Elimina un contexto por su clave. | Alto impacto |
| [modx_list_context_settings](API.md#modx_list_context_settings) | Enumera los ajustes de un contexto (modContextSetting). | Lectura |
| [modx_get_context_setting](API.md#modx_get_context_setting) | Obtiene un ajuste de contexto (por context_key + key). | Lectura |
| [modx_create_context_setting](API.md#modx_create_context_setting) | Crea un ajuste a nivel de contexto (sobrescribe el ajuste del sistema para ese contexto). | Modifica el sitio |
| [modx_update_context_setting](API.md#modx_update_context_setting) | Actualiza un ajuste de contexto (por context_key + key). | Modifica el sitio |
| [modx_delete_context_setting](API.md#modx_delete_context_setting) | Elimina un ajuste de contexto (por context_key + key). | Modifica el sitio |

### Paquetes y proveedores

| Herramienta | Descripción traducida | Tipo de operación |
| --- | --- | --- |
| [modx_install_package](API.md#modx_install_package) | Instala un paquete de transporte de un proveedor (modx.com de forma predeterminada) por nombre, p. ej. {package:'MIGX'}. | Alto impacto |
| [modx_uninstall_package](API.md#modx_uninstall_package) | Desinstala un paquete de transporte por su firma (p. ej. 'migx-2.13.0-pl'). | Alto impacto |
| [modx_list_providers](API.md#modx_list_providers) | Enumera los proveedores de transporte (modx.com y personalizados, p. ej. modstore.pro): id, name, service_url. | Lectura |
| [modx_search_packages](API.md#modx_search_packages) | Busca en el catálogo de un proveedor (utilícelo antes de install_package). | Lectura |
| [modx_create_provider](API.md#modx_create_provider) | Añade un proveedor de transporte (p. ej. modstore.pro). | Modifica el sitio |
| [modx_update_provider](API.md#modx_update_provider) | Actualiza un proveedor de transporte. | Modifica el sitio |
| [modx_delete_provider](API.md#modx_delete_provider) | Elimina un proveedor de transporte por id. | Modifica el sitio |

### Espacios de nombres

| Herramienta | Descripción traducida | Tipo de operación |
| --- | --- | --- |
| [modx_list_namespaces](API.md#modx_list_namespaces) | Enumera los espacios de nombres (modNamespace): name, path, assets_path. | Lectura |
| [modx_create_namespace](API.md#modx_create_namespace) | Crea un espacio de nombres. | Modifica el sitio |
| [modx_update_namespace](API.md#modx_update_namespace) | Actualiza un espacio de nombres (por nombre). | Modifica el sitio |
| [modx_delete_namespace](API.md#modx_delete_namespace) | Elimina un espacio de nombres por nombre ('core' no se puede eliminar). | Modifica el sitio |

### Léxico y traducciones

| Herramienta | Descripción traducida | Tipo de operación |
| --- | --- | --- |
| [modx_list_lexicon_entries](API.md#modx_list_lexicon_entries) | Enumera las entradas del léxico, filtradas por namespace + topic + language (y una búsqueda opcional). | Lectura |
| [modx_list_lexicon_topics](API.md#modx_list_lexicon_topics) | Enumera los temas del léxico para un namespace/language. | Lectura |
| [modx_set_lexicon_entry](API.md#modx_set_lexicon_entry) | Crea o sobrescribe una entrada del léxico (sobrescritura en la base de datos del valor del archivo). | Modifica el sitio |
| [modx_revert_lexicon_entry](API.md#modx_revert_lexicon_entry) | Revierte una entrada del léxico: elimina su sobrescritura en la base de datos para que se aplique el valor del archivo. | Modifica el sitio |

### Mantenimiento y diagnóstico

| Herramienta | Descripción traducida | Tipo de operación |
| --- | --- | --- |
| [modx_get_site_state](API.md#modx_get_site_state) | Lee la revisión del sitio cuya fuente de autoridad es el conector y la capacidad de precondiciones atómicas utilizada por herramientas externas. | Lectura |
| [modx_describe_object](API.md#modx_describe_object) | Introspección del esquema: enumera los campos de una clase xPDO (nombre + tipo php/db, null, default) y su clave primaria, para utilizar nombres REALES de campos en lugar de adivinarlos. | Lectura |
| [modx_read_error_log](API.md#modx_read_error_log) | Lee el final del registro de errores de MODX (core/cache/logs/error.log) para diagnóstico. | Lectura |
| [modx_refresh_uris](API.md#modx_refresh_uris) | Regenera todas las URI de recursos (ejecútelo después de cambios masivos de alias/estructura que hayan dejado URI obsoletas). | Modifica el sitio |
| [modx_remove_locks](API.md#modx_remove_locks) | Elimina bloqueos de edición obsoletos del administrador (cuando un elemento/recurso figura bloqueado por una sesión terminada). | Modifica el sitio |
| [modx_project_overview](API.md#modx_project_overview) | Ofrece una visión general de todo el sitio instalado en UNA llamada compacta y de bajo coste. | Lectura |
| [modx_system_info](API.md#modx_system_info) | Información de entorno/diagnóstico: versión de MODX, versión de modxMCP, versión de PHP, tipo de base de datos y rutas clave. | Lectura |
| [modx_regenerate_token](API.md#modx_regenerate_token) | Rota modxmcp.api_token a un nuevo valor aleatorio y lo devuelve. | Alto impacto |
| [modx_help](API.md#modx_help) | Documentación integrada. Llame sin argumentos para enumerar temas; envíe {topic} para obtener una guía (p. ej. 'tv_input_types', 'study_component', 'minishop2', 'migx', 'acl', 'getting_started'). Lea el tema pertinente antes de tareas desconocidas o para aprender a utilizar un componente recién instalado. | Lectura |
| [modx_list_actions](API.md#modx_list_actions) | Enumera los nombres de acciones que admite esta compilación del servidor modxMCP, agrupados por área. | Lectura |
| [modx_clear_cache](API.md#modx_clear_cache) | Actualiza la caché de MODX. Envíe partitions (p. ej. ['resource','context_settings']) para actualizar solo esas particiones; omítalo para una actualización completa. | Modifica el sitio |
| [modx_read_audit_log](API.md#modx_read_audit_log) | Lee el registro de auditoría de escritura de modxMCP (lo más reciente al final). | Lectura |
| [modx_run_processor](API.md#modx_run_processor) | Ejecuta directamente CUALQUIER procesador MODX (vía de acceso directo). | Alto impacto |
