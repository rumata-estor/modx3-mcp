# Контроль индивидуальных CAS-сценариев — MODX MCP 1.2.8

> Автоматически формируется из `_build/cas-mutation-inventory.json`.
> Источник истины — **имена проверенных сценариев**, а не наличие вызова
> в тесте. Этот список не является сертификатом безопасности.

**Всего мутаций:** 116. **Есть хотя бы один индивидуальный
сценарий:** 59. **Не имеют индивидуальных сценариев:** 57.

Общее тестирование CAS, подписей и раннего отказа действует отдельно.
Оно не гарантирует rollback внешних эффектов, защиту от Manager или
безопасный повтор после обрыва связи. Подтверждённый сценарий отмечает
только указанное в его имени свойство. PR #67 остаётся блокером ACL.

## P0 — критические операции — 7 без сценария

| Действие | CAS-граница | Следующая проверка |
|---|---|---|
| `add_user_to_group` | `revision_reserved_before_action` | Readback + stale/replay + независимые права и Manager race |
| `assign_resource_to_group` | `revision_reserved_before_action` | Readback + stale/replay + независимые права и Manager race |
| `delete_resource_group` | `revision_reserved_before_action` | Readback + stale/replay + независимые права и Manager race |
| `remove_resource_from_group` | `revision_reserved_before_action` | Readback + stale/replay + независимые права и Manager race |
| `remove_user_from_group` | `revision_reserved_before_action` | Readback + stale/replay + независимые права и Manager race |
| `update_group_member` | `revision_reserved_before_action` | Readback + stale/replay + независимые права и Manager race |
| `update_resource_group` | `revision_reserved_before_action` | Readback + stale/replay + независимые права и Manager race |

## P1 — операции повышенного риска — 1 без сценария

| Действие | CAS-граница | Следующая проверка |
|---|---|---|
| `revert_lexicon_entry` | `revision_reserved_before_action` | Readback + stale/replay + целевые настройки/разрешения |

## P2 — остальные операции — 49 без сценария

