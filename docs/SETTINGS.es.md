# Configuración del sistema — MODX MCP

[English](SETTINGS.md) · [Русский](SETTINGS.ru.md) · [Español](SETTINGS.es.md) · [Deutsch](SETTINGS.de.md) · [Français](SETTINGS.fr.md) · [Українська](SETTINGS.uk.md) · [Қазақша](SETTINGS.kk.md) · [Nederlands](SETTINGS.nl.md) · [Polski](SETTINGS.pl.md)

Los 16 ajustes están disponibles en «Sistema → Configuración del sistema», espacio de nombres `modxmcp`. Los valores predeterminados corresponden a los paquetes de transporte de MODX 2 y MODX 3.

**Importante:** desactivar grupos no sustituye la autorización del servidor. Los clientes HTTP personalizados tampoco heredan automáticamente las protecciones del cliente oficial Node.js.

| Clave | Predeterminado | Descripción |
| --- | --- | --- |
| `modxmcp.enabled` | `0` | Activa o desactiva las operaciones autenticadas; las instalaciones nuevas empiezan desactivadas. |
| `modxmcp.api_token` | `(generated)` | Token secreto enviado con `X-MCP-Token`; las actualizaciones normales lo conservan. |
| `modxmcp.service_user_id` | `0` | Usuario de servicio MODX; `0` selecciona el primer usuario sudo activo. |
| `modxmcp.require_https` | `1` | Exige HTTPS para las solicitudes; manténgalo activado en producción. |
| `modxmcp.trust_proxy_https` | `0` | Confía en la cabecera HTTPS solo detrás de un proxy inverso correctamente configurado. |
| `modxmcp.allowed_ips` | `(empty)` | Direcciones IP y rangos IPv4 CIDR permitidos; vacío significa sin restricción de IP. |
| `modxmcp.disabled_groups` | `versionx,virtualpage,minishop2,migx,access,property_sets,contexts,package_management,namespaces,lexicon` | Grupos de herramientas desactivados, separados por comas; no es una barrera completa de permisos. |
| `modxmcp.allow_run_processor` | `0` | Permite ejecutar procesadores MODX arbitrarios; desactivado por defecto. |
| `modxmcp.allow_root_filesystem_read` | `0` | Permite leer la fuente raíz de archivos «Filesystem»; desactivado por defecto. |
| `modxmcp.component_code_roots` | `core/components,assets/components` | Directorios desde los que se puede inspeccionar código de componentes; no limita todas las operaciones de archivos. |
| `modxmcp.max_payload_bytes` | `1048576` | Tamaño máximo en bytes del cuerpo JSON POST; si se excede devuelve 413. |
| `modxmcp.max_read_bytes` | `262144` | Máximo de bytes devueltos en un segmento de lectura de archivos. |
| `modxmcp.auto_static` | `0` | Crea archivos estáticos automáticamente al modificar elementos. |
| `modxmcp.audit_log` | `1` | Registra ciertas operaciones de cambio; no incluye necesariamente todas las solicitudes. |
| `modxmcp.debug` | `0` | Devuelve errores internos detallados; manténgalo desactivado en producción. |
| `modxmcp.core_path` | `{core_path}components/modxmcp/` | Ruta del núcleo del componente; cambie solo en instalaciones especiales comprobadas. |

**Referencias técnicas completas:** [parámetros API (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/API.md) · [seguridad (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/SECURITY.md) · [desarrollo (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/DEVELOPMENT.md). Las tablas técnicas detalladas se mantienen en inglés.
