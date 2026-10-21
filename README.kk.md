# MODX MCP — MODX сайтын ЖИ көмегімен басқару

[English](README.md) · [Русский](README.ru.md) · [Español](README.es.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Українська](README.uk.md) · [Қазақша](README.kk.md) · [Nederlands](README.nl.md) · [Polski](README.pl.md)

**MODX MCP 1.2.4 · MODX Revolution 2.8.x / 3.x · 193 / 192**

MODX MCP ЖИ көмекшілерін Model Context Protocol арқылы MODX сайтына қосады. PHP компоненті токенмен қорғалған HTTP API ұсынады, ал жергілікті Node.js клиенті оны stdio арқылы MCP сервері ретінде қолжетімді етеді. Қосымша оркестрация сервері қажет емес.

## Бұл жоба кімге арналған?

MODX әзірлеушілеріне, веб-студияларға және сайттарын ЖИ арқылы бақылаумен басқарғысы келетін әкімшілерге арналған.

## Мүмкіндіктер мен құралдар

- Ресурстарды, үлгілерді, чанктарды, сниппеттерді, плагиндер мен TV өрістерін іздеу, оқу және өзгерту.
- Тәуелділіктер графынан байланыстарды, жарамсыз сілтемелерді және пайдаланылмайтын кодты зерттеу.
- Жүйелік баптаулармен, медиа көздерімен, пакеттермен және қолдау көрсетілетін қосымшалармен жұмыс істеу.
- Өзгерістерді алдын ала шарттар, сынақ режимі және резервтік көшірмелер арқылы қорғау.

## Жылдам бастау

MODX негізгі нұсқасына сәйкес көлік пакетін таңдаңыз. MCP клиентіне Node.js 18 немесе жаңарақ нұсқа қажет.

- **MODX 2.8.x:** [MODX 2 пакеті](https://github.com/rumata-estor/modx3-mcp/releases/download/v1.2.4/modx2mcp-1.2.4-pl.transport.zip)
- **MODX 3.x:** [MODX 3 пакеті](https://github.com/rumata-estor/modx3-mcp/releases/download/v1.2.4/modx3mcp-1.2.4-pl.transport.zip)
- [GitHub v1.2.4](https://github.com/rumata-estor/modx3-mcp/releases/tag/v1.2.4) · [SHA256SUMS](https://github.com/rumata-estor/modx3-mcp/releases/download/v1.2.4/SHA256SUMS)

MODX басқару панеліне кіріп, сәйкес ZIP файлын пакеттер менеджері арқылы орнатыңыз.

«Компоненттер → MODX MCP» бөлімін ашыңыз. HTTPS, рұқсаттар мен API токенін тексеріңіз; жаңа орнатылымда компонент әдепкіде өшірулі.

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

Қатынау қорғалғаннан кейін ғана `modxmcp.enabled` мәнін қосыңыз. Токенді MCP клиентінің құпия конфигурациясында сақтаңыз.

## Қауіпсіздік және сенім моделі

**API токені — әкімшілік құпия дерек.** Расталған сұраулар sudo құқығы бар MODX қызметтік пайдаланушысы арқылы орындалады. Жарамды токен PHP кодын қоса, сайттың маңызды бөліктерін өзгертуге мүмкіндік беруі мүмкін.

**Маңызды:** құрал топтарын өшіру серверлік рұқсаттарды алмастырмайды. Тікелей HTTP клиенттері стандартты Node.js клиентінің қауіпсіздік шараларын автоматты қолданбайды.

## Құжаттама

[Орнату](docs/INSTALL.kk.md) · [Қауіпсіздік](docs/SECURITY.kk.md) · [Баптаулар](docs/SETTINGS.kk.md) · [Мүмкіндіктер](docs/CAPABILITIES.kk.md) · [API](docs/API.kk.md)

**Толық техникалық анықтамалар:** [API параметрлері (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/API.md) · [қауіпсіздік (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/SECURITY.md) · [әзірлеуші құжаттамасы (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/DEVELOPMENT.md). Техникалық кестелердің толық нұсқасы әзірге ағылшын тілінде.
