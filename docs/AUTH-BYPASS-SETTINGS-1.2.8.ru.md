# MODX MCP 1.2.8 — проверка обходов авторизации настроек

**Дата:** 22 ноября 2026. **Контур:** одноразовый GitHub CI, MODX 2/3.
**Итог:** 22/22 попыток не изменили доверенную системную настройку;
**13/22** отклонённых запросов изменили site_revision. Это разные свойства.

## Проверенные маршруты

- create/update/delete_system_setting: точный `modxmcp.*`, смешанный
  регистр/пробелы, переименование обычного ключа в защищённый;
- create/update/delete_context_setting: protected key/name, чужой контекст;
- create/update_context: вложенные `settings` и `context_settings`;
- run_processor: `system/settings/update`, `system/setting/update`,
  `context/setting/update`, `context/update`,
  `security/user/setting/update`, попытка `processors_path`.

Все случаи проверялись через **штатный MCP** с актуальными
CAS-предусловиями. До/после сверялось значение
`modxmcp.external_authorization_required` непосредственно в
одноразовом MODX. Изменений trust anchor не выявлено.

## Найденное поведение: отклонённая команда расходует CAS

В обоих MODX 2/3 первые 9 попыток прямого изменения системных
настроек отвергнуты **до резервирования** site_revision.
Оставшиеся 13 — ошибки context setting, context/update и
run_processor — тоже отклонены, но уже **после** изменения ревизии.

Это не доказательство обхода Ed25519 или прав ACL. Это разрыв
между предварительным отказом (preflight) и резервированием CAS для
операций, классифицированных как `external`. Слишком позднее
проверяемое условие может привести к ложному STALE_STATE у другого
клиента, даже если business state не менялся.

**Нельзя считать поведение допустимым лишь из-за зелёного CI.**
Тест `_build/test.ci-settings-auth-bypass.php` записывает каждый
`AUTH_BYPASS_CAS_PRECHECK_GAP` как отдельное свидетельство и не
утверждает, что CAS безопасен для этих маршрутов. Не смешивать с
независимым P0 ACL TOCTOU PR #67.

## Критерии исправления

1. Для **детерминированных отказов** context-setting protected key,
   непрозрачных context settings и disabled/denied run_processor
   выполнять preflight **до** резервирования site_revision и
   до любого вызова processor. Не дублировать/не обходить
   независимую Ed25519-авторизацию.
2. После исправления все 22 запроса должны отвергаться без
   изменения protected settings, `site_revision`, побочных ACL и
   созданных объектов.
3. Негативный тест не должен пропускать CAS-изменения. Добавить
   положительные сценарии для разрешённых context settings и
   отдельно — с валидным signed permit (protected mode).
4. Повторить на disposable MODX 2/3 и проверить работу остальных
   CAS-операций второго агента; дождаться интеграции, не править
   параллельно общую `StateSupport` без координации.

**Свидетельства:**
[MODX 2/3 12/12](https://github.com/rumata-estor/modx3-mcp/actions/runs/45058188636);
[общий CI 4/4](https://github.com/rumata-estor/modx3-mcp/actions/runs/45058193882).
