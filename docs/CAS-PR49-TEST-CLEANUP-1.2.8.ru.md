# PR #49 / 1.2.8 — инвентаризация и уборка устаревших тестов

Это осторожная **карта обнаруженных вызовов**, а не заявление о полном покрытии операций.

- Всего зарегистрированных мутаций: **116**.
- Имеют запись `verified_scenarios` в машинном реестре: **22**.
- Не имеют такой записи: **94**.
- Буквально названы в выполняемых интеграционных PHP CI-сценариях: **29**. Наличие вызова не гарантирует успешный сценарий, отрицательный тест, откат или readback.
- Найдены только в удаляемых устаревших live-сценариях: **61**.
- Не найдены ни в этих CI-сценариях, ни в устаревших live-файлах: **4**.

## Почему удалены старые исполняемые сценарии

Пятнадцать файлов `_build/test.live-*` не подключены к актуальной матрице GitHub Actions. Четырнадцать используют reflection для отключения модульного Runtime и не требуют `MODXMCP_DISPOSABLE_CI`. Ещё один выполняет TV-операции по адресу сайта и с параметрами старого CAS. Это **исторические инструменты**, несовместимые с обязательным защищённым маршрутом записи 1.2.8. Их нельзя считать испытаниями текущей версии или предлагать запускать на живых сайтах.

Исходники доступны в истории Git до данного удаления (коммит `7ac665a`), без исполняемых копий в текущем дереве. Удаление не заменяет перенос полезных сценариев на штатный MODX MCP с обязательными CAS/Ed25519 и одноразовым CI.

Удалены файлы:

- `_build/test.live-element-file-mutations.php`
- `_build/test.live-element-mutations.php`
- `_build/test.live-final-mutations.php`
- `_build/test.live-media-mutations.php`
- `_build/test.live-migx-mutations.php`
- `_build/test.live-minishop2-mutations.php`
- `_build/test.live-modular-parity.php`
- `_build/test.live-mutation-parity.php`
- `_build/test.live-ops-mutations.php`
- `_build/test.live-package-mutations.php`
- `_build/test.live-property-set-mutations.php`
- `_build/test.live-resource-ops-mutations.php`
- `_build/test.live-resource-tv-cas.php`
- `_build/test.live-versionx-mutation.php`
- `_build/test.live-virtualpage-mutations.php`

## Действия без индивидуального подтверждения

