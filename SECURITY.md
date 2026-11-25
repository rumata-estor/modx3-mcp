<!-- MODX MCP — Security Policy / Политика безопасности -->
# Security Policy / Политика безопасности

## RU: Как сообщить об уязвимости

**Не публикуйте уязвимость в открытом PR, issue или обсуждении.**

Приоритетный канал — **приватные отчёты GitHub («Report a vulnerability»)**, вкладка
**Security → Advisories → Report a vulnerability** в этом репозитории. Отчёт остаётся
конфиденциальным до выпуска исправления.

Что приложить к отчёту:

- затронутый инструмент/эндпоинт MCP и версию компонента (`system_info → modxmcp_version`);
- минимальные шаги воспроизведения (payload, параметры);
- оценку влияния и требуемые привилегии (manager / authenticated / anonymous);
- при наличии — предложенный вариант исправления.

Ожидаемый срок первичного ответа — 7 дней. Отчёту присваивается статус:
принят / требует уточнения / не является уязвимостью. Автора указываем
в CHANGELOG и релизных записях по его желанию (credit).

## EN: Reporting a vulnerability

**Do not disclose vulnerabilities in public PRs, issues, or discussions.**

The preferred channel is **GitHub private vulnerability reporting**: the
**Security → Advisories → Report a vulnerability** tab of this repository.
Reports stay confidential until a fix is released.

Please include: affected MCP tool/endpoint, component version
(`system_info → modxmcp_version`), minimal reproduction steps, impact
assessment and required privileges. Expect a first response within 7 days;
authors are credited in the changelog upon request.

<!-- TODO(maintainer): при желании добавить прямой канал связи (email/Telegram) после включения
     приватного репортинга; без включённого «Private vulnerability reporting» этот файл не работает. -->

## Поддерживаемые версии / Supported versions

| Ветка / Version | Поддержка / Support |
| --- | --- |
| 1.2.x (stable, `main`) | :white_check_mark: исправления безопасности / security fixes |
| 1.2.8-beta (`release/1.2.8-*`) | :white_check_mark: приоритетно / priority |
| < 1.2.0 | :x: не поддерживается / unsupported |

## Граница модели угроз / Threat model boundary

Компонент защищает **собственную поверхность MCP** (авторизация, CAS,
изоляция настроек `modxmcp.*`, границы static-файлов). Он не защищает от
скомпрометированного ядра MODX, Manager-аккаунта или доступа к БД: атакующий
с такими правами может менять namespace, настройки и процессоры в обход MCP.

The component protects **its own MCP surface** (authorization, CAS, `modxmcp.*`
setting isolation, static file boundaries). It does not protect against a
compromised MODX core, manager account, or database access.

## Политика раскрытия / Disclosure policy

1. Исправление готовится приватно и выпускается в ближайшем патч-релизе.
2. Публичное раскрытие — после релиза исправления (coordinated disclosure).
3. Для CVE используется GitHub Security Advisory (CVE-запрос через GitHub).
