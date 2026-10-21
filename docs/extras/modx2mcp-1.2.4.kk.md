# MODX 2 пакеті — MODX MCP 1.2.4

[English](modx2mcp-1.2.4.md) · [Русский](modx2mcp-1.2.4.ru.md) · [Español](modx2mcp-1.2.4.es.md) · [Deutsch](modx2mcp-1.2.4.de.md) · [Français](modx2mcp-1.2.4.fr.md) · [Українська](modx2mcp-1.2.4.uk.md) · [Қазақша](modx2mcp-1.2.4.kk.md) · [Nederlands](modx2mcp-1.2.4.nl.md) · [Polski](modx2mcp-1.2.4.pl.md)

[Орнату](../INSTALL.kk.md) · [Қауіпсіздік](../SECURITY.kk.md) · [Баптаулар](../SETTINGS.kk.md) · [GitHub v1.2.4](https://github.com/rumata-estor/modx3-mcp/releases/tag/v1.2.4)

**MODX Revolution 2.8.x · 1.2.4-pl**

MODX MCP ЖИ көмекшілерін Model Context Protocol арқылы MODX сайтына қосады. PHP компоненті токенмен қорғалған HTTP API ұсынады, ал жергілікті Node.js клиенті оны stdio арқылы MCP сервері ретінде қолжетімді етеді. Қосымша оркестрация сервері қажет емес.

## Жүктеп алу

[modx2mcp-1.2.4-pl.transport.zip](https://github.com/rumata-estor/modx3-mcp/releases/download/v1.2.4/modx2mcp-1.2.4-pl.transport.zip) · [SHA256SUMS](https://github.com/rumata-estor/modx3-mcp/releases/download/v1.2.4/SHA256SUMS)

## Жылдам бастау

1. MODX басқару панеліне кіріп, сәйкес ZIP файлын пакеттер менеджері арқылы орнатыңыз.
2. «Компоненттер → MODX MCP» бөлімін ашыңыз. HTTPS, рұқсаттар мен API токенін тексеріңіз; жаңа орнатылымда компонент әдепкіде өшірулі.
3. Қатынау қорғалғаннан кейін ғана `modxmcp.enabled` мәнін қосыңыз. Токенді MCP клиентінің құпия конфигурациясында сақтаңыз.

## Бар орнатылымды жаңарту

`modx2mcp` және `modx3mcp` пакеттерінің қалыпты жаңаруы баптаулар мен токенді сақтайды. MODX 2 сайтында бұрынғы `modxmcp` пакеті болса, ол басқа пакет идентификаторы: жоймас бұрын баптаулар мен токеннің сақтық көшірмесін жасаңыз.

## Қауіпсіздік және сенім моделі

**API токені — әкімшілік құпия дерек.** Расталған сұраулар sudo құқығы бар MODX қызметтік пайдаланушысы арқылы орындалады. Жарамды токен PHP кодын қоса, сайттың маңызды бөліктерін өзгертуге мүмкіндік беруі мүмкін.

**Толық техникалық анықтамалар:** [API параметрлері (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/API.md) · [қауіпсіздік (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/SECURITY.md) · [әзірлеуші құжаттамасы (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/DEVELOPMENT.md). Техникалық кестелердің толық нұсқасы әзірге ағылшын тілінде.
