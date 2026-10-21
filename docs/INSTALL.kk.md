# MODX MCP орнату және қосу

[English](INSTALL.md) · [Русский](INSTALL.ru.md) · [Español](INSTALL.es.md) · [Deutsch](INSTALL.de.md) · [Français](INSTALL.fr.md) · [Українська](INSTALL.uk.md) · [Қазақша](INSTALL.kk.md) · [Nederlands](INSTALL.nl.md) · [Polski](INSTALL.pl.md)

**MODX MCP 1.2.4 · MODX Revolution 2.8.x / 3.x · 193 / 192**

MODX негізгі нұсқасына сәйкес көлік пакетін таңдаңыз. MCP клиентіне Node.js 18 немесе жаңарақ нұсқа қажет.

## 1. MODX 2 / MODX 3

- [MODX 2 пакеті](https://github.com/rumata-estor/modx3-mcp/releases/download/v1.2.4/modx2mcp-1.2.4-pl.transport.zip) — MODX 2.8.x
- [MODX 3 пакеті](https://github.com/rumata-estor/modx3-mcp/releases/download/v1.2.4/modx3mcp-1.2.4-pl.transport.zip) — MODX 3.x
- [SHA256SUMS](https://github.com/rumata-estor/modx3-mcp/releases/download/v1.2.4/SHA256SUMS)

## 2. Жылдам бастау

1. MODX басқару панеліне кіріп, сәйкес ZIP файлын пакеттер менеджері арқылы орнатыңыз.
2. «Компоненттер → MODX MCP» бөлімін ашыңыз. HTTPS, рұқсаттар мен API токенін тексеріңіз; жаңа орнатылымда компонент әдепкіде өшірулі.
3. Қатынау қорғалғаннан кейін ғана `modxmcp.enabled` мәнін қосыңыз. Токенді MCP клиентінің құпия конфигурациясында сақтаңыз.

## 3. Node.js / MCP

Төмендегі мысалмен stdio қолдайтын MCP қолданбасын баптаңыз. Мысал домені мен токенін ауыстырып, жарияланған v1.2.4 тегін пайдаланыңыз.

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

## 4. Қосылымды тексеру

MCP клиентін қайта іске қосып, алдымен `modx_system_info` немесе `modx_project_overview` сияқты тек оқу операциясын орындаңыз. GET күй жауабы токеннің жарамдылығын дәлелдемейді.

## Бар орнатылымды жаңарту

`modx2mcp` және `modx3mcp` пакеттерінің қалыпты жаңаруы баптаулар мен токенді сақтайды. MODX 2 сайтында бұрынғы `modxmcp` пакеті болса, ол басқа пакет идентификаторы: жоймас бұрын баптаулар мен токеннің сақтық көшірмесін жасаңыз.

## Ақаулықтарды жою

- `401`: `MODX_MCP_TOKEN` / `modxmcp.api_token`
- `403`: `modxmcp.enabled`, `modxmcp.require_https`, `modxmcp.allowed_ips`
- `404`: `/assets/components/modxmcp/api.php`
- `413`: `modxmcp.max_payload_bytes`
- `500`: MODX error log and `error_id`.

**Толық техникалық анықтамалар:** [API параметрлері (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/API.md) · [қауіпсіздік (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/SECURITY.md) · [әзірлеуші құжаттамасы (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/DEVELOPMENT.md). Техникалық кестелердің толық нұсқасы әзірге ағылшын тілінде.