| Действие | CAS-граница | Следующая проверка |
|---|---|---|
| `assign_property_set` | `revision_reserved_before_action` | Readback + stale/replay + отказ/восстановление |
| `bulk_resources` | `revision_reserved_before_action` | Readback + stale/replay + отказ/восстановление |
| `clear_cache` | `revision_reserved_before_action` | Readback + stale/replay + отказ/восстановление |
| `clear_tv_values` | `revision_reserved_before_action` | Readback + stale/replay + отказ/восстановление |
| `clientconfig_create_setting` | `revision_reserved_before_action` | Readback + stale/replay + отказ/восстановление |
| `clientconfig_delete_setting` | `revision_reserved_before_action` | Readback + stale/replay + отказ/восстановление |
| `clientconfig_update_setting` | `revision_reserved_before_action` | Readback + stale/replay + отказ/восстановление |
| `create_provider` | `revision_reserved_before_action` | Readback + stale/replay + отказ/восстановление |
| `delete_provider` | `revision_reserved_before_action` | Readback + stale/replay + отказ/восстановление |
| `duplicate_element` | `revision_reserved_before_action` | Readback + stale/replay + отказ/восстановление |
| `duplicate_resource` | `revision_reserved_before_action` | Readback + stale/replay + отказ/восстановление |
| `edit_element_lines` | `revision_reserved_before_action` | Readback + stale/replay + отказ/восстановление |
| `flush_permissions` | `revision_reserved_before_action` | Readback + stale/replay + отказ/восстановление |
| `make_static` | `revision_reserved_before_action` | Readback + stale/replay + отказ/восстановление |
| `migx_create_config` | `revision_reserved_before_action` | Readback + stale/replay + отказ/восстановление |
| `migx_delete_config` | `revision_reserved_before_action` | Readback + stale/replay + отказ/восстановление |
| `migx_update_config` | `revision_reserved_before_action` | Readback + stale/replay + отказ/восстановление |
| `ms2_assign_option_to_category` | `revision_reserved_before_action` | Readback + stale/replay + отказ/восстановление |
| `ms2_create_category` | `revision_reserved_before_action` | Readback + stale/replay + отказ/восстановление |
| `ms2_create_link_type` | `revision_reserved_before_action` | Readback + stale/replay + отказ/восстановление |
| `ms2_create_option` | `revision_reserved_before_action` | Readback + stale/replay + отказ/восстановление |
| `ms2_create_product_link` | `revision_reserved_before_action` | Readback + stale/replay + отказ/восстановление |
| `ms2_delete_link_type` | `revision_reserved_before_action` | Readback + stale/replay + отказ/восстановление |
| `ms2_delete_product_link` | `revision_reserved_before_action` | Readback + stale/replay + отказ/восстановление |
| `ms2_update_category` | `revision_reserved_before_action` | Readback + stale/replay + отказ/восстановление |
| `ms2_update_link_type` | `revision_reserved_before_action` | Readback + stale/replay + отказ/восстановление |
| `ms2_update_option` | `revision_reserved_before_action` | Readback + stale/replay + отказ/восстановление |
| `ms2_update_order` | `revision_reserved_before_action` | Readback + stale/replay + отказ/восстановление |
| `ms2_update_product_options` | `revision_reserved_before_action` | Readback + stale/replay + отказ/восстановление |
| `refresh_uris` | `revision_reserved_before_action` | Readback + stale/replay + отказ/восстановление |
| `remove_locks` | `revision_reserved_before_action` | Readback + stale/replay + отказ/восстановление |
| `reorder_resources` | `revision_reserved_before_action` | Readback + stale/replay + отказ/восстановление |
| `replace_across` | `revision_reserved_before_action` | Readback + stale/replay + отказ/восстановление |
| `set_lexicon_entry` | `revision_reserved_before_action` | Readback + stale/replay + отказ/восстановление |
| `unassign_property_set` | `revision_reserved_before_action` | Readback + stale/replay + отказ/восстановление |
| `undelete_resource` | `revision_reserved_before_action` | Readback + stale/replay + отказ/восстановление |
| `update_element_property` | `database_transaction` | Реальная запись, rollback при сбое, readback |
| `update_provider` | `revision_reserved_before_action` | Readback + stale/replay + отказ/восстановление |
| `versionx_revert_version` | `revision_reserved_before_action` | Readback + stale/replay + отказ/восстановление |
| `virtualpage_clear_cache` | `revision_reserved_before_action` | Readback + stale/replay + отказ/восстановление |
| `virtualpage_create_event` | `revision_reserved_before_action` | Readback + stale/replay + отказ/восстановление |
| `virtualpage_create_handler` | `revision_reserved_before_action` | Readback + stale/replay + отказ/восстановление |
| `virtualpage_create_route` | `revision_reserved_before_action` | Readback + stale/replay + отказ/восстановление |
| `virtualpage_delete_event` | `revision_reserved_before_action` | Readback + stale/replay + отказ/восстановление |
| `virtualpage_delete_handler` | `revision_reserved_before_action` | Readback + stale/replay + отказ/восстановление |
| `virtualpage_delete_route` | `revision_reserved_before_action` | Readback + stale/replay + отказ/восстановление |
| `virtualpage_update_event` | `revision_reserved_before_action` | Readback + stale/replay + отказ/восстановление |
| `virtualpage_update_handler` | `revision_reserved_before_action` | Readback + stale/replay + отказ/восстановление |
| `virtualpage_update_route` | `revision_reserved_before_action` | Readback + stale/replay + отказ/восстановление |

## Уже зарегистрированные индивидуальные сценарии

