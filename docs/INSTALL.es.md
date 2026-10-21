# Instalación y conexión de MODX MCP

[English](INSTALL.md) · [Русский](INSTALL.ru.md) · [Español](INSTALL.es.md) · [Deutsch](INSTALL.de.md) · [Français](INSTALL.fr.md) · [Українська](INSTALL.uk.md) · [Қазақша](INSTALL.kk.md) · [Nederlands](INSTALL.nl.md) · [Polski](INSTALL.pl.md) · [Capacidades](CAPABILITIES.es.md) · [Referencia de la API](API.es.md) · [Seguridad](SECURITY.es.md)

MODX MCP permite que un cliente de IA compatible con MCP estándar trabaje con **MODX Revolution 2.8.x y 3.x**. Una instalación normal requiere un paquete de transporte de MODX, **Node.js 18+** en la máquina que ejecuta el cliente MCP y una aplicación capaz de iniciar servidores MCP mediante **stdio**.

**No se necesita un servidor de orquestación independiente.** El componente MODX aloja una API HTTP en el sitio; el proceso local `modx-mcp` expone esta API a los clientes MCP mediante `stdio`. El endpoint PHP no es, por sí mismo, un servidor MCP stdio directo.

## 1. Elegir el paquete MODX

- **MODX 2.8.x:** `modx2mcp-1.2.4-pl.transport.zip`.
- **MODX 3.x:** `modx3mcp-1.2.4-pl.transport.zip`.

