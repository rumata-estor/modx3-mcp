# MODX MCP — capacidades y catálogo de herramientas

[English](CAPABILITIES.md) · [Русский](CAPABILITIES.ru.md) · [Español](CAPABILITIES.es.md) · [Deutsch](CAPABILITIES.de.md) · [Français](CAPABILITIES.fr.md) · [Українська](CAPABILITIES.uk.md) · [Қазақша](CAPABILITIES.kk.md) · [Nederlands](CAPABILITIES.nl.md) · [Polski](CAPABILITIES.pl.md) · [Instalación](INSTALL.es.md) · [Referencia de la API](API.es.md)

**MODX MCP 1.2.4 · 192 public tools · 19 functional groups · MODX Revolution 2.8.x / 3.x**

MODX MCP proporciona a un cliente MCP con capacidades de IA operaciones con nombre para trabajar con datos y elementos MODX, en lugar de exigir que cada tarea se exprese como SQL sin procesar o comandos del sistema de archivos. Admite la inspección del sitio, la edición de contenido y código, valores de TV, paquetes, administración de acceso y determinados complementos de terceros. La lista real de herramientas depende de la compilación instalada y de las capacidades habilitadas.

Esta guía responde a **«¿Qué puedo hacer con MODX MCP?»**. Para conocer todos los campos, tipos, enumeraciones y detalles de integración, consulte la [referencia de la API](API.es.md). Para conectarse, comience por [Instalación](INSTALL.es.md).

**Las etiquetas de impacto son explicaciones, no comprobaciones de permisos.** «Lectura» aún puede devolver datos confidenciales, como valores de configuración. «Alto impacto» advierte de que un comando puede impedir que funcione el sitio o comprometer su seguridad si se utiliza incorrectamente.

## Índice de herramientas

