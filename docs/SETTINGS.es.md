# Ajustes del sistema de MODX MCP

[English](SETTINGS.md) · [Русский](SETTINGS.ru.md) · [Español](SETTINGS.es.md) · [Deutsch](SETTINGS.de.md) · [Français](SETTINGS.fr.md) · [Українська](SETTINGS.uk.md) · [Қазақша](SETTINGS.kk.md) · [Nederlands](SETTINGS.nl.md) · [Polski](SETTINGS.pl.md) · [Instalación](INSTALL.es.md) · [Seguridad](SECURITY.es.md)

Para **MODX MCP 1.2.5** en MODX 2.8.x y 3.x. Este documento abarca los **16 ajustes del sistema** definidos por ambos paquetes de transporte. Los valores predeterminados proceden de `_build/data/transport.settings.php`; el comportamiento se ha comprobado frente a la implementación PHP.

En el administrador de MODX: **Sistema → Ajustes del sistema**, espacio de nombres `modxmcp`. **Componentes → MODX MCP** también permite cambiar Audit log, Auto-static y los grupos de capacidades, así como mostrar/copiar de forma segura el token de la API. Modifique las demás opciones en Ajustes del sistema. Actualice las cachés de MODX cuando sea necesario y actualice las capacidades del cliente MCP después de los cambios.

**Seguridad:** las operaciones autorizadas por el token se ejecutan bajo un **usuario de servicio sudo** de MODX. Mantenga el token en secreto. Los interruptores de capacidades **no establecen un límite de permisos**.

## Conexión e identidad

| Ajuste | Valor predeterminado | Valores y comportamiento |
| --- | --- | --- |
| `modxmcp.enabled` | `0` | `0`: se rechazan las acciones POST de la API. `1`: API habilitada. El endpoint limitado de estado/versión `GET` sigue funcionando con `0` si se cumplen las condiciones HTTPS. Habilítelo solo cuando estén preparados los controles de acceso. |
| `modxmcp.api_token` | Valor vacío en el paquete; se genera un token aleatorio durante la instalación **si falta** | Cadena secreta enviada en la cabecera `X-MCP-Token`. Los tokens ausentes/incorrectos devuelven `401`. Las actualizaciones normales conservan el token. Los usuarios del administrador con permiso `settings` pueden utilizar **Mostrar** / **Copiar**. Rotar un token invalida las credenciales de clientes existentes. |
| `modxmcp.service_user_id` | `0` | `0` selecciona el primer **usuario MODX sudo activo** por ID; un ID positivo requiere una cuenta sudo activa existente. Para un comportamiento predecible, elija una cuenta de servicio dedicada. No es la identidad del cliente MCP externo. |

## Red y seguridad

| Ajuste | Valor predeterminado | Valores y comportamiento |
| --- | --- | --- |
| `modxmcp.require_https` | `1` | `0`/`1`. Exige HTTPS, incluso para la respuesta de estado `GET`, o devuelve `403`. Mantenga `1` en sitios desplegados. |
| `modxmcp.trust_proxy_https` | `0` | `0`/`1`. Si está habilitado, se acepta `X-Forwarded-Proto: https` como prueba de HTTPS. Habilítelo solo detrás de un proxy inverso de confianza que sobrescriba las cabeceras proporcionadas por el cliente. |
| `modxmcp.allowed_ips` | Vacío | Direcciones IP exactas y **IPv4 CIDR** separadas por comas, p. ej. `203.0.113.5,198.51.100.0/24`. Vacío permite cualquier origen con un token válido. Comprueba `REMOTE_ADDR`, **no** `X-Forwarded-For`. Se admiten coincidencias IPv6 exactas; IPv6 CIDR no se admite. Los valores incorrectos provocan `403`. |
| `modxmcp.disabled_groups` | `versionx,virtualpage,minishop2,migx,access,property_sets,contexts,package_management,namespaces,lexicon` | Grupos deshabilitados separados por comas: `versionx`, `virtualpage`, `minishop2`, `migx`, `clientconfig`, `access`, `property_sets`, `contexts`, `package_management`, `namespaces`, `lexicon`. Vacío habilita todos los grupos que por lo demás sean compatibles. **No es un límite de seguridad basado en roles**; las acciones básicas aún pueden modificar el sitio. |
| `modxmcp.allow_run_processor` | `0` | `0`/`1`. Permite la operación general `run_processor`, incluidos procesadores MODX personalizados. Amplía el alcance de las operaciones administrativas: manténgalo deshabilitado salvo necesidad específica. |
| `modxmcp.allow_root_filesystem_read` | `0` | `0`/`1`. Permite explorar y leer el origen multimedia raíz `Filesystem`. Puede exponer un conjunto más amplio de archivos del servidor. Manténgalo deshabilitado salvo necesidad. |
| `modxmcp.component_code_roots` | `core/components,assets/components` | Directorios de código fuente de componentes separados por comas, relativos a `base_path` o absolutos. Admite los marcadores `{core_path}`, `{assets_path}`, `{base_path}`. Restringe **solo la inspección del código de componentes**, no todas las acciones MCP sobre el sistema de archivos. Evite ampliarlo a la raíz del sistema de archivos del servidor. |

