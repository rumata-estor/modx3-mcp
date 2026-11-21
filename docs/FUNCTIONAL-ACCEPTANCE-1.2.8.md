# MODX MCP 1.2.8 — functional acceptance / функциональная приёмка

> **RU:** Тестовая ветка 1.2.8. Пакеты и метаданные ещё сохраняют номер 1.2.7; этот документ не означает выпуск или успешный интеграционный прогон.
>
> **EN:** Experimental 1.2.8 branch. Package metadata still says 1.2.7; this is neither a release announcement nor proof that integration tests have passed.

## Русский

**Выполнено на изолированной исходной копии:**

- 199 клиентских действий сопоставлены с серверными, 116 операций записи имеют обязательные CAS-схемы.
- Обе платформы проходят проверки подготовки release-staging и одинаковых безопасных значений 26 настроек.
- Отрицательные автономные проверки процессоров, CAS, Ed25519, токена и ACL проходят.
- Проверка безопасности отказывается запускать реальные интеграционные тесты за пределами `MODXMCP_DISPOSABLE_CI=1` и пути `/home/runner/`.
- Синтаксис новых PHP/JavaScript-тестов проверен. Комментарии в файлах тестов двуязычные.

**Подготовлено, но требует выполнения на реальном одноразовом MODX:**

| Сценарий | Проверяемый результат | CI-скрипт |
| --- | --- | --- |
| Обычный MCP (без Ed25519) | Подключение по токену, HTTP-чтение, создание скрытой страницы, обновление, отказ stale CAS, мягкое удаление | `_build/test.ci-normal-http.cjs` |
| CAS CRUD | Создание, чтение, изменение и удаление ресурса, чанка, системной настройки; сохранение значений после операции | `_build/test.ci-functional-acceptance.php` |
| Пользователи/ACL | Создание/чтение/удаление временного неактивного пользователя и группы исключительно через MCP | `_build/test.ci-functional-acceptance.php` |
| Разрешённый процессор | Успешное выполнение `resource/getlist`; запрет `security/user/create` через обычный список | `_build/test.ci-functional-acceptance.php` |
| Защищённый процессор | Подписанный Ed25519 запуск точного процессора, расходование nonce, запрет повторного вызова, отказ при выключенном переключателе | `_build/test.ci-protected-auth.php` |
| Защищённый HTTP | Привязка подписи к телу запроса, сайту, контуру и CAS; успешные разрешённые изменения | `_build/test.ci-protected-http.cjs` |
| TV/транзакции | Сохранение TV, конкурирующие и устаревшие состояния, откат транзакций | `_build/test.ci-pr46-tv-cas.php` |

**Матрица проверки совместимости (11 комбинаций):** MODX 2.8.9 + PHP 7.4/8.0/8.1/8.2/8.3; MODX 3.1.2 + PHP 7.4/8.1/8.2; MODX 3.2.4 + PHP 8.1/8.2/8.3. Во всех случаях — одноразовая MariaDB 10.11. PHP 8.4+ не считаются подтверждёнными по результатам более ранних тестов, MODX 3.3 не является опубликованным стабильным релизом. Страница официальных требований: https://docs.modx.com/current/en/getting-started/server-requirements. Матрица в `.github/workflows/cas-pr46-isolated.yml`.

**Известный дефект первого интеграционного запуска:** в MODX 2.8.9/3.2.4 штатный ответ процессора `create_user` содержит сообщение об успешном создании и временный пароль, но MCP трактует его как ошибку. Результат операции потенциально неоднозначен; автоматический повтор запрещён до проверки состояния через MCP. Тест остаётся падающим и вынесен в конец workflow, чтобы остальные испытания выполнялись прежде него.

**Приёмка запрещена, если:** хотя бы одна разрешённая операция падает; данные не читаются после записи; конфликт CAS изменяет данные; пользователь/ACL остаются после отказа; API-токен теряется после обновления; обе версии MODX имеют разные политики; возникли непроверенные побочные эффекты. Это также относится к недоказанным 94 индивидуальным сценариям: один зелёный smoke-тест их не закрывает.

**Статус:** автономные и статические проверки пройдены, **положительные интеграционные сценарии в новых незакоммиченных изменениях пока НЕ выполнялись**. На текущем рабочем сервере нет готовой отдельной disposable MODX/MariaDB. Не заменять этот тест работой на production.

## English

**Verified offline:** client/server parity for 199 actions, mandatory CAS schemas for 116 mutating actions, staged builds for both MODX versions, 26 aligned defaults, negative authorization/CAS/ACL/token unit tests, and guarded refusal outside disposable CI.

**Not yet executed on these uncommitted changes:** full positive HTTP and dispatcher flows, successful user/group CRUD, signed allowlisted processor execution, upgrade token preservation on real databases, and transaction rollback/readback in disposable MariaDB.

The compatibility matrix contains 11 combinations: MODX 2.8.9 on PHP 7.4/8.0/8.1/8.2/8.3, MODX 3.1.2 on PHP 7.4/8.1/8.2, and MODX 3.2.4 on PHP 8.1/8.2/8.3, all on disposable MariaDB 10.11. The initial integration run exposed an ambiguous create_user outcome (the processor reports creation but MCP treats the response as an error); this is a known failing acceptance gate, not a successful test. The isolated workflow is `.github/workflows/cas-pr46-isolated.yml`. Until that matrix is green on the actual changed sources, **do not claim release readiness** and do not deploy this branch to production.
