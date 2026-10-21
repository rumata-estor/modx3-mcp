# MODX MCP — seguridad y modelo de confianza

[English](SECURITY.md) · [Русский](SECURITY.ru.md) · [Español](SECURITY.es.md) · [Deutsch](SECURITY.de.md) · [Français](SECURITY.fr.md) · [Українська](SECURITY.uk.md) · [Қазақша](SECURITY.kk.md) · [Nederlands](SECURITY.nl.md) · [Polski](SECURITY.pl.md) · [Instalación](INSTALL.es.md) · [Capacidades](CAPABILITIES.es.md) · [API](API.es.md)

Esta guía documenta el límite de confianza de la versión pública **MODX MCP 1.2.4**, sus ajustes de seguridad y limitaciones importantes. No describe ninguna infraestructura externa de gestión de agentes.

## La regla fundamental

**Trate el token de la API de MODX MCP como una credencial administrativa.** Conocer únicamente la URL del endpoint o los nombres documentados de las herramientas no autoriza una solicitud. Sin embargo, un `X-MCP-Token` válido puede permitir cambios amplios en el sitio.

Tras autenticar una solicitud, el servidor selecciona una cuenta de servicio con permisos **`sudo`** de MODX. `modxmcp.service_user_id` puede especificar una cuenta `sudo` activa; con su valor predeterminado de cero, se selecciona el primer usuario `sudo` activo. Esto **no** es una autorización individual por usuario externo basada en los permisos personales del operador en el administrador.

Quien posee un token puede potencialmente modificar snippets, plugins, plantillas, recursos, ajustes y otros contenidos sensibles. Editar PHP en elementos ejecutables de MODX puede dar lugar a la ejecución de código PHP arbitrario con los privilegios del proceso de la aplicación web.

## Protecciones del endpoint PHP

| Control | Comportamiento y límites |
| --- | --- |
| Token | Exige `X-MCP-Token`; se comprueba frente al ajuste `modxmcp.api_token`. |
| HTTPS | `modxmcp.require_https` está habilitado de forma predeterminada; verifique la configuración de terminación TLS. |
| Lista de IP permitidas | `modxmcp.allowed_ips` compara `REMOTE_ADDR` con IP exactas o rangos IPv4 CIDR; vacía permite cualquier dirección del interlocutor. |
| Proxy inverso | `modxmcp.trust_proxy_https` está deshabilitado de forma predeterminada; actívelo solo detrás de un proxy inverso de confianza correctamente configurado. |
| Interruptor general | `modxmcp.enabled`, cuando está desactivado, deshabilita las operaciones autenticadas de la API. |
| Límite de tamaño | `modxmcp.max_payload_bytes` limita el tamaño de las cargas JSON entrantes. |
| Vía de acceso directo a procesadores | `modxmcp.allow_run_processor` está deshabilitado de forma predeterminada. |
| Grupos de capacidades | `modxmcp.disabled_groups` deshabilita las familias de herramientas seleccionadas. |

**Las familias de herramientas deshabilitadas no constituyen un límite de autorización completo.** Las herramientas estándar de creación/actualización/eliminación de ajustes del sistema rechazan las claves que empiezan por `modxmcp.`, incluidos los intentos de renombrar otros ajustes con ese prefijo. La rotación del token sigue siendo una acción privilegiada independiente. Esto no es un entorno aislado: un llamante autenticado que pueda editar PHP ejecutable (o invocar procesadores habilitados explícitamente) aún puede afectar a la seguridad del sitio. Los distintos operadores necesitan controles de autorización independientes en el servidor.

Un endpoint correctamente autenticado no equivale a una limitación integrada de la frecuencia de solicitudes. Si su despliegue necesita límites de frecuencia, aplíquelos en el proxy inverso o en el servidor web.

## Cliente MCP Node.js estándar frente a llamadas HTTP directas

El cliente Node.js suministrado incluye salvaguardas adicionales para algunas modificaciones, como copias de seguridad previas de determinados objetos, habilitaciones explícitas mediante variables de entorno para operaciones arriesgadas y coordinación de llamadas. **Los llamantes personalizados que envían solicitudes directamente a `api.php` no ejecutan estas protecciones del cliente.**

