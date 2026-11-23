# MODX MCP 1.2.8 — сводка консолидации и оставшиеся security gates (23.11.2026)

> **Только тестовая ветка** `feature/1.2.8-cas-pr46-20261121`. Ни один пункт не разрешает merge в `main`, установку на production, публикацию пакета или обход серверной защиты. Снимок состояния на `f95e1a5559718921c4de9bc38c4b2398be2ade33`; при дальнейшем развитии ветки повторно проверять head и CI.

## Уже интегрировано в тестовую 1.2.8

- CAS-предусловия для мутаций, сырые значения TV, transactional DB-CAS, индивидуальные доказательные сценарии реестра **116/116** на применимых поддерживаемых платформах. **116/116 — это покрытие реестра, а не доказательство полного отсутствия уязвимостей**.
- Безопасная остановка при непроверенном результате и `MUTATION_OUTCOME_UNCERTAIN`; тесты COMMIT/ROLLBACK, сбоев журналирования, потери DB connection и SIGKILL (PR #164 и связанные патчи).
- Межузловая координация CAS через один общий MySQL/MariaDB writer / GET_LOCK; отдельные PHP-FPM контейнеры с раздельными файловыми системами проверены в PR #173. Это **не** тест двух физических хостов, TLS/proxy, Galera или multi-primary.
- ACL membership TOCTOU для пяти операций с InnoDB/gap locking и запретом обхода через универсальные процессоры: PR #162. Явная контролируемая миграция индекса для существующих инсталляций: PR #172; **никакого автоматического DDL на production**.
- Защита секретов, прав и ошибок, опасных package-параметров и одноразового signed nonce, fail-closed у TV-отношений и static file read, строгие имена/пути Media Source: PR #165–#170 и ранее принятые security-патчи.
- Комплексный тест настоящего transport upgrade из опубликованного 1.2.7 в staged 1.2.8 с сохранением конфигурации и `CAS_REQUIRED` для старого небезопасного клиента: PR #171.
- Отказ без сдвига CAS для всех 26 мутаций отсутствующих optional add-ons: PR #169. Дублирование ресурса и stale replay уже вошли в интеграционную ветку; открытые PR #123 и #103 не должны добавляться повторно.
- miniShop2 проверяется **только на MODX 2**; VersionX 2.x имеет свой native positive E2E. Не переносить эти утверждения на miniShop2/MODX3 или VersionX 3.x.

### Контрольная точка CI (точный head выше)

- Общий CI **4/4 success**: https://github.com/rumata-estor/modx3-mcp/actions/runs/45155131814
- Disposable CAS/MODX matrix **12/12 success**: https://github.com/rumata-estor/modx3-mcp/actions/runs/45155120761
- Настоящий transport upgrade 1.2.7→staged 1.2.8 **2/2 success**: https://github.com/rumata-estor/modx3-mcp/actions/runs/45155120709
- Реальный cross-container PHP-FPM сценарий: PR #173; не объявлять заменой независимым физическим серверам: https://github.com/rumata-estor/modx3-mcp/pull/173

## P0 — обязательные незакрытые проверки до релиза

1. **Полный маршрут клиента и нестандартная сеть.** Два отдельных физических PHP-FPM/сайта с одним writer DB, валидность DB-session GET_LOCK, node restart, reverse proxy/TLS/Node bridge/Ed25519, lost response/reconnect; смешанные клиенты 1.2.7↔1.2.8. Отдельно зафиксировать, что Galera/multi-primary не поддержан этой моделью без самостоятельного дизайна.
2. **Последствия и журнал.** Систематически проверить аварийные окна до/после claim, эффекта, COMMIT, SUCCEEDED и HTTP-ответа, журнал PENDING/UNCERTAIN, запрет повторов и автоматического внешнего rollback. Особое внимание package install/uninstall, сторонним processor, cache/plugins/webhooks и общему `withMutationLock`.
3. **Статические файлы и symlink TOCTOU.** PR #175 **принят только в тестовую 1.2.8**: один `StaticFileWriteSupport` для чтения/записи/TV make_static, режимы `portable` (PHP без дополнительной зависимости) и `strict` (опциональный Python 3 + `proc_open` + Unix `dir_fd`), запрещён небезопасный прямой fallback. Проверены 4/4 CI, 12/12 disposable MODX 2/3 и 2/2 upgrade до merge. **Release blocker остаётся:** portable не устраняет parent-directory TOCTOU, strict меняет inode и может терять ACL/xattrs; нужны независимое review, реальные PHP-FPM/права/хостинги и враждебные конкурентные записи. Старые PR #95 и #174 закрыты без merge как superseded.
4. **Media Source.** Настоящие нестандартные источники (наследуемые local, S3/SFTP/object drivers), разрешения, path traversal, parent symlink race и удаление папок. Использовать штатный API драйвера, не универсальный прямой путь в FS; неизвестные внешние побочные эффекты — `unknown`, а не ложный rollback.
5. **ACL и установленные сайты.** Выполнить CLI migration `--check` на копиях существующих MODX 2/3 баз и отдельно согласованное `--apply --ack-backup --ack-writes-paused` только на **одноразовом стенде**. Проверить другие индексы, lock timeout/deadlock, сторонние ACL-плагины и отсутствие неожиданных побочных эффектов. Без автоматической миграции production.
6. **Контракт клиента/ответов.** Явно документировать, что legacy write без `_runtime_preconditions` теперь предсказуемо получает `CAS_REQUIRED`; старое чтение сохраняется. Проверить install/upgrade и настоящий read→CAS→write по Node bridge и MCP transport на MODX 2/3, включая человекочитаемые безопасные ошибки и outcome `not_applied / rolled_back / partial / unknown / completed`. Нельзя возвращать обход старой небезопасной записи ради «совместимости».
7. **Секреты/обход авторизации.** Независимые отрицательные проверки protected `modxmcp.*` через context settings, `run_processor`, rename и namespace; Ed25519 nonce/replay, ACL revoke между read и commit, сохранность прав и секретов в журнале и исключениях.

## P1 — важные ограничения, совместимость и данные

- **Property Set (R13):** валидация структуры перед записью, защита от частичной порчи при malformed `properties`.
- **Сторонние процессоры (R10/R22):** безопасный namespace/allowlist вместо произвольного `processors_path`, верификация ожидаемых параметров, запрет traversal и защищённых маршрутов.
- **VersionX 3.x:** отдельная API-адаптация и installed native revert E2E; текущий маршрут 2.x нельзя считать совместимым с 3.x.
- **miniShop2 MODX2:** отдельный реальный status-transition/partial-failure сценарий `ms2_update_order`; остальные обещания только в пределах уже проверенных native API.
- **Файловые права и static:** PHP-FPM UID/GID/ACL, read-only, каталог без права создавать temp, внутренние ссылки, crash/rename, NFS, Windows, хостинги без Python/`proc_open`. Предусмотреть явный fail-closed с понятным сообщением.
- **Дополнения и версии:** реальная installed matrix pdoTools/Fenom, MIGX, ClientConfig, Collections, Gallery, FetchIt/FormIt и MODX3/PHP8.4; не путать отсутствие дополнения с его реальной совместимостью.
- **Формат результата/UX:** R06 raw/rendered TV, R07/R11 ошибки и безопасная диагностика, R12 TV schema, R19 безопасное файловое восстановление, R20 `$_FILES` upload, R21 rate limit. Это отдельные API/compatibility changes, а не доказанные исправления P0.

## Открытые PR / правило интеграции

- #49: ветка разработки 1.2.8 → `main`; **НЕ MERGE**, сохранить draft до отдельного разрешения.
- #103: старый кандидат resource duplicate; функциональность уже принята; дубликат.
- #123: старый кандидат 26 missing-addon denial; закрыт по содержанию принятым #169; дубликат.
- #95: старый descriptor I/O candidate с short-write риском; не включать.
- #174: закрыт без merge как superseded; descriptor-anchored helper переиспользован в принятом тестовом PR #175, без обязательной зависимости Python по умолчанию.
- #175: **merged только в test/1.2.8**; режимы portable/strict и общие API проверены, выпускные риски ACL/xattrs и hosting PHP-FPM открыты.

## Release gate / решение

Пока открыты P0, **не делать release 1.2.8, merge в `main` или установку на production**. Любые исправления — только в отдельных тестовых ветках с PR base `feature/1.2.8-cas-pr46-20261121`, независимым CI, последующей интеграцией в тот же тестовый контур. После любого изменения повторять общий CI 4/4, MODX/CAS 12/12 и upgrade 2/2; при затрагивании lock/FS — отдельные fault/concurrency тесты. Перед выпуском — ручное разрешение владельца проекта.
