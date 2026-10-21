# Қауіпсіздік және сенім моделі — MODX MCP

[English](SECURITY.md) · [Русский](SECURITY.ru.md) · [Español](SECURITY.es.md) · [Deutsch](SECURITY.de.md) · [Français](SECURITY.fr.md) · [Українська](SECURITY.uk.md) · [Қазақша](SECURITY.kk.md) · [Nederlands](SECURITY.nl.md) · [Polski](SECURITY.pl.md)

**API токені — әкімшілік құпия дерек.** Расталған сұраулар sudo құқығы бар MODX қызметтік пайдаланушысы арқылы орындалады. Жарамды токен PHP кодын қоса, сайттың маңызды бөліктерін өзгертуге мүмкіндік беруі мүмкін.

**Маңызды:** құрал топтарын өшіру серверлік рұқсаттарды алмастырмайды. Тікелей HTTP клиенттері стандартты Node.js клиентінің қауіпсіздік шараларын автоматты қолданбайды.

## Қауіпсіздік және сенім моделі

1. HTTPS қолданыңыз және кері проксидегі TLS баптауын тексеріңіз.
2. API токенін әкімші құпиясөзі сияқты қорғаңыз; ешқашан жарияламаңыз.
3. Мүмкіндігінше қатынауды белгілі IP мекенжайларымен немесе қорғалған желімен шектеңіз.
4. Қажет болмаса `modxmcp.allow_run_processor=0` және `modxmcp.allow_root_filesystem_read=0` күйінде қалдырыңыз.
5. Мүмкіндік топтарын өшіру жеке пайдаланушы рұқсаттарын алмастырмайды.
6. Өндірісте `modxmcp.debug=0` күйін сақтап, ішкі қате мәліметтерін көрсетпеңіз.
7. Тәуелсіз резервтік көшірме жасаңыз және қауіпті өзгерістер алдында `dry_run` қолданыңыз.

## HTTP / MCP

- `POST` JSON uses `X-MCP-Token` over HTTPS.
- `GET` health/version is **not** proof that the client token is valid.
- `modxmcp.disabled_groups` is **not** an independent authorization boundary.
- External scripts calling `api.php` directly do not inherit Node.js client safeguards such as some backups and confirmations.

**Толық техникалық анықтамалар:** [API параметрлері (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/API.md) · [қауіпсіздік (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/SECURITY.md) · [әзірлеуші құжаттамасы (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/DEVELOPMENT.md). Техникалық кестелердің толық нұсқасы әзірге ағылшын тілінде.
