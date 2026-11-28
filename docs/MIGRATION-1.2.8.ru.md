# Миграция 1.2.7 → 1.2.8

Этот документ — честный список изменений контракта между стабильной 1.2.7 и 1.2.8.
Имена инструментов, параметры чтения и HTTP-контракт не изменились; изменилась модель записи.

## Главное: запись теперь требует CAS

В 1.2.8 все мутации (78 инструментов) требуют CAS-предусловия:

1. прочитать `get_site_state` → `site_revision` (+ `resource_fingerprint_version`);
2. передать в мутации `observed_site_revision` и `strict_site_revision: true`;
3. для затрагиваемых объектов передать `read_set` с sha256-фингерпринтами
   (до 32 записей, фингерпринты возвращает `get_site_state` с `read_set`).

Без этого мутация вернёт `CAS_REQUIRED`. Клиент 1.2.7, не знающий про CAS,
теряет запись сразу после обновления — **обновляйте клиент и сервер вместе**.

## Что ещё меняется

| Было в 1.2.7 | Стало в 1.2.8 |
|---|---|
| `run_processor` с любым процессором при `allow_run_processor=1` | settings-процессоры запрещены всегда; security/workspace/package/namespace — только privileged + внешний Ed25519-пермит; `processors_path` запрещён |
| `update_media_file` перезаписывал файл | обязателен `expected_sha256` (сверка на сервере через Media Source) |
| membership/ACL-мутации работали на любом движке БД | требуются InnoDB + `REPEATABLE READ` + транзакция + индекс |
| legacy fallback диспетчера при сбое модульного рантайма | fail-closed: `CAS_RUNTIME_REQUIRED`, включая MODX 2 |
| `read_error_log`/`read_audit_log` с большим `limit` | кэп 1000 строк и 4 МиБ на ответ, метаданные `result_complete` |
| чтение секретных настроек | значения `[REDACTED]` по имени ключа (password/token/secret/api_key, `modxmcp.*`) |
| `enableanonymous` в любом регистре | см. PR: отключение принимается в любом регистре, включение требует ACL |

## Чек-лист обновления

1. Прочитайте `system_info` → `modxmcp_version` и `get_site_state` → `cas_required`.
   Если `cas_required=true` — ваш клиент обязан поддерживать CAS-протокол.
2. Обновите клиент: реализуйте цикл «прочитать state → фингерпринты → мутация с предусловиями».
3. Проверьте движок таблиц (InnoDB) и isolation level (REPEATABLE READ) для membership-мутаций.
4. Убедитесь, что настройка `modxmcp.external_authorization_required` существует и равна `0`
   или `1` (апгрейд in-place с 1.2.7: строка отсутствует — см. миграционный PR).
5. Скрипты, писавшие media/static файлы «вслепую», переведите на `expected_sha256`/CAS.

## Известные ограничения beta

- Строгий режим записи статических файлов (`modxmcp.static_io_mode=strict`) требует
  `/usr/bin/python3`; по умолчанию используется `portable` (см. `docs/STATIC-IO-MODES-1.2.8*`).
- Ветка помечена в CHANGELOG как «NOT a release»; не устанавливайте её на production.