Ambas variantes utilizan el mismo contrato público de herramientas y el mismo cliente Node.js. Descargue el paquete adecuado desde [GitHub Releases](https://github.com/rumata-estor/modx3-mcp/releases) o desde un proveedor de paquetes MODX compatible.

**Instalaciones existentes de MODX 2:** al migrar de la antigua identidad de paquete `modxmcp` a `modx2mcp`, se recomienda desinstalar completamente el paquete antiguo y después instalar `modx2mcp`. Una desinstalación completa elimina los ajustes `modxmcp.*` antiguos y el token de la API. Conserve la configuración pertinente según sus necesidades y actualice después las credenciales del cliente. Las actualizaciones normales posteriores de `modx2mcp` conservan el token actual.

### Panel del administrador

En **Componentes → MODX MCP**, copie la URL de la API, muestre/copie el token existente solo si su cuenta del administrador tiene el permiso **settings**, active o desactive grupos opcionales de capacidades y configure **Auto-static** / **Audit log**. Los cambios de ajustes y capacidades solo surten efecto al pulsar sus botones de guardar. El **grafo de dependencias** está disponible mediante el enlace al grafo; el lienzo se amplía o reduce con la rueda del ratón, mientras que el panel circundante se desplaza para mostrar filtros y detalles. **Regenerar token** invalida todas las credenciales de clientes existentes; las actualizaciones ordinarias del paquete no lo hacen. Que la página indique «MCP habilitado» no verifica en tiempo real la conexión de ningún cliente concreto.

## 2. Instalar el paquete de transporte

1. Inicie sesión en el administrador de MODX con una cuenta autorizada para instalar paquetes.
2. Abra la gestión de paquetes, cargue el ZIP de transporte correspondiente a su versión principal de MODX e instálelo.
3. Abra el componente MODX MCP o los ajustes del sistema con el espacio de nombres `modxmcp`.
4. Compruebe `modxmcp.api_token` y las restricciones de red. Las instalaciones nuevas comienzan con `modxmcp.enabled = 0`; habilite el componente manualmente después de proteger el acceso.
5. Copie el token en la **configuración de su cliente MCP**. Trátelo como un secreto, nunca como un ejemplo público.

El instalador genera un token criptográficamente aleatorio si el ajuste está vacío. Una actualización ordinaria conserva el token existente.

## 3. Localizar el endpoint del sitio

La URL estándar es:

```text
https://example.com/assets/components/modxmcp/api.php
```

Utilice su propio dominio; los despliegues personalizados pueden cambiar la ruta real. Se recomienda **HTTPS**, que además es obligatorio de forma predeterminada.

`GET` devuelve una respuesta limitada de estado/versión del componente sin autenticación. Las solicitudes administrativas utilizan `POST` con JSON y `X-MCP-Token`.

## 4. Configurar el host MCP

En la máquina que ejecuta su aplicación de IA, instale **Node.js 18+** con npm/`npx`. Añada el servidor MCP a la configuración del host (la ubicación y el formato de configuración admitido dependen de la aplicación):

```json
{
  "mcpServers": {
    "modx": {
      "command": "npx",
      "args": [
        "-y",
        "github:rumata-estor/modx3-mcp#v1.2.4"
      ],
      "env": {
        "MODX_MCP_SITE_URL": "https://example.com/assets/components/modxmcp/api.php",
        "MODX_MCP_TOKEN": "YOUR_SITE_PRIVATE_TOKEN"
      }
    }
  }
}
```

`MODX_MCP_SITE_URL` es el endpoint HTTP de la API PHP. `MODX_MCP_TOKEN` es el token de este sitio concreto. Fije la **etiqueta de una versión publicada** en sistemas de producción en lugar de seguir `main`. Asegúrese de que el host permita iniciar un servidor MCP local mediante `stdio`.

## 5. Confirmar la conectividad

Reinicie la integración MCP o actualice los servidores del host. Busque el servidor `modx` y las herramientas con el prefijo `modx_`. Comience con una solicitud de solo lectura, como:

```text
Show the MODX version and an overview of the site structure. Make no changes.
```

Esto puede utilizar `modx_system_info` y `modx_project_overview`. El host puede mostrar menos de 192 herramientas, según la compilación del servidor, los grupos deshabilitados y los complementos instalados.

La [referencia completa de ajustes del sistema](SETTINGS.es.md) documenta **los 16 ajustes**, sus valores predeterminados, comportamiento e implicaciones de seguridad.

## 6. Ajustes de seguridad que debe revisar

| Ajuste | Significado |
| --- | --- |
| `modxmcp.enabled` | Habilita o deshabilita la API. |
| `modxmcp.api_token` | Credencial secreta de la API. |
| `modxmcp.require_https` | Exige HTTPS; habilitado de forma predeterminada. |
| `modxmcp.allowed_ips` | Lista opcional de IP y rangos IPv4 CIDR permitidos; vacía permite cualquier origen. |
| `modxmcp.trust_proxy_https` | Confíe en la cabecera HTTPS del proxy inverso solo detrás de un proxy correctamente configurado. |
| `modxmcp.disabled_groups` | Deshabilita algunas familias de herramientas; **no constituye un límite de seguridad basado en roles**. |
| `modxmcp.allow_run_processor` | Permite invocar directamente procesadores MODX; deshabilitado de forma predeterminada. |
| `modxmcp.max_payload_bytes` | Límite de tamaño de las solicitudes. |
| `modxmcp.debug` | Detalles de errores; manténgalo deshabilitado en producción. |

**Importante:** las solicitudes se ejecutan como un usuario de servicio MODX con privilegios `sudo`. La exposición de un token válido puede permitir cambios administrativos y la modificación de PHP ejecutable en los elementos. Lea la [guía de seguridad](SECURITY.es.md).

## Solución de problemas

| Síntoma | Qué comprobar |
| --- | --- |
| El servidor MCP no se inicia | Node.js 18+, `npx`, resolución del paquete y configuración del host. |
| `401 Unauthorized` | `MODX_MCP_TOKEN` debe coincidir con `modxmcp.api_token`; compruebe la rotación del token. |
| `403` | Ajuste de habilitación del componente, HTTPS, configuración del proxy inverso e IP permitida. |
| `404` | Ruta del endpoint, instalación del componente y enrutamiento del servidor web. |
| `413` | La solicitud superó `modxmcp.max_payload_bytes`. |
| Herramienta no disponible | Versiones del cliente/servidor, grupos deshabilitados y presencia del complemento requerido. |
| `500` | Registros de errores de MODX y `error_id`; evite mostrar mensajes de depuración públicamente. |

No incluya tokens de la API en cadenas de consulta ni en informes de incidencias. Los clientes HTTP personalizados deben enviar `X-MCP-Token` mediante TLS.

## Próximos pasos

- [Capacidades](CAPABILITIES.es.md) — qué puede hacer MODX MCP.
- [Referencia de la API](API.es.md) — herramientas y parámetros de entrada.
- [Seguridad](SECURITY.es.md) — riesgos y configuración defensiva.
- [README del proyecto](../README.es.md) — descripción general del proyecto.
- [Validación](VALIDATION.es.md) — notas de pruebas de compatibilidad.
