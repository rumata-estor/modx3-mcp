# MODX MCP — керування MODX за допомогою ШІ

[English](README.md) · [Русский](README.ru.md) · [Español](README.es.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Українська](README.uk.md) · [Қазақша](README.kk.md) · [Nederlands](README.nl.md) · [Polski](README.pl.md)

**MODX MCP 1.2.5 · MODX Revolution 2.8.x / 3.x · 199 / 198**

MODX MCP підключає ШІ-асистентів до сайтів MODX через Model Context Protocol. PHP-компонент надає захищений HTTP API, а локальний клієнт Node.js передає його сумісним MCP-застосункам через stdio. Окремий сервер оркестрації не потрібен.


## Для кого призначений проєкт?

Для розробників MODX, вебстудій і адміністраторів, які хочуть безпечно керувати сайтами за допомогою ШІ.


## Можливості та інструменти

- Шукати, переглядати й змінювати ресурси, шаблони, чанки, сніпети, плагіни та TV.
- Керувати категоріями елементів MODX: створювати, перейменовувати, переміщати, безпечно видаляти та призначати елементам.
- Аналізувати залежності, пошкоджені посилання й потенційно невикористаний код на графі.
- Працювати із системними налаштуваннями, медіаджерелами, пакетами й підтримуваними доповненнями.
- Захищати зміни перевіркою передумов, попереднім переглядом і резервними копіями.

## Швидкий початок

Оберіть транспортний пакет відповідно до версії MODX. Для MCP-клієнта потрібен Node.js 18 або новіший.

- **MODX 2.8.x:** [Пакет для MODX 2](https://github.com/rumata-estor/modx3-mcp/releases/download/v1.2.4/modx2mcp-1.2.4-pl.transport.zip)
- **MODX 3.x:** [Пакет для MODX 3](https://github.com/rumata-estor/modx3-mcp/releases/download/v1.2.4/modx3mcp-1.2.4-pl.transport.zip)
- [GitHub v1.2.4](https://github.com/rumata-estor/modx3-mcp/releases/tag/v1.2.4) · [SHA256SUMS](https://github.com/rumata-estor/modx3-mcp/releases/download/v1.2.4/SHA256SUMS)

> Посилання на завантаження вище ведуть до останньої опублікованої версії 1.2.4. Транспортні ZIP 1.2.5 ще не зібрані.

Увійдіть до менеджера MODX та встановіть відповідний ZIP через менеджер пакетів.

Відкрийте «Компоненти → MODX MCP». Перевірте HTTPS, дозволи й API-токен; після нової інсталяції компонент вимкнений.

```json
{
  "mcpServers": {
    "modx": {
      "command": "npx",
      "args": ["-y", "github:rumata-estor/modx3-mcp#v1.2.4"],
      "env": {
        "MODX_MCP_SITE_URL": "https://example.com/assets/components/modxmcp/api.php",
        "MODX_MCP_TOKEN": "YOUR_SITE_PRIVATE_TOKEN"
      }
    }
  }
}
```

Увімкніть `modxmcp.enabled` лише після захисту доступу. Зберігайте токен у приватній конфігурації MCP-клієнта.

## Безпека та модель довіри

**API-токен є секретом адміністратора.** Автентифіковані запити виконуються від імені сервісного користувача MODX із правами sudo. Доступ до токена може дозволити змінювати навіть виконуваний PHP-код.

**Важливо:** вимкнення груп функцій не замінює серверну авторизацію. Прямі HTTP-клієнти не успадковують автоматично захист стандартного Node.js-клієнта.

## Документація

[Встановлення](docs/INSTALL.uk.md) · [Безпека](docs/SECURITY.uk.md) · [Налаштування](docs/SETTINGS.uk.md) · [Можливості](docs/CAPABILITIES.uk.md) · [API](docs/API.uk.md)

**Повні технічні довідники:** [параметри API (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/API.md) · [безпека (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/SECURITY.md) · [розробка (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/DEVELOPMENT.md). Детальні технічні таблиці поки доступні англійською.
