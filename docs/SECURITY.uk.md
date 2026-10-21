# Безпека та модель довіри — MODX MCP

[English](SECURITY.md) · [Русский](SECURITY.ru.md) · [Español](SECURITY.es.md) · [Deutsch](SECURITY.de.md) · [Français](SECURITY.fr.md) · [Українська](SECURITY.uk.md) · [Қазақша](SECURITY.kk.md) · [Nederlands](SECURITY.nl.md) · [Polski](SECURITY.pl.md)

**API-токен є секретом адміністратора.** Автентифіковані запити виконуються від імені сервісного користувача MODX із правами sudo. Доступ до токена може дозволити змінювати навіть виконуваний PHP-код.

**Важливо:** вимкнення груп функцій не замінює серверну авторизацію. Прямі HTTP-клієнти не успадковують автоматично захист стандартного Node.js-клієнта.

## Безпека та модель довіри

1. Використовуйте HTTPS і перевіряйте налаштування TLS у зворотному проксі.
2. Вважайте API-токен обліковими даними адміністратора; не публікуйте його.
3. За можливості обмежте доступ дозволеними IP-адресами або захищеною мережею.
4. Залишайте `modxmcp.allow_run_processor=0` і `modxmcp.allow_root_filesystem_read=0`, якщо вони не потрібні.
5. Вимкнення груп функцій не замінює права доступу для кожного користувача.
6. Не вмикайте розгорнуті повідомлення про помилки в продакшені (`modxmcp.debug=0`).
7. Робіть незалежні резервні копії та перевіряйте ризикові операції через `dry_run`.

## HTTP / MCP

- `POST` JSON uses `X-MCP-Token` over HTTPS.
- `GET` health/version is **not** proof that the client token is valid.
- `modxmcp.disabled_groups` is **not** an independent authorization boundary.
- External scripts calling `api.php` directly do not inherit Node.js client safeguards such as some backups and confirmations.

**Повні технічні довідники:** [параметри API (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/API.md) · [безпека (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/SECURITY.md) · [розробка (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/DEVELOPMENT.md). Детальні технічні таблиці поки доступні англійською.