| Действие | Риск | Обнаруженная активная CI-фикстура | Устаревший тест (исторически) |
| --- | --- | --- | --- |
| `add_user_to_group` | critical | `test.ci-functional-acceptance.php` | `test.live-mutation-parity.php` |
| `assign_property_set` | review | — | `test.live-property-set-mutations.php` |
| `assign_resource_to_group` | critical | — | `test.live-mutation-parity.php` |
| `bulk_resources` | review | `test.ci-functional-acceptance.php` | `test.live-final-mutations.php` |
| `clear_cache` | review | `test.ci-protected-auth.php` | `test.live-ops-mutations.php` |
| `clear_tv_values` | review | — | `test.live-ops-mutations.php` |
| `clientconfig_create_setting` | review | — | — |
| `clientconfig_delete_setting` | review | — | — |
| `clientconfig_update_setting` | review | — | — |
| `create_access_policy` | critical | — | `test.live-mutation-parity.php` |
| `create_access_policy_template` | critical | — | `test.live-mutation-parity.php` |
| `create_context` | high | `test.ci-acl-context-defaults.php` | `test.live-mutation-parity.php` |
| `create_context_setting` | high | `test.ci-acl-context-defaults.php` | `test.live-mutation-parity.php` |
| `create_media_source` | review | — | `test.live-media-mutations.php` |
| `create_namespace` | high | — | `test.live-mutation-parity.php` |
| `create_property_set` | review | — | `test.live-property-set-mutations.php` |
| `create_provider` | review | — | `test.live-package-mutations.php` |
| `create_resource_group` | critical | `test.ci-acl-resourcegroup-access.php` | `test.live-mutation-parity.php` |
| `create_role` | critical | `test.ci-functional-acceptance.php` | `test.live-mutation-parity.php` |
| `create_user` | critical | `test.ci-functional-acceptance.php`, `test.ci-protected-auth.php` | `test.live-mutation-parity.php` |
| `create_user_group` | critical | `test.ci-acl-context-access.php`, `test.ci-acl-resourcegroup-access.php`, `test.ci-functional-acceptance.php` | `test.live-mutation-parity.php` |
| `delete_access_policy` | critical | — | `test.live-mutation-parity.php` |
| `delete_access_policy_template` | critical | — | `test.live-mutation-parity.php` |
| `delete_context` | high | `test.ci-acl-context-defaults.php` | `test.live-mutation-parity.php` |
| `delete_context_setting` | high | `test.ci-acl-context-defaults.php` | `test.live-mutation-parity.php` |
| `delete_media_source` | review | — | `test.live-media-mutations.php` |
| `delete_namespace` | high | — | `test.live-mutation-parity.php` |
| `delete_property_set` | review | — | `test.live-property-set-mutations.php` |
| `delete_provider` | review | — | `test.live-package-mutations.php` |
| `delete_resource_group` | critical | `test.ci-acl-resourcegroup-access.php` | `test.live-mutation-parity.php` |
| `delete_role` | critical | `test.ci-functional-acceptance.php` | `test.live-mutation-parity.php` |
| `delete_user` | critical | `test.ci-functional-acceptance.php` | `test.live-mutation-parity.php` |
| `delete_user_group` | critical | `test.ci-acl-context-access.php`, `test.ci-acl-resourcegroup-access.php`, `test.ci-functional-acceptance.php` | `test.live-mutation-parity.php` |
| `duplicate_element` | review | — | `test.live-resource-ops-mutations.php` |
| `duplicate_resource` | review | — | `test.live-resource-ops-mutations.php` |
| `edit_element_lines` | review | `test.ci-static-tv-fields.php` | `test.live-element-file-mutations.php` |
| `flush_permissions` | review | — | `test.live-ops-mutations.php` |
| `grant_context_access` | critical | `test.ci-acl-context-access.php` | `test.live-mutation-parity.php` |
| `grant_resourcegroup_access` | critical | `test.ci-acl-resourcegroup-access.php` | `test.live-mutation-parity.php` |
| `make_static` | review | `test.ci-static-tv-fields.php` | `test.live-element-file-mutations.php` |
| `migx_create_config` | review | — | `test.live-migx-mutations.php` |
| `migx_delete_config` | review | — | `test.live-migx-mutations.php` |
| `migx_update_config` | review | — | `test.live-migx-mutations.php` |
| `ms2_assign_option_to_category` | review | — | `test.live-minishop2-mutations.php` |
| `ms2_create_category` | review | — | `test.live-minishop2-mutations.php` |
| `ms2_create_link_type` | review | — | `test.live-minishop2-mutations.php` |
| `ms2_create_option` | review | — | `test.live-minishop2-mutations.php` |
| `ms2_create_product_link` | review | — | `test.live-minishop2-mutations.php` |
| `ms2_delete_link_type` | review | — | `test.live-minishop2-mutations.php` |
| `ms2_delete_product_link` | review | — | `test.live-minishop2-mutations.php` |
| `ms2_update_category` | review | — | `test.live-minishop2-mutations.php` |
| `ms2_update_link_type` | review | — | `test.live-minishop2-mutations.php` |
| `ms2_update_option` | review | — | `test.live-minishop2-mutations.php` |
| `ms2_update_order` | review | — | `test.live-minishop2-mutations.php` |
| `ms2_update_product_options` | review | — | `test.live-minishop2-mutations.php` |
| `refresh_uris` | review | — | `test.live-resource-ops-mutations.php` |
| `remove_locks` | review | — | `test.live-resource-ops-mutations.php` |
| `remove_resource_from_group` | critical | — | `test.live-mutation-parity.php` |
| `remove_user_from_group` | critical | `test.ci-functional-acceptance.php` | `test.live-mutation-parity.php` |
| `reorder_resources` | review | — | `test.live-resource-ops-mutations.php` |
| `replace_across` | review | `test.ci-functional-acceptance.php` | `test.live-final-mutations.php` |
| `revert_lexicon_entry` | high | — | `test.live-mutation-parity.php` |
| `revoke_context_access` | critical | `test.ci-acl-context-access.php` | `test.live-mutation-parity.php` |
| `revoke_resourcegroup_access` | critical | `test.ci-acl-resourcegroup-access.php` | `test.live-mutation-parity.php` |
| `set_lexicon_entry` | review | — | `test.live-mutation-parity.php` |
| `unassign_property_set` | review | — | `test.live-property-set-mutations.php` |
| `undelete_resource` | review | — | `test.live-resource-ops-mutations.php` |
| `update_access_policy` | critical | — | `test.live-mutation-parity.php` |
| `update_access_policy_template` | critical | — | `test.live-mutation-parity.php` |
| `update_context` | high | — | `test.live-mutation-parity.php` |
| `update_context_access` | critical | `test.ci-acl-context-access.php` | `test.live-mutation-parity.php` |
| `update_context_setting` | high | `test.ci-acl-context-defaults.php` | `test.live-mutation-parity.php` |
| `update_element_property` | review | — | — |
| `update_group_member` | critical | — | `test.live-mutation-parity.php` |
| `update_media_source` | review | — | `test.live-media-mutations.php` |
| `update_namespace` | high | — | `test.live-mutation-parity.php` |
| `update_property_set` | review | — | `test.live-property-set-mutations.php` |
| `update_provider` | review | — | `test.live-package-mutations.php` |
| `update_resource_group` | critical | — | `test.live-mutation-parity.php` |
| `update_resourcegroup_access` | critical | `test.ci-acl-resourcegroup-access.php` | `test.live-mutation-parity.php` |
| `update_role` | critical | `test.ci-functional-acceptance.php` | `test.live-mutation-parity.php` |
| `update_user` | critical | `test.ci-functional-acceptance.php` | `test.live-mutation-parity.php` |
| `update_user_group` | critical | `test.ci-functional-acceptance.php` | `test.live-mutation-parity.php` |
| `versionx_revert_version` | review | — | `test.live-versionx-mutation.php` |
| `virtualpage_clear_cache` | review | — | `test.live-virtualpage-mutations.php` |
| `virtualpage_create_event` | review | — | `test.live-virtualpage-mutations.php` |
| `virtualpage_create_handler` | review | — | `test.live-virtualpage-mutations.php` |
| `virtualpage_create_route` | review | — | `test.live-virtualpage-mutations.php` |
| `virtualpage_delete_event` | review | — | `test.live-virtualpage-mutations.php` |
| `virtualpage_delete_handler` | review | — | `test.live-virtualpage-mutations.php` |
| `virtualpage_delete_route` | review | — | `test.live-virtualpage-mutations.php` |
| `virtualpage_update_event` | review | — | `test.live-virtualpage-mutations.php` |
| `virtualpage_update_handler` | review | — | `test.live-virtualpage-mutations.php` |
| `virtualpage_update_route` | review | — | `test.live-virtualpage-mutations.php` |

## Что проверять дальше

1. Для каждого из 29 действий из активного интеграционного CI подтвердить конкретный успешный/запрещённый исход и независимый readback по журналу, только затем занести доказанный сценарий в `verified_scenarios`.
2. Для остальных 65 — подобрать или написать отдельный изолированный сценарий. Не копировать старый обход `modularRuntime` и не запускать тесты против production.
3. Приоритет: критичные ACL, установка/удаление пакетов, `run_processor`, операции с файлами/пакетами; не выдавать общий revision gate за доказательство полной атомарности.
