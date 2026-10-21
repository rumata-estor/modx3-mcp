# Встановлення та підключення MODX MCP

[English](INSTALL.md) · [Русский](INSTALL.ru.md) · [Español](INSTALL.es.md) · [Deutsch](INSTALL.de.md) · [Français](INSTALL.fr.md) · [Українська](INSTALL.uk.md) · [Қазақша](INSTALL.kk.md) · [Nederlands](INSTALL.nl.md) · [Polski](INSTALL.pl.md)

**MODX MCP 1.2.4 · MODX Revolution 2.8.x / 3.x · 193 / 192**

Оберіть транспортний пакет відповідно до версії MODX. Для MCP-клієнта потрібен Node.js 18 або новіший.

## 1. MODX 2 / MODX 3

- [Пакет для MODX 2](https://github.com/rumata-estor/modx3-mcp/releases/download/v1.2.4/modx2mcp-1.2.4-pl.transport.zip) — MODX 2.8.x
- [Пакет для MODX 3](https://github.com/rumata-estor/modx3-mcp/releases/download/v1.2.4/modx3mcp-1.2.4-pl.transport.zip) — MODX 3.x
- [SHA256SUMS](https://github.com/rumata-estor/modx3-mcp/releases/download/v1.2.4/SHA256SUMS)

## 2. Швидкий початок

1. Увійдіть до менеджера MODX та встановіть відповідний ZIP через менеджер пакетів.
2. Відкрийте «Компоненти → MODX MCP». Перевірте HTTPS, дозволи й API-токен; після нової інсталяції компонент вимкнений.
3. Увімкніть `modxmcp.enabled` лише після захисту доступу. Зберігайте токен у приватній конфігурації MCP-клієнта.

## 3. Node.js / MCP

Налаштуйте сумісний зі stdio MCP-хост за наведеним зразком. Замініть тестовий домен і токен; використовуйте опублікований тег v1.2.4.

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

## 4. Перевірка підключення

Перезапустіть MCP-клієнт і спочатку перевірте читання через `modx_system_info` або `modx_project_overview`. GET-відповідь про стан не підтверджує правильність токена.

## Оновлення наявної інсталяції

Звичайне оновлення `modx2mcp` або `modx3mcp` зберігає налаштування та токен. Якщо на MODX 2 досі є старий пакет `modxmcp`, це інша ідентичність пакета: перш ніж видаляти його, збережіть конфігурацію та токен.

## Усунення неполадок

- `401`: `MODX_MCP_TOKEN` / `modxmcp.api_token`
- `403`: `modxmcp.enabled`, `modxmcp.require_https`, `modxmcp.allowed_ips`
- `404`: `/assets/components/modxmcp/api.php`
- `413`: `modxmcp.max_payload_bytes`
- `500`: MODX error log and `error_id`.

**Повні технічні довідники:** [параметри API (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/API.md) · [безпека (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/SECURITY.md) · [розробка (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/DEVELOPMENT.md). Детальні технічні таблиці поки доступні англійською.
