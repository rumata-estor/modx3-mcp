# Пакет для MODX 3 — MODX MCP 1.2.4

[English](modx3mcp-1.2.4.md) · [Русский](modx3mcp-1.2.4.ru.md) · [Español](modx3mcp-1.2.4.es.md) · [Deutsch](modx3mcp-1.2.4.de.md) · [Français](modx3mcp-1.2.4.fr.md) · [Українська](modx3mcp-1.2.4.uk.md) · [Қазақша](modx3mcp-1.2.4.kk.md) · [Nederlands](modx3mcp-1.2.4.nl.md) · [Polski](modx3mcp-1.2.4.pl.md)

[Встановлення](../INSTALL.uk.md) · [Безпека](../SECURITY.uk.md) · [Налаштування](../SETTINGS.uk.md) · [GitHub v1.2.4](https://github.com/rumata-estor/modx3-mcp/releases/tag/v1.2.4)

**MODX Revolution 3.x · 1.2.4-pl**

MODX MCP підключає ШІ-асистентів до сайтів MODX через Model Context Protocol. PHP-компонент надає захищений HTTP API, а локальний клієнт Node.js передає його сумісним MCP-застосункам через stdio. Окремий сервер оркестрації не потрібен.

## Завантажити

[modx3mcp-1.2.4-pl.transport.zip](https://github.com/rumata-estor/modx3-mcp/releases/download/v1.2.4/modx3mcp-1.2.4-pl.transport.zip) · [SHA256SUMS](https://github.com/rumata-estor/modx3-mcp/releases/download/v1.2.4/SHA256SUMS)

## Швидкий початок

1. Увійдіть до менеджера MODX та встановіть відповідний ZIP через менеджер пакетів.
2. Відкрийте «Компоненти → MODX MCP». Перевірте HTTPS, дозволи й API-токен; після нової інсталяції компонент вимкнений.
3. Увімкніть `modxmcp.enabled` лише після захисту доступу. Зберігайте токен у приватній конфігурації MCP-клієнта.

## Оновлення наявної інсталяції

Звичайне оновлення `modx2mcp` або `modx3mcp` зберігає налаштування та токен. Якщо на MODX 2 досі є старий пакет `modxmcp`, це інша ідентичність пакета: перш ніж видаляти його, збережіть конфігурацію та токен.

## Безпека та модель довіри

**API-токен є секретом адміністратора.** Автентифіковані запити виконуються від імені сервісного користувача MODX із правами sudo. Доступ до токена може дозволити змінювати навіть виконуваний PHP-код.

**Повні технічні довідники:** [параметри API (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/API.md) · [безпека (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/SECURITY.md) · [розробка (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/DEVELOPMENT.md). Детальні технічні таблиці поки доступні англійською.