El servidor PHP también implementa bloqueos y comprobaciones de precondiciones para algunas modificaciones, además de registros de auditoría. Esto no hace que el acceso HTTP directo sea equivalente a pasar por el cliente MCP estándar.

Si escribe su propia integración HTTP, implemente sus propias copias de seguridad, autorización, confirmaciones y procedimientos de recuperación.

## Configuración recomendada para producción

1. Utilice HTTPS con un certificado válido.
2. Guarde el token en una configuración de cliente protegida o en un gestor de secretos. Nunca lo incluya en URL, código público, capturas de pantalla, solicitudes de soporte ni registros.
3. Restrinja el acceso al endpoint a IP de clientes conocidas o a una red/proxy protegido cuando sea viable. Verifique qué dirección ve realmente PHP en `REMOTE_ADDR`.
4. Mantenga `modxmcp.allow_run_processor = 0` salvo que sea específicamente necesario.
5. Deshabilite los grupos de capacidades no utilizados, reconociendo que esto no es un control de acceso por usuario.
6. Mantenga `modxmcp.debug` deshabilitado en producción.
7. Mantenga copias de seguridad independientes y probadas del sitio MODX y de la base de datos.
8. Utilice `dry_run` en las herramientas masivas destructivas cuando esté disponible; apruebe manualmente los cambios de alto impacto en código, permisos y ajustes.
9. Rote el token de la API si sospecha que se ha comprometido; actualice de inmediato la configuración de todos los clientes autorizados.

## Secretos e información privada en las respuestas de herramientas

`modx_list_system_settings` y `modx_get_system_setting` pueden devolver valores que contengan **tokens de API y otros secretos**. No reenvíe resultados completos a registros públicos, informes de incidencias, sistemas de análisis ni a un modelo externo sin evaluar el riesgo para la confidencialidad.

Las lecturas de archivos, los registros de errores, los registros de usuarios y los datos de pedidos de comercio electrónico también pueden contener información privada o sensible.

## Alcance de la protección

La documentación no puede impedir que un cliente de IA interprete mal una solicitud. Una sola edición de un snippet, plugin o plantilla compartidos puede afectar a varias páginas. Cualquier aplicación que otorgue distintos derechos a varios operadores debe hacerlos cumplir dentro de su límite de confianza en el servidor, no únicamente mediante instrucciones en el prompt o una lista de herramientas oculta.

Si descubre una vulnerabilidad, no publique credenciales válidas ni datos de clientes en una incidencia pública de GitHub. Revoque los secretos expuestos y comunique suficiente información depurada de datos sensibles para que los mantenedores reproduzcan el problema de forma segura.

## Concurrencia de TV de recursos y CAS v2

A partir del paquete de transporte publicado 1.2.3 en MODX 2 y MODX 3, `get_site_state` informa de `precondition_version=2` para CAS de TV del lado del Connector. Una solicitud protegida de `modx_update_resource_tvs` incluye `_runtime_preconditions` gestionadas por Runtime: la revisión observada del sitio, la aplicación estricta de la revisión y el mapa `expected_tvs` con exactamente los nombres de las TV que se modifican. El Connector comprueba estas condiciones bajo su bloqueo de modificación del sitio y rechaza valores obsoletos en lugar de sobrescribirlos silenciosamente.

**Limitación:** el esquema MCP sin procesar no exige esta protección interna, por lo que una llamada directa sin protección **no** debe presentarse como segura frente a agentes concurrentes. Los usuarios de Site Agent deben utilizar la ruta revisada de Runtime `modx-tv-change` o la de Executor. Las llamadas CAS fallidas requieren nuevas lecturas y un nuevo plan, no reintentos a ciegas.

## Implementación

- [Endpoint PHP y validación del token](https://github.com/rumata-estor/modx3-mcp/blob/main/core/components/modxmcp/endpoint/api.common.php)
- [Ajustes predeterminados](https://github.com/rumata-estor/modx3-mcp/blob/main/_build/data/transport.settings.php)
- [Cliente MCP estándar](https://github.com/rumata-estor/modx3-mcp/blob/main/client/index.js)
- [Despachador de acciones del servidor](https://github.com/rumata-estor/modx3-mcp/blob/main/core/components/modxmcp/model/modxmcp.class.php)
