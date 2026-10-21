# Жүйелік баптаулар — MODX MCP

[English](SETTINGS.md) · [Русский](SETTINGS.ru.md) · [Español](SETTINGS.es.md) · [Deutsch](SETTINGS.de.md) · [Français](SETTINGS.fr.md) · [Українська](SETTINGS.uk.md) · [Қазақша](SETTINGS.kk.md) · [Nederlands](SETTINGS.nl.md) · [Polski](SETTINGS.pl.md)

Барлық 16 параметр «Жүйе → Жүйелік баптаулар» бөліміндегі `modxmcp` атаулар кеңістігінде орналасқан. MODX 2 және MODX 3 пакеттерінің әдепкі мәндері ортақ.

**Маңызды:** құрал топтарын өшіру серверлік рұқсаттарды алмастырмайды. Тікелей HTTP клиенттері стандартты Node.js клиентінің қауіпсіздік шараларын автоматты қолданбайды.

| Кілт | Әдепкі | Сипаттама |
| --- | --- | --- |
| `modxmcp.enabled` | `0` | Токенмен расталған API әрекеттерін қосады не өшіреді; алғаш орнатқанда өшірулі. |
| `modxmcp.api_token` | `(generated)` | `X-MCP-Token` тақырыбымен жіберілетін құпия токен; қалыпты жаңарту оны сақтайды. |
| `modxmcp.service_user_id` | `0` | MODX қызметтік пайдаланушысы; `0` алғашқы белсенді sudo пайдаланушысын таңдайды. |
| `modxmcp.require_https` | `1` | HTTPS талап етеді; өндірісте қосулы қалсын. |
| `modxmcp.trust_proxy_https` | `0` | HTTPS тақырыптарына тек сенімді кері прокси арқылы сеніңіз. |
| `modxmcp.allowed_ips` | `(empty)` | Рұқсат етілген IP және IPv4 CIDR ауқымдары; бос болса IP бойынша шектеу жоқ. |
| `modxmcp.disabled_groups` | `versionx,virtualpage,minishop2,migx,access,property_sets,contexts,package_management,namespaces,lexicon` | Үтірмен бөлінген өшірілген құрал топтары; толық қауіпсіздік шекарасы емес. |
| `modxmcp.allow_run_processor` | `0` | Кез келген MODX процессорын орындауға рұқсат береді; әдепкіде өшірулі. |
| `modxmcp.allow_root_filesystem_read` | `0` | Түбірлік «Filesystem» медиа көзін оқуға рұқсат; әдепкіде өшірулі. |
| `modxmcp.component_code_roots` | `core/components,assets/components` | Компонент кодын тексеруге рұқсат етілген каталогтар; барлық файлдық әрекетті шектемейді. |
| `modxmcp.max_payload_bytes` | `1048576` | POST JSON денесінің байттағы ең үлкен өлшемі; асқанда 413 қайтарылады. |
| `modxmcp.max_read_bytes` | `262144` | Файлды оқуда бір рет қайтарылатын байттар санының шегі. |
| `modxmcp.auto_static` | `0` | Элементтерге автоматты статикалық файл жасайды. |
| `modxmcp.audit_log` | `1` | Қолдау көрсетілген өзгерістерді аудит журналына жазады; барлық сұрауды қамтымайды. |
| `modxmcp.debug` | `0` | Ішкі қателерді толық көрсетеді; өндірісте өшіріңіз. |
| `modxmcp.core_path` | `{core_path}components/modxmcp/` | Компонент ядросының жолы; тек тексерілген арнайы орнатылымда өзгертіңіз. |

**Толық техникалық анықтамалар:** [API параметрлері (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/API.md) · [қауіпсіздік (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/SECURITY.md) · [әзірлеуші құжаттамасы (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/DEVELOPMENT.md). Техникалық кестелердің толық нұсқасы әзірге ағылшын тілінде.
