# Поддержка безопасности (Security Policy)

## Поддерживаемые версии

Исправления безопасности выпускаются для актуальной линии и тестовой 1.2.8. Пожалуйста, убедитесь, что вы используете свежий релиз.

| Версия проекта | Поддерживается |
| -------------- | -------------- |
| `main` (стабильная 1.2.x) | :white_check_mark: |
| `release/1.2.8-*` (тестовая) | :white_check_mark: приоритетно |
| Старые релизы | :x: |

## Как сообщить об уязвимости

Я очень серьёзно отношусь к безопасности компонента. Если вы нашли уязвимость или проблему с безопасностью в `modx3-mcp`, пожалуйста, **не создавайте публичный PR/issue** (issues в репозитории отключены в том числе поэтому), чтобы деталь эксплойта не стала публичной до выпуска патча.

Вместо этого я прошу вас:

1. Использовать механизм **GitHub Security Advisories**: вкладка **Security → Advisories → "Report a vulnerability"** в этом репозитории — отчёт остаётся приватным до релиза исправления.
2. Альтернативно — написать мне напрямую (контакты доступны в моём GitHub-профиле).

В отчёте помогут: затронутый инструмент MCP, версия компонента (`system_info → modxmcp_version`), минимальные шаги воспроизведения и требуемые привилегии (manager / authenticated / anonymous).

**Процесс обработки:**

- Я постараюсь подтвердить получение отчёта в течение 48 часов.
- Дам оценку проблемы и примерные сроки исправления.
- После релиза патча упомяну вас в CHANGELOG и релизной записи, если вы этого хотите.

**Важно включить:** приватный канал «Report a vulnerability» работает только при включённом **Private vulnerability reporting** в Settings → Code security этого репозитория (см. [документацию GitHub](https://docs.github.com/ru/code-security/getting-started/github-security-features)).

## Граница модели угроз

Компонент защищает собственную поверхность MCP: авторизацию, CAS, изоляцию настроек `modxmcp.*`, границы static-файлов. Он **не защищает** от скомпрометированного ядра MODX, Manager-аккаунта или прямого доступа к БД — с такими правами namespace, настройки и процессоры меняются в обход MCP.

## Раскрытие

Исправление готовится приватно и выходит в ближайшем патч-релизе; публичное раскрытие — после релиза (coordinated disclosure). Для CVE используется GitHub Security Advisory.

---

*EN summary: do not report vulnerabilities publicly; use Security → Advisories → "Report a vulnerability" (or the maintainer's profile contacts). First response within 48 hours; coordinated disclosure; CVE via GitHub Security Advisory. The component protects the MCP surface only — not a compromised MODX core, manager account, or DB.*