| Grupo | Herramientas |
| --- | ---: |
| [Elementos y recursos](#elementos-y-recursos) | 15 |
| [TV de recursos](#tv-de-recursos) | 4 |
| [Tipos de campo de TV](#tipos-de-campo-de-tv) | 2 |
| [Ajustes del sistema](#ajustes-del-sistema) | 5 |
| [Orígenes multimedia y archivos](#orígenes-multimedia-y-archivos) | 13 |
| [Componentes y archivos](#componentes-y-archivos) | 4 |
| [Búsqueda de código y dependencias](#búsqueda-de-código-y-dependencias) | 5 |
| [VersionX](#versionx) | 3 |
| [VirtualPage](#virtualpage) | 17 |
| [miniShop2](#minishop2) | 22 |
| [MIGX](#migx) | 5 |
| [Usuarios y derechos de acceso](#usuarios-y-derechos-de-acceso) | 47 |
| [ClientConfig](#clientconfig) | 5 |
| [Conjuntos de propiedades](#conjuntos-de-propiedades) | 7 |
| [Contextos y ajustes de contexto](#contextos-y-ajustes-de-contexto) | 10 |
| [Paquetes y proveedores](#paquetes-y-proveedores) | 7 |
| [Espacios de nombres](#espacios-de-nombres) | 4 |
| [Léxico y traducciones](#léxico-y-traducciones) | 4 |
| [Mantenimiento y diagnóstico](#mantenimiento-y-diagnóstico) | 13 |
| **Total** | **192** |

## Elementos y recursos

Gestione páginas, plantillas, chunks, snippets, plugins, TV y categorías como objetos MODX.

| Herramienta | Qué hace | Impacto |
| --- | --- | --- |
| `modx_list_elements` | Enumera elementos MODX de un tipo (id + name). | Lectura |
| `modx_make_static` | Convierte un chunk/snippet/template/plugin en un archivo estático bajo core/elements/ (escribe el archivo y establece static=1 + source=Filesystem). | Alto impacto |
| `modx_get_element` | Lee un elemento MODX (devuelve sus campos + el código completo). | Lectura |
| `modx_update_element_property` | Modifica de forma segura una propiedad predeterminada existente mediante una comprobación CAS del valor esperado; nunca reemplaza el código ni otras propiedades. | Modifica el sitio |
| `modx_update_element` | Actualiza un elemento MODX REEMPLAZANDO todo su contenido (envíe el contenido nuevo completo). | Alto impacto |
| `modx_create_element` | Crea un nuevo elemento MODX. | Alto impacto |
| `modx_delete_element` | Elimina un elemento MODX. Ejecute PRIMERO con dry_run:true: devuelve lo que se eliminaría y dónde sigue habiendo referencias al elemento (para recursos: número de recursos hijos), sin eliminar nada. Revíselo antes de la eliminación real. | Alto impacto |
| `modx_view_element` | Muestra un chunk/snippet/template/plugin como líneas NUMERADAS (como cat -n), opcionalmente limitadas por start_line/end_line. | Modifica el sitio |
| `modx_edit_element_lines` | Edita un chunk/snippet/template/plugin POR LÍNEAS, enviando solo las líneas modificadas (no hace falta reenviar todo el elemento). | Alto impacto |
| `modx_bulk_resources` | Aplica UNA operación a muchos recursos a la vez: publish, unpublish, set_template (requiere template), move (requiere parent_to y/o context_to) o delete. | Alto impacto |
| `modx_duplicate_resource` | Duplica un recurso. id = recurso de origen; opcionales: name (nuevo pagetitle), duplicate_children (bool), published_mode (preserve\|publish\|unpublish). | Modifica el sitio |
| `modx_duplicate_element` | Duplica un elemento (chunk/snippet/template/plugin/tv). type + id; name opcional para la copia. | Modifica el sitio |
| `modx_undelete_resource` | Restaura un recurso eliminado de forma lógica desde la papelera (revierte su eliminación). id = id del recurso. | Modifica el sitio |
| `modx_empty_recycle_bin` | Purga permanentemente TODOS los recursos de la papelera (eliminados de forma lógica). | Alto impacto |
| `modx_reorder_resources` | Reordena recursos en el árbol estableciendo menuindex (y, opcionalmente, parent). items = [{id, menuindex, parent?}], aplicado por recurso. | Modifica el sitio |

## TV de recursos

Lea o actualice campos personalizados de recursos e inspeccione valores de TV guardados.

| Herramienta | Qué hace | Impacto |
| --- | --- | --- |
| `modx_get_resource_tvs` | Obtiene los valores de las variables de plantilla (TV) de un recurso. | Lectura |
| `modx_update_resource_tvs` | Actualiza los valores de las variables de plantilla (TV) de un recurso. | Modifica el sitio |
| `modx_list_tv_values` | Enumera los valores almacenados explícitamente para una TV en todos los recursos, incluidos valores obsoletos en recursos cuya plantilla actual puede haber dejado de utilizar esa TV. | Lectura |
| `modx_clear_tv_values` | Previsualiza o borra todos los valores almacenados explícitamente para una TV. | Modifica el sitio |

## Tipos de campo de TV

Descubra los tipos de campo de TV disponibles y elija uno según sus necesidades.

| Herramienta | Qué hace | Impacto |
| --- | --- | --- |
| `modx_suggest_tv_type` | ¿No sabe qué tipo de entrada de TV se ajusta a una necesidad? Descríbala (en inglés o ruso) y obtenga candidatos field_types ordenados con sus motivos + un esquema create_element listo para editar para la mejor opción (con las claves adicionales que necesita ese tipo). | Lectura |
| `modx_list_tv_input_types` | Enumera los tipos de entrada (widget) de TV disponibles en este sitio. | Lectura |

## Ajustes del sistema

Inspeccione y edite la configuración que puede afectar a todo el sitio.

| Herramienta | Qué hace | Impacto |
| --- | --- | --- |
| `modx_list_system_settings` | Enumera los ajustes del sistema de MODX. | Lectura |
| `modx_get_system_setting` | Obtiene un ajuste del sistema de MODX por su clave. | Lectura |
| `modx_create_system_setting` | Crea un ajuste del sistema de MODX. | Modifica el sitio |
| `modx_update_system_setting` | Actualiza un ajuste del sistema de MODX por su clave. | Alto impacto |
| `modx_delete_system_setting` | Elimina un ajuste del sistema de MODX por su clave. | Alto impacto |

## Orígenes multimedia y archivos

Explore orígenes multimedia y gestione los archivos y carpetas expuestos a través de ellos.

| Herramienta | Qué hace | Impacto |
| --- | --- | --- |
| `modx_list_media_sources` | Enumera los orígenes multimedia de MODX. | Lectura |
| `modx_get_media_source` | Obtiene un origen multimedia de MODX. | Lectura |
| `modx_create_media_source` | Crea un origen multimedia (de archivos). class_key tiene como valor predeterminado sources.modFileMediaSource. 'properties' es un mapa {name:value} de parámetros del origen (p. ej. {"basePath":"assets/files/","baseUrl":"assets/files/"}) que se combina con el origen. | Modifica el sitio |
| `modx_update_media_source` | Actualiza un origen multimedia. 'properties' es un mapa {name:value} que se combina con los parámetros del origen (se conservan los demás); utilícelo para establecer basePath/baseUrl/etc. | Modifica el sitio |
| `modx_delete_media_source` | Elimina un origen multimedia por id. | Modifica el sitio |
| `modx_list_media_source_files` | Enumera archivos y carpetas dentro de un origen multimedia. | Lectura |
| `modx_read_media_source_file` | Lee un archivo dentro de un origen multimedia. | Lectura |
| `modx_create_media_file` | Crea un archivo dentro de un origen multimedia (escribe contenido). source = id o nombre del origen, path = directorio de destino (relativo a la base del origen), name = nombre del archivo, content = cuerpo del archivo. | Modifica el sitio |
| `modx_update_media_file` | Sobrescribe el contenido de un archivo dentro de un origen multimedia. source = id/nombre, path = ruta del archivo dentro del origen, content = cuerpo nuevo. | Modifica el sitio |
| `modx_delete_media_file` | Elimina un archivo dentro de un origen multimedia. source = id/nombre, path = ruta del archivo dentro del origen. | Alto impacto |
| `modx_rename_media_file` | Renombra un archivo dentro de un origen multimedia. source = id/nombre, path = ruta actual del archivo, new_name = nuevo nombre del archivo. | Modifica el sitio |
| `modx_create_media_folder` | Crea una carpeta dentro de un origen multimedia. source = id/nombre, parent = directorio padre (raíz de forma predeterminada), name = nombre de la carpeta. | Modifica el sitio |
| `modx_delete_media_folder` | Elimina una carpeta (y su contenido) dentro de un origen multimedia. source = id/nombre, path = ruta de la carpeta dentro del origen. | Alto impacto |

## Componentes y archivos

Descubra los complementos instalados e inspeccione sus archivos permitidos.

| Herramienta | Qué hace | Impacto |
| --- | --- | --- |
| `modx_list_installed_components` | Enumera los componentes MODX instalados en core/components y assets/components. | Lectura |
| `modx_get_component_files` | Enumera archivos y carpetas dentro de un componente MODX instalado. | Lectura |
| `modx_read_component_file` | Lee un archivo de un componente MODX instalado. | Lectura |
| `modx_check_integrations` | Informa de qué complementos populares de MODX (miniShop2, MIGX, pdoTools, Tickets, etc.) están instalados en este sitio y qué puede hacer modxMCP con cada uno. | Lectura |

## Búsqueda de código y dependencias

Localice detalles de implementación, referencias y relaciones entre los elementos del sitio.

| Herramienta | Qué hace | Impacto |
| --- | --- | --- |
| `modx_search_code` | Busca texto completo en el CONTENIDO (y los nombres) de elementos y recursos. | Lectura |
| `modx_find_usages` | Encuentra dónde se utiliza un elemento: coincidencias de su nombre en el contenido de todo el código y, si es una plantilla, los recursos que tienen asignada esa plantilla. | Lectura |
| `modx_list_resources` | Enumera recursos (el árbol de contenido), opcionalmente filtrados por padre, contexto, estado de eliminación o una consulta pagetitle/alias/uri. | Lectura |
| `modx_replace_across` | Busca y reemplaza en todo el sitio dentro de elementos de código (chunk/snippet/template/plugin): encuentra todos los elementos cuyo CONTENIDO contiene find y reemplaza TODAS las apariciones por replacement, en UNA llamada (sin lecturas/escrituras por elemento por su parte). | Alto impacto |
| `modx_dependency_graph` | Representa cómo SE CONECTAN los elementos del sitio, en una llamada: qué plantilla incorpora qué chunk/snippet/TV, qué snippet renderiza qué chunk y dónde está asociada cada TV. | Lectura |

## VersionX

Inspeccione o restaure revisiones históricas cuando VersionX esté instalado.

| Herramienta | Qué hace | Impacto |
| --- | --- | --- |
| `modx_versionx_list_versions` | Enumera las versiones de VersionX de un recurso, chunk, snippet, plantilla, plugin o TV de MODX. | Lectura |
| `modx_versionx_get_version` | Lee el contenido de una versión de VersionX antes de decidir si se revierte a ella. | Modifica el sitio |
| `modx_versionx_revert_version` | Revierte un objeto MODX a una versión específica de VersionX. | Modifica el sitio |

## VirtualPage

Gestione vinculaciones de eventos, manejadores y rutas proporcionados por VirtualPage.

| Herramienta | Qué hace | Impacto |
| --- | --- | --- |
| `modx_virtualpage_list_events` | Enumera los eventos de VirtualPage que vinculan grupos de rutas con eventos MODX. | Modifica el sitio |
| `modx_virtualpage_get_event` | Lee un evento de VirtualPage por id o nombre, incluidas sus rutas. | Modifica el sitio |
| `modx_virtualpage_create_event` | Crea un evento de VirtualPage, normalmente OnPageNotFound u OnHandleRequest. | Modifica el sitio |
| `modx_virtualpage_update_event` | Actualiza un evento de VirtualPage por id o nombre. | Modifica el sitio |
| `modx_virtualpage_list_handlers` | Enumera los manejadores de VirtualPage que renderizan recursos, snippets, chunks o recursos dinámicos. | Modifica el sitio |
| `modx_virtualpage_get_handler` | Lee un manejador de VirtualPage por id o nombre, incluidas las rutas que lo utilizan. | Modifica el sitio |
| `modx_virtualpage_create_handler` | Crea un manejador de VirtualPage. type puede ser resource, snippet, chunk, dynamic_resource/template o 0..3. | Modifica el sitio |
| `modx_virtualpage_update_handler` | Actualiza un manejador de VirtualPage por id o nombre. | Modifica el sitio |
| `modx_virtualpage_list_routes` | Enumera las rutas de VirtualPage con los nombres de sus eventos y manejadores. | Modifica el sitio |
| `modx_virtualpage_get_route` | Lee una ruta de VirtualPage por id, o por route y method opcional. | Modifica el sitio |
| `modx_virtualpage_create_route` | Crea una ruta de VirtualPage y vincula su plugin de evento si es necesario. | Modifica el sitio |
| `modx_virtualpage_update_route` | Actualiza una ruta de VirtualPage por id. | Modifica el sitio |
| `modx_virtualpage_resolve_route` | Simula la coincidencia de rutas de VirtualPage y devuelve el manejador junto con los marcadores vp.*. | Lectura |
| `modx_virtualpage_delete_event` | Elimina un evento de VirtualPage por id o nombre (también elimina sus rutas, según el esquema de VP). | Modifica el sitio |
| `modx_virtualpage_delete_handler` | Elimina un manejador de VirtualPage por id o nombre. | Modifica el sitio |
| `modx_virtualpage_delete_route` | Elimina una ruta de VirtualPage por id o nombre. | Modifica el sitio |
| `modx_virtualpage_clear_cache` | Borra los archivos de rutas/caché de VirtualPage y actualiza la caché de MODX. | Modifica el sitio |

## miniShop2

Trabaje con opciones de la tienda, categorías, relaciones entre productos y pedidos.

| Herramienta | Qué hace | Impacto |
| --- | --- | --- |
| `modx_ms2_list_option_types` | Enumera los tipos de campo disponibles para opciones de producto de miniShop2. | Modifica el sitio |
| `modx_ms2_list_options` | Enumera las opciones de producto de miniShop2 de la pestaña de opciones de los ajustes. | Modifica el sitio |
| `modx_ms2_get_option` | Lee una opción de producto de miniShop2 por id. | Modifica el sitio |
| `modx_ms2_create_option` | Crea una opción de producto de miniShop2 en las opciones de los ajustes. | Modifica el sitio |
| `modx_ms2_update_option` | Actualiza una opción de producto de miniShop2 en las opciones de los ajustes. | Modifica el sitio |
| `modx_ms2_assign_option_to_category` | Asigna una opción existente de miniShop2 a una msCategory. | Modifica el sitio |
| `modx_ms2_get_product_options` | Lee los valores de opciones de miniShop2 asignados a un producto. | Modifica el sitio |
| `modx_ms2_update_product_options` | Crea, actualiza o elimina valores de opciones de miniShop2 para un producto. | Modifica el sitio |
| `modx_ms2_list_link_types` | Enumera los tipos de vínculo de miniShop2 (msLink): id, type, name, description. | Modifica el sitio |
| `modx_ms2_get_link_type` | Obtiene un tipo de vínculo de miniShop2 por id. | Modifica el sitio |
| `modx_ms2_create_link_type` | Crea un tipo de vínculo de miniShop2. 'type' es la clase de relación que determina cómo se vinculan los productos. | Modifica el sitio |
| `modx_ms2_update_link_type` | Actualiza un tipo de vínculo de miniShop2. | Modifica el sitio |
| `modx_ms2_delete_link_type` | Elimina un tipo de vínculo de miniShop2 por id. | Modifica el sitio |
| `modx_ms2_list_product_links` | Enumera los vínculos entre productos (msProductLink), opcionalmente para un solo producto. | Modifica el sitio |
| `modx_ms2_create_product_link` | Vincula dos productos bajo un tipo de vínculo. La clase de relación del tipo de vínculo determina si también se crea el vínculo inverso. | Modifica el sitio |
| `modx_ms2_delete_product_link` | Elimina un vínculo de producto (por tipo de vínculo + master + slave). | Modifica el sitio |
| `modx_ms2_list_categories` | Enumera las categorías de miniShop2 (msCategory). | Modifica el sitio |
| `modx_ms2_create_category` | Crea una categoría de miniShop2 (recurso msCategory). | Modifica el sitio |
| `modx_ms2_update_category` | Actualiza una categoría de miniShop2. | Modifica el sitio |
| `modx_ms2_list_orders` | Enumera los pedidos de miniShop2 (msOrder), filtrables por status/customer/context/rango de fechas. | Modifica el sitio |
| `modx_ms2_get_order` | Obtiene un pedido de miniShop2 por id (con items/customer). | Modifica el sitio |
| `modx_ms2_update_order` | Actualiza un pedido de miniShop2, normalmente para cambiar su estado (activa la lógica de cambio de estado de ms2), entrega o pago. | Modifica el sitio |

## MIGX

Lea o modifique configuraciones MIGX cuando el complemento esté instalado.

| Herramienta | Qué hace | Impacto |
| --- | --- | --- |
| `modx_migx_list_configs` | Enumera las configuraciones MIGX (migxConfig): id, name, category, published. | Modifica el sitio |
| `modx_migx_get_config` | Obtiene una configuración MIGX completa por id (formtabs, columns, buttons, filters, permissions, como cadenas JSON almacenadas). | Modifica el sitio |
| `modx_migx_create_config` | Crea una configuración MIGX. formtabs/columns/contextmenus/actionbuttons/columnbuttons/filters son cadenas JSON (el mismo formato que MIGX almacena en el administrador). | Modifica el sitio |
| `modx_migx_update_config` | Actualiza una configuración MIGX. Solo se modifican los campos que se envían. | Modifica el sitio |
| `modx_migx_delete_config` | Elimina una configuración MIGX por id. | Modifica el sitio |

## Usuarios y derechos de acceso

Administre usuarios, grupos, roles, políticas y permisos de recursos/contextos.

| Herramienta | Qué hace | Impacto |
| --- | --- | --- |
| `modx_flush_permissions` | Vacía los permisos de acceso en caché ('Flush Permissions' del administrador) para que se apliquen los cambios de ACL. | Modifica el sitio |
| `modx_list_users` | Enumera los usuarios del administrador/web (modUser). | Lectura |
| `modx_get_user` | Obtiene un usuario por id. | Lectura |
| `modx_create_user` | Crea un usuario. Envíe 'password' para establecerla explícitamente; omítala para generarla automáticamente. También se aceptan campos del perfil (email, fullname, phone…). | Modifica el sitio |
| `modx_update_user` | Actualiza un usuario (campos del perfil, estado activo). | Modifica el sitio |
| `modx_delete_user` | Elimina un usuario por id. | Modifica el sitio |
| `modx_list_user_groups` | Enumera los grupos de usuarios de MODX (id, name, parent, rank). | Lectura |
| `modx_get_user_group` | Obtiene un grupo de usuarios por id. | Lectura |
| `modx_create_user_group` | Crea un grupo de usuarios. | Modifica el sitio |
| `modx_update_user_group` | Actualiza un grupo de usuarios. | Modifica el sitio |
| `modx_delete_user_group` | Elimina un grupo de usuarios por id. | Modifica el sitio |
| `modx_list_user_group_members` | Enumera los miembros de un grupo de usuarios. | Lectura |
| `modx_add_user_to_group` | Añade un usuario a un grupo de usuarios, opcionalmente con un rol. | Modifica el sitio |
| `modx_update_group_member` | Actualiza la pertenencia de un usuario a un grupo (p. ej. cambia el rol). | Modifica el sitio |
| `modx_remove_user_from_group` | Retira un usuario de un grupo de usuarios. | Modifica el sitio |
| `modx_list_roles` | Enumera los roles de acceso (id, name, authority). | Lectura |
| `modx_get_role` | Obtiene un rol por id. | Lectura |
| `modx_create_role` | Crea un rol. | Modifica el sitio |
| `modx_update_role` | Actualiza un rol. | Modifica el sitio |
| `modx_delete_role` | Elimina un rol por id. | Modifica el sitio |
| `modx_list_access_policies` | Enumera las políticas de acceso (id, name, description, template). | Lectura |
| `modx_get_access_policy` | Obtiene una política de acceso por id. | Lectura |
| `modx_create_access_policy` | Crea una política de acceso. | Modifica el sitio |
| `modx_update_access_policy` | Actualiza una política de acceso (p. ej. su mapa de permisos). | Alto impacto |
| `modx_delete_access_policy` | Elimina una política de acceso por id. | Modifica el sitio |
| `modx_list_access_policy_templates` | Enumera las plantillas de políticas de acceso. | Lectura |
| `modx_get_access_policy_template` | Obtiene una plantilla de política de acceso por id. | Lectura |
| `modx_create_access_policy_template` | Crea una plantilla de política de acceso. | Modifica el sitio |
| `modx_update_access_policy_template` | Actualiza una plantilla de política de acceso. | Modifica el sitio |
| `modx_delete_access_policy_template` | Elimina una plantilla de política de acceso por id. | Modifica el sitio |
| `modx_list_access_permissions` | Enumera los permisos definidos por una plantilla de política (catálogo de solo lectura). | Lectura |
| `modx_list_resource_groups` | Enumera los grupos de recursos (id, name). | Lectura |
| `modx_get_resource_group` | Obtiene un grupo de recursos por id. | Lectura |
| `modx_list_resource_group_resources` | Enumera los recursos asignados a un grupo de recursos. | Lectura |
| `modx_create_resource_group` | Crea un grupo de recursos. | Modifica el sitio |
| `modx_update_resource_group` | Actualiza un grupo de recursos. | Modifica el sitio |
| `modx_delete_resource_group` | Elimina un grupo de recursos por id. | Modifica el sitio |
| `modx_assign_resource_to_group` | Añade un recurso (documento) a un grupo de recursos. | Modifica el sitio |
| `modx_remove_resource_from_group` | Retira un recurso (documento) de un grupo de recursos. | Modifica el sitio |
| `modx_list_context_access` | Enumera las entradas ACL de acceso a contextos, opcionalmente filtradas por usergroup/context/policy. | Lectura |
| `modx_grant_context_access` | Concede a un grupo de usuarios acceso a un contexto con una política y una autoridad mínima de rol. | Modifica el sitio |
| `modx_update_context_access` | Actualiza una entrada ACL de acceso a contexto por id. | Modifica el sitio |
| `modx_revoke_context_access` | Elimina una entrada ACL de acceso a contexto por id. | Modifica el sitio |
| `modx_list_resourcegroup_access` | Enumera las entradas ACL de acceso a grupos de recursos. | Lectura |
| `modx_grant_resourcegroup_access` | Concede a un grupo de usuarios acceso a un grupo de recursos con una política y una autoridad mínima de rol. | Modifica el sitio |
| `modx_update_resourcegroup_access` | Actualiza una entrada ACL de acceso a grupo de recursos por id. | Modifica el sitio |
| `modx_revoke_resourcegroup_access` | Elimina una entrada ACL de acceso a grupo de recursos por id. | Modifica el sitio |

## ClientConfig

Gestione los ajustes proporcionados por el complemento ClientConfig.

| Herramienta | Qué hace | Impacto |
| --- | --- | --- |
| `modx_clientconfig_list_settings` | Enumera los ajustes de ClientConfig, incluidos los valores específicos de cada contexto. | Modifica el sitio |
| `modx_clientconfig_get_setting` | Obtiene un ajuste de ClientConfig por id o clave. | Modifica el sitio |
| `modx_clientconfig_create_setting` | Crea un ajuste de ClientConfig. | Modifica el sitio |
| `modx_clientconfig_update_setting` | Actualiza un ajuste de ClientConfig por id o clave. | Modifica el sitio |
| `modx_clientconfig_delete_setting` | Elimina un ajuste de ClientConfig por id o clave. | Modifica el sitio |

## Conjuntos de propiedades

Cree y asigne conjuntos de propiedades MODX reutilizables.

| Herramienta | Qué hace | Impacto |
| --- | --- | --- |
| `modx_list_property_sets` | Enumera los conjuntos de propiedades (modPropertySet): id, name, description, category. | Lectura |
| `modx_get_property_set` | Obtiene un conjunto de propiedades por id (incluidas sus propiedades). | Lectura |
| `modx_create_property_set` | Crea un conjunto de propiedades. 'properties' es un objeto JSON de definiciones de propiedades. | Modifica el sitio |
| `modx_update_property_set` | Actualiza un conjunto de propiedades. | Modifica el sitio |
| `modx_delete_property_set` | Elimina un conjunto de propiedades por id. | Modifica el sitio |
| `modx_assign_property_set` | Asocia un conjunto de propiedades a un elemento. Identifique el elemento mediante su id + element_class (p. ej. 'modSnippet') o element_type ('snippet'/'chunk'/'template'/'plugin'/'tv'). | Modifica el sitio |
| `modx_unassign_property_set` | Desasocia un conjunto de propiedades de un elemento (la misma identificación que al asociarlo). | Modifica el sitio |

## Contextos y ajustes de contexto

Gestione metadatos de contextos y configuración de alcance limitado.

| Herramienta | Qué hace | Impacto |
| --- | --- | --- |
| `modx_list_contexts` | Enumera los contextos (modContext): key, name, description, rank. | Lectura |
| `modx_get_context` | Obtiene un contexto por su clave. | Lectura |
| `modx_create_context` | Crea un contexto. 'key' es el identificador único (p. ej. 'mobile'). | Modifica el sitio |
| `modx_update_context` | Actualiza un contexto (name/description/rank). 'settings' puede ser una matriz JSON de ajustes de contexto (avanzado). | Modifica el sitio |
| `modx_delete_context` | Elimina un contexto por su clave. | Alto impacto |
| `modx_list_context_settings` | Enumera los ajustes de un contexto (modContextSetting). | Lectura |
| `modx_get_context_setting` | Obtiene un ajuste de contexto (por context_key + key). | Lectura |
| `modx_create_context_setting` | Crea un ajuste a nivel de contexto (sobrescribe el ajuste del sistema para ese contexto). | Modifica el sitio |
| `modx_update_context_setting` | Actualiza un ajuste de contexto (por context_key + key). | Modifica el sitio |
| `modx_delete_context_setting` | Elimina un ajuste de contexto (por context_key + key). | Modifica el sitio |

## Paquetes y proveedores

Busque en proveedores de paquetes e instale o desinstale complementos MODX.

| Herramienta | Qué hace | Impacto |
| --- | --- | --- |
| `modx_install_package` | Instala un paquete de transporte de un proveedor (modx.com de forma predeterminada) por nombre, p. ej. {package:'MIGX'}. | Alto impacto |
| `modx_uninstall_package` | Desinstala un paquete de transporte por su firma (p. ej. 'migx-2.13.0-pl'). | Alto impacto |
| `modx_list_providers` | Enumera los proveedores de transporte (modx.com y personalizados, p. ej. modstore.pro): id, name, service_url. | Lectura |
| `modx_search_packages` | Busca en el catálogo de un proveedor (utilícelo antes de install_package). | Lectura |
| `modx_create_provider` | Añade un proveedor de transporte (p. ej. modstore.pro). | Modifica el sitio |
| `modx_update_provider` | Actualiza un proveedor de transporte. | Modifica el sitio |
| `modx_delete_provider` | Elimina un proveedor de transporte por id. | Modifica el sitio |

## Espacios de nombres

Registre y gestione espacios de nombres y rutas de complementos.

| Herramienta | Qué hace | Impacto |
| --- | --- | --- |
| `modx_list_namespaces` | Enumera los espacios de nombres (modNamespace): name, path, assets_path. | Lectura |
| `modx_create_namespace` | Crea un espacio de nombres. | Modifica el sitio |
| `modx_update_namespace` | Actualiza un espacio de nombres (por nombre). | Modifica el sitio |
| `modx_delete_namespace` | Elimina un espacio de nombres por nombre ('core' no se puede eliminar). | Modifica el sitio |

## Léxico y traducciones

Lea o sobrescriba cadenas traducidas y temas del léxico.

| Herramienta | Qué hace | Impacto |
| --- | --- | --- |
| `modx_list_lexicon_entries` | Enumera las entradas del léxico, filtradas por namespace + topic + language (y una búsqueda opcional). | Lectura |
| `modx_list_lexicon_topics` | Enumera los temas del léxico para un namespace/language. | Lectura |
| `modx_set_lexicon_entry` | Crea o sobrescribe una entrada del léxico (sobrescritura en la base de datos del valor del archivo). | Modifica el sitio |
| `modx_revert_lexicon_entry` | Revierte una entrada del léxico: elimina su sobrescritura en la base de datos para que se aplique el valor del archivo. | Modifica el sitio |

## Mantenimiento y diagnóstico

Inspeccione metadatos, estado y registros del sitio, y realice tareas de mantenimiento.

| Herramienta | Qué hace | Impacto |
| --- | --- | --- |
| `modx_get_site_state` | Lee la revisión del sitio cuya fuente de autoridad es el conector y la capacidad de precondiciones atómicas utilizada por herramientas externas. | Lectura |
| `modx_describe_object` | Introspección del esquema: enumera los campos de una clase xPDO (nombre + tipo php/db, null, default) y su clave primaria, para utilizar nombres REALES de campos en lugar de adivinarlos. | Lectura |
| `modx_read_error_log` | Lee el final del registro de errores de MODX (core/cache/logs/error.log) para diagnóstico. | Lectura |
| `modx_refresh_uris` | Regenera todas las URI de recursos (ejecútelo después de cambios masivos de alias/estructura que hayan dejado URI obsoletas). | Modifica el sitio |
| `modx_remove_locks` | Elimina bloqueos de edición obsoletos del administrador (cuando un elemento/recurso figura bloqueado por una sesión terminada). | Modifica el sitio |
| `modx_project_overview` | Ofrece una visión general de todo el sitio instalado en UNA llamada compacta y de bajo coste. | Lectura |
| `modx_system_info` | Información de entorno/diagnóstico: versión de MODX, versión de modxMCP, versión de PHP, tipo de base de datos y rutas clave. | Lectura |
| `modx_regenerate_token` | Rota modxmcp.api_token a un nuevo valor aleatorio y lo devuelve. | Alto impacto |
| `modx_help` | Documentación integrada. Llame sin argumentos para enumerar temas; envíe {topic} para obtener una guía (p. ej. 'tv_input_types', 'study_component', 'minishop2', 'migx', 'acl', 'getting_started'). Lea el tema pertinente antes de tareas desconocidas o para aprender a utilizar un componente recién instalado. | Lectura |
| `modx_list_actions` | Enumera los nombres de acciones que admite esta compilación del servidor modxMCP, agrupados por área. | Lectura |
| `modx_clear_cache` | Actualiza la caché de MODX. Envíe partitions (p. ej. ['resource','context_settings']) para actualizar solo esas particiones; omítalo para una actualización completa. | Modifica el sitio |
| `modx_read_audit_log` | Lee el registro de auditoría de escritura de modxMCP (lo más reciente al final). | Lectura |
| `modx_run_processor` | Ejecuta directamente CUALQUIER procesador MODX (vía de acceso directo). | Alto impacto |

## Cómo utilizar el catálogo

Un host MCP compatible descubrirá las herramientas disponibles y pasará argumentos validados al cliente MCP local. No necesita memorizar los nombres de los comandos. Por ejemplo, averiguar cómo se renderiza una página puede implicar listar su recurso, leer su plantilla asignada e inspeccionar el chunk o snippet referenciado. Son operaciones públicas estándar, no un flujo de trabajo prescrito para agentes.

Si falta una acción, compruebe si se ha deshabilitado su grupo de capacidades, si está instalado el complemento requerido y si coinciden las versiones del cliente/servidor. `modx_list_actions` informa de las acciones que conoce el servidor instalado.

## Recordatorio de seguridad

La credencial `X-MCP-Token` autoriza a un usuario de servicio con privilegios elevados. Conocer únicamente los nombres de estas herramientas no concede acceso. Sin embargo, un atacante que obtenga un token válido puede potencialmente modificar elementos PHP y ajustes del sistema. No utilice una etiqueta «Lectura» de este documento como límite de seguridad. Consulte [Seguridad](SECURITY.es.md).

Para detalles de implementación, consulte las [definiciones públicas del cliente](https://github.com/rumata-estor/modx3-mcp/blob/main/client/index.js).