## Límites

| Ajuste | Valor predeterminado | Valores y comportamiento |
| --- | --- | --- |
| `modxmcp.max_payload_bytes` | `1048576` (1 MiB) | Tamaño máximo en bytes del cuerpo JSON de una solicitud POST a la API HTTP; si se supera, devuelve `413`. Los valores `<=0` eliminan esta comprobación. Mantenga un límite superior positivo. |
| `modxmcp.max_read_bytes` | `262144` (256 KiB) | Fragmento máximo devuelto al leer archivos; `<=0` utiliza 262144 como valor de reserva. **No** limita el tamaño total del archivo: las lecturas pueden utilizar `offset` y `bytes`/`length`. |

## Comportamiento y diagnóstico

| Ajuste | Valor predeterminado | Valores y comportamiento |
| --- | --- | --- |
| `modxmcp.auto_static` | `0` | `0`/`1`. Si está habilitado, los chunks, snippets, plantillas y plugins creados/actualizados mediante MCP se convierten en archivos estáticos bajo `core/elements/`. Requiere permisos de escritura, copias de seguridad y prácticas de despliegue adecuados. |
| `modxmcp.audit_log` | `1` | `0`/`1`. Registra las acciones de modificación compatibles en los registros de MODX y en `core/components/modxmcp/logs/audit.log`. **No** es un registro exhaustivo de cada solicitud. Manténgalo habilitado en producción con controles de acceso y tamaño. |
| `modxmcp.debug` | `0` | `0`/`1`. Si está habilitado, pueden aparecer excepciones internas detalladas en las respuestas de error HTTP. Si está deshabilitado, los llamantes reciben un error genérico y `error_id`. Mantenga `0` en producción. |

## Ruta del núcleo

| Ajuste | Valor predeterminado | Valores y comportamiento |
| --- | --- | --- |
| `modxmcp.core_path` | `{core_path}components/modxmcp/` | Directorio del núcleo del componente (también acepta `[[++core_path]]`). La API HTTP lo utiliza para cargar la implementación y la versión. Cámbielo solo para instalaciones no estándar verificadas; los valores incorrectos impiden que funcione la API. |

## Primera conexión segura

1. Instale el paquete de transporte correspondiente a su versión principal de MODX. Las instalaciones nuevas comienzan con `modxmcp.enabled=0`; el token se genera automáticamente y los tokens existentes se conservan en las actualizaciones ordinarias.
2. Configure HTTPS, las restricciones de IP opcionales, los ajustes del proxy inverso y `service_user_id`.
3. Utilice **Copiar** en el panel del administrador para guardar el token de la API en el almacenamiento de secretos del cliente MCP. No lo divulgue.
4. Habilite solo las capacidades necesarias. Deje `allow_run_processor` y `allow_root_filesystem_read` deshabilitados salvo justificación.
5. Habilite la API y verifique una **acción autenticada de solo lectura**. Una respuesta `GET` de versión/estado **no** demuestra que el token del cliente sea válido.
