# Bezpieczeństwo i model zaufania — MODX MCP

[English](SECURITY.md) · [Русский](SECURITY.ru.md) · [Español](SECURITY.es.md) · [Deutsch](SECURITY.de.md) · [Français](SECURITY.fr.md) · [Українська](SECURITY.uk.md) · [Қазақша](SECURITY.kk.md) · [Nederlands](SECURITY.nl.md) · [Polski](SECURITY.pl.md)

**Token API jest tajnym kluczem administracyjnym.** Uwierzytelniane żądania działają jako użytkownik usługowy MODX z uprawnieniami sudo. Posiadacz tokenu może zmieniać nawet wykonywalny kod PHP.

**Ważne:** wyłączenie grup funkcji nie zastępuje autoryzacji po stronie serwera. Bezpośredni klient HTTP nie dziedziczy automatycznie zabezpieczeń oficjalnego klienta Node.js.

## Bezpieczeństwo i model zaufania

1. Używaj HTTPS i sprawdź konfigurację TLS na serwerze proxy.
2. Traktuj token API jak dane logowania administratora; nie udostępniaj go publicznie.
3. Jeśli to możliwe, ogranicz dostęp do zaufanych adresów IP lub chronionej sieci.
4. Pozostaw `modxmcp.allow_run_processor=0` oraz `modxmcp.allow_root_filesystem_read=0`, jeśli nie są potrzebne.
5. Wyłączenie grup funkcji nie zastępuje uprawnień poszczególnych użytkowników.
6. W produkcji zachowaj `modxmcp.debug=0`, aby nie ujawniać szczegółów błędów.
7. Wykonuj niezależne kopie zapasowe i stosuj `dry_run` przed ryzykownymi zmianami.

## HTTP / MCP

- `POST` JSON uses `X-MCP-Token` over HTTPS.
- `GET` health/version is **not** proof that the client token is valid.
- `modxmcp.disabled_groups` is **not** an independent authorization boundary.
- External scripts calling `api.php` directly do not inherit Node.js client safeguards such as some backups and confirmations.

**Pełne dokumenty techniczne:** [parametry API (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/API.md) · [bezpieczeństwo (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/SECURITY.md) · [dla programistów (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/DEVELOPMENT.md). Szczegółowe tabele techniczne są obecnie dostępne po angielsku.
