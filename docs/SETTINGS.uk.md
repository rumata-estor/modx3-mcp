# Системні налаштування — MODX MCP

[English](SETTINGS.md) · [Русский](SETTINGS.ru.md) · [Español](SETTINGS.es.md) · [Deutsch](SETTINGS.de.md) · [Français](SETTINGS.fr.md) · [Українська](SETTINGS.uk.md) · [Қазақша](SETTINGS.kk.md) · [Nederlands](SETTINGS.nl.md) · [Polski](SETTINGS.pl.md)

Усі 16 параметрів доступні в «Система → Системні налаштування», простір імен `modxmcp`. Типові значення однакові для MODX 2 та MODX 3.

**Важливо:** вимкнення груп функцій не замінює серверну авторизацію. Прямі HTTP-клієнти не успадковують автоматично захист стандартного Node.js-клієнта.

| Ключ | Типово | Опис |
| --- | --- | --- |
| `modxmcp.enabled` | `0` | Вмикає або вимикає автентифіковані операції API; після встановлення вимкнено. |
| `modxmcp.api_token` | `(generated)` | Секретний токен у заголовку `X-MCP-Token`; звичайне оновлення зберігає його. |
| `modxmcp.service_user_id` | `0` | Сервісний користувач MODX; `0` вибирає першого активного користувача sudo. |
| `modxmcp.require_https` | `1` | Вимагає HTTPS; залишайте увімкненим у продакшені. |
| `modxmcp.trust_proxy_https` | `0` | Довіряє HTTPS-заголовкам лише від правильно налаштованого зворотного проксі. |
| `modxmcp.allowed_ips` | `(empty)` | Дозволені IP та діапазони IPv4 CIDR; порожнє значення не обмежує IP. |
| `modxmcp.disabled_groups` | `versionx,virtualpage,minishop2,migx,access,property_sets,contexts,package_management,namespaces,lexicon` | Вимкнені групи інструментів через кому; не є повною межею авторизації. |
| `modxmcp.allow_run_processor` | `0` | Дозволяє запуск довільних процесорів MODX; типово вимкнено. |
| `modxmcp.allow_root_filesystem_read` | `0` | Дозволяє читати кореневе медіаджерело «Filesystem»; типово вимкнено. |
| `modxmcp.component_code_roots` | `core/components,assets/components` | Каталоги для перегляду коду компонентів; не обмежують усі файлові операції. |
| `modxmcp.max_payload_bytes` | `1048576` | Максимальний розмір тіла POST JSON у байтах; перевищення дає 413. |
| `modxmcp.max_read_bytes` | `262144` | Максимальний обсяг одного фрагмента читання файлу в байтах. |
| `modxmcp.auto_static` | `0` | Автоматично створює статичні файли елементів. |
| `modxmcp.audit_log` | `1` | Записує підтримувані зміни до журналу аудиту, але не всі запити. |
| `modxmcp.debug` | `0` | Показує докладні внутрішні помилки; вимкніть у продакшені. |
| `modxmcp.core_path` | `{core_path}components/modxmcp/` | Шлях до ядра компонента; змінюйте лише для перевірених нестандартних інсталяцій. |

**Повні технічні довідники:** [параметри API (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/API.md) · [безпека (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/SECURITY.md) · [розробка (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/DEVELOPMENT.md). Детальні технічні таблиці поки доступні англійською.