| Действие | Риск | Подтверждённые сценарии (не полная гарантия) |
|---|---|---|
| `assign_element_category` | review | `revision_write_failure_rollback` |
| `create_access_policy` | critical | `disposable_unassigned_policy_create_readback_revision_stale_replay` |
| `create_access_policy_template` | critical | `disposable_grouped_policy_template_create_readback_stale_replay` |
| `create_category` | review | `revision_write_failure_rollback` |
| `create_context` | high | `disposable_context_create_readback_stale_replay_no_anonymous` |
| `create_context_setting` | high | `disposable_context_setting_create_readback_stale_replay` |
| `create_element` | review | `regular_resource_revision_write_failure_rollback` |
| `create_media_file` | high | `revision_reservation_refusal`, `positive_file_readback`, `path_traversal_denied` |
| `create_media_folder` | high | `revision_reservation_refusal`, `positive_file_readback`, `path_traversal_denied` |
| `create_media_source` | review | `disposable_media_source_definition_create_readback_stale_replay` |
| `create_namespace` | high | `disposable_namespace_metadata_create_readback_stale_replay` |
| `create_property_set` | review | `disposable_unassigned_property_set_create_readback_stale_replay` |
| `create_resource_group` | critical | `disposable_create_readback_revision_stale_replay` |
| `create_role` | critical | `disposable_create_readback_revision_stale_replay` |
| `create_system_setting` | review | `revision_write_failure_rollback` |
| `create_user` | critical | `disposable_inactive_user_create_readback_revision_stale_replay_secret_fields` |
| `create_user_group` | critical | `disposable_create_readback_revision_stale_replay` |
| `delete_access_policy` | critical | `disposable_unassigned_policy_delete_readback_stale_replay` |
| `delete_access_policy_template` | critical | `disposable_grouped_policy_template_delete_readback_stale_replay` |
| `delete_category` | review | `revision_write_failure_rollback` |
| `delete_context` | high | `disposable_context_delete_readback_stale_replay` |
| `delete_context_setting` | high | `disposable_context_setting_delete_readback_stale_replay` |
| `delete_element` | review | `regular_resource_soft_delete_revision_write_failure_rollback` |
| `delete_media_file` | high | `revision_reservation_refusal`, `positive_file_readback`, `path_traversal_denied` |
| `delete_media_folder` | high | `revision_reservation_refusal`, `positive_file_readback`, `path_traversal_denied` |
| `delete_media_source` | review | `disposable_media_source_definition_delete_readback_stale_replay` |
| `delete_namespace` | high | `disposable_namespace_metadata_delete_readback_stale_replay` |
| `delete_property_set` | review | `disposable_unassigned_property_set_delete_readback_stale_replay` |
| `delete_role` | critical | `manager_out_of_band_edit_stale_target_denied` |
| `delete_system_setting` | review | `revision_write_failure_rollback` |
| `delete_user` | critical | `manager_out_of_band_edit_stale_target_denied` |
| `delete_user_group` | critical | `manager_out_of_band_edit_stale_target_denied` |
| `empty_recycle_bin` | critical | `revision_reservation_refusal_only` |
| `grant_context_access` | critical | `disposable_context_acl_grant_readback_stale_replay` |
| `grant_resourcegroup_access` | critical | `disposable_resourcegroup_acl_grant_readback_stale_replay` |
| `install_package` | critical | `revision_reservation_refusal_only` |
| `regenerate_token` | critical | `revision_reservation_refusal_only` |
| `rename_media_file` | high | `revision_reservation_refusal`, `positive_file_readback`, `path_traversal_denied` |
| `revoke_context_access` | critical | `disposable_context_acl_revoke_readback_stale_replay` |
| `revoke_resourcegroup_access` | critical | `disposable_resourcegroup_acl_revoke_readback_stale_replay` |
| `run_processor` | critical | `revision_reservation_refusal_only` |
| `uninstall_package` | critical | `revision_reservation_refusal_only` |
| `update_access_policy` | critical | `disposable_unassigned_policy_description_update_readback_stale_replay` |
| `update_access_policy_template` | critical | `disposable_grouped_policy_template_description_update_readback_stale_replay` |
| `update_category` | review | `revision_write_failure_rollback` |
| `update_context` | high | `disposable_context_name_update_readback_stale_replay_no_anonymous` |
| `update_context_access` | critical | `disposable_context_acl_update_readback_stale_replay` |
| `update_context_setting` | high | `disposable_context_setting_update_readback_stale_replay` |
| `update_element` | review | `regular_resource_revision_write_failure_rollback` |
| `update_media_file` | high | `revision_reservation_refusal`, `positive_file_readback`, `path_traversal_denied`, `concurrent_media_cas` |
| `update_media_source` | review | `disposable_media_source_definition_description_update_readback_stale_replay` |
| `update_namespace` | high | `disposable_namespace_metadata_update_readback_stale_replay` |
| `update_property_set` | review | `disposable_unassigned_property_set_description_update_readback_stale_replay` |
| `update_resource_tvs` | review | `revision_write_failure_rollback`, `concurrent_cas_conflict`, `raw_tv_and_inherit` |
| `update_resourcegroup_access` | critical | `disposable_resourcegroup_acl_update_readback_stale_replay` |
| `update_role` | critical | `manager_out_of_band_edit_stale_target_denied` |
| `update_system_setting` | review | `revision_write_failure_rollback` |
| `update_user` | critical | `manager_out_of_band_edit_stale_target_denied` |
| `update_user_group` | critical | `manager_out_of_band_edit_stale_target_denied` |

## Правило учёта

Нельзя добавлять сценарий в `verified_scenarios` без реального
исполняемого теста на одноразовых MODX 2/3, читаемого маркера
и успешного GitHub Actions. Если подтверждён только ранний
отказ по ревизии, это не отдельный бизнес-сценарий.

