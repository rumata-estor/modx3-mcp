# Контроль индивидуальных CAS-сценариев — MODX MCP 1.2.8

> Автоматически формируется из `_build/cas-mutation-inventory.json`.
> Источник истины — **имена проверенных сценариев**, а не наличие вызова
> в тесте. Этот список не является сертификатом безопасности.

**Всего мутаций:** 116. **Есть хотя бы один индивидуальный
сценарий:** 114. **Не имеют индивидуальных сценариев:** 2.

Общее тестирование CAS, подписей и раннего отказа действует отдельно.
Оно не гарантирует rollback внешних эффектов, защиту от Manager или
безопасный повтор после обрыва связи. Подтверждённый сценарий отмечает
только указанное в его имени свойство. PR #67 остаётся блокером ACL.

**Поддержка платформы учитывается по дополнению:** miniShop2 проверяется и
засчитывается на **MODX 2**; MODX 3 не является целевой платформой miniShop2.
MiniShop3 на MODX 3 — отдельный компонент и отдельное API, не считается
проверенным этими сценариями. Маркер `disposable_modx2_*` однозначно
ограничивает доказательство MODX 2.

## P0 — критические операции — 0 без сценария

| Действие | CAS-граница | Следующая проверка |
|---|---|---|

## P1 — операции повышенного риска — 0 без сценария

| Действие | CAS-граница | Следующая проверка |
|---|---|---|

## P2 — остальные операции — 2 без сценария

| Действие | CAS-граница | Следующая проверка |
|---|---|---|
| `ms2_update_order` | `revision_reserved_before_action` | Readback + stale/replay + отказ/восстановление |
| `versionx_revert_version` | `revision_reserved_before_action` | Readback + stale/replay + отказ/восстановление |

## Уже зарегистрированные индивидуальные сценарии

| Действие | Риск | Подтверждённые сценарии (не полная гарантия) |
|---|---|---|
| `add_user_to_group` | critical | `disposable_inactive_user_group_membership_add_readback_stale_replay` |
| `assign_element_category` | review | `revision_write_failure_rollback` |
| `assign_property_set` | review | `disposable_chunk_property_set_assign_readback_stale_replay` |
| `assign_resource_to_group` | critical | `disposable_unpublished_resource_group_assign_readback_stale_replay` |
| `bulk_resources` | review | `disposable_two_resource_bulk_publish_preview_readback_stale_replay` |
| `clear_cache` | review | `disposable_native_cache_key_clear_readback_revision_stale_replay` |
| `clear_tv_values` | review | `disposable_single_tv_value_preview_confirm_readback_stale_replay` |
| `clientconfig_create_setting` | review | `pinned_clientconfig25_php82_create_readback_stale_replay` |
| `clientconfig_delete_setting` | review | `pinned_clientconfig25_php82_delete_readback_stale_replay` |
| `clientconfig_update_setting` | review | `pinned_clientconfig25_php82_update_readback_stale_replay` |
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
| `create_provider` | review | `disposable_loopback_provider_create_readback_stale_replay` |
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
| `delete_provider` | review | `disposable_unassigned_provider_native_seed_delete_readback_stale_replay` |
| `delete_resource_group` | critical | `disposable_unassigned_resource_group_delete_readback_stale_replay` |
| `delete_role` | critical | `manager_out_of_band_edit_stale_target_denied` |
| `delete_system_setting` | review | `revision_write_failure_rollback` |
| `delete_user` | critical | `manager_out_of_band_edit_stale_target_denied` |
| `delete_user_group` | critical | `manager_out_of_band_edit_stale_target_denied` |
| `duplicate_element` | review | `disposable_chunk_duplicate_independent_readback_stale_replay` |
| `duplicate_resource` | review | `disposable_unpublished_resource_copy_readback_stale_replay` |
| `edit_element_lines` | review | `disposable_chunk_exact_line_edit_readback_expect_guard_stale_replay` |
| `empty_recycle_bin` | critical | `revision_reservation_refusal_only` |
| `flush_permissions` | review | `disposable_native_permission_cache_flush_success_revision_stale_replay` |
| `grant_context_access` | critical | `disposable_context_acl_grant_readback_stale_replay` |
| `grant_resourcegroup_access` | critical | `disposable_resourcegroup_acl_grant_readback_stale_replay` |
| `install_package` | critical | `revision_reservation_refusal_only` |
| `make_static` | review | `disposable_static_tv_file_bytes_readback_revision_stale_replay` |
| `migx_create_config` | review | `disposable_installed_migx_create_config_readback_stale_replay` |
| `migx_delete_config` | review | `disposable_installed_migx_delete_config_readback_stale_replay` |
| `migx_update_config` | review | `disposable_installed_migx_update_config_readback_stale_replay` |
| `ms2_assign_option_to_category` | review | `disposable_modx2_pinned_ms2_assign_option_to_category_native_mcp_readback_stale_replay` |
| `ms2_create_category` | review | `disposable_modx2_pinned_ms2_create_category_native_mcp_readback_stale_replay` |
| `ms2_create_link_type` | review | `disposable_pinned_ms2_create_link_type_native_processor_readback_stale_replay` |
| `ms2_create_option` | review | `disposable_pinned_ms2_create_option_native_processor_list_readback_stale_replay` |
| `ms2_create_product_link` | review | `disposable_modx2_pinned_ms2_create_product_link_native_mcp_readback_stale_replay` |
| `ms2_delete_link_type` | review | `disposable_pinned_ms2_delete_link_type_native_processor_readback_stale_replay` |
| `ms2_delete_product_link` | review | `disposable_modx2_pinned_ms2_delete_product_link_native_mcp_readback_stale_replay` |
| `ms2_update_category` | review | `disposable_modx2_pinned_ms2_update_category_full_context_mcp_readback_stale_replay` |
| `ms2_update_link_type` | review | `disposable_pinned_ms2_update_link_type_native_processor_readback_stale_replay` |
| `ms2_update_option` | review | `disposable_pinned_ms2_update_option_native_processor_list_readback_stale_replay` |
| `ms2_update_product_options` | review | `disposable_modx2_pinned_ms2_update_product_options_native_mcp_readback_stale_replay` |
| `refresh_uris` | review | `disposable_unpublished_resource_refresh_uris_readback_stale_replay` |
| `regenerate_token` | critical | `revision_reservation_refusal_only` |
| `remove_locks` | review | `disposable_empty_install_remove_locks_revision_stale_replay` |
| `remove_resource_from_group` | critical | `disposable_unpublished_resource_group_remove_readback_stale_replay` |
| `remove_user_from_group` | critical | `disposable_inactive_user_group_membership_remove_readback_stale_replay` |
| `rename_media_file` | high | `revision_reservation_refusal`, `positive_file_readback`, `path_traversal_denied` |
| `reorder_resources` | review | `disposable_two_resource_menuindex_reorder_readback_stale_replay` |
| `replace_across` | review | `disposable_unique_chunk_replace_across_dry_run_readback_stale_replay` |
| `revert_lexicon_entry` | high | `disposable_namespace_lexicon_revert_readback_stale_replay` |
| `revoke_context_access` | critical | `disposable_context_acl_revoke_readback_stale_replay` |
| `revoke_resourcegroup_access` | critical | `disposable_resourcegroup_acl_revoke_readback_stale_replay` |
| `run_processor` | critical | `revision_reservation_refusal_only` |
| `set_lexicon_entry` | review | `disposable_namespace_lexicon_set_readback_stale_replay` |
| `unassign_property_set` | review | `disposable_chunk_property_set_unassign_readback_stale_replay` |
| `undelete_resource` | review | `disposable_unpublished_resource_undelete_readback_stale_replay` |
| `uninstall_package` | critical | `revision_reservation_refusal_only` |
| `update_access_policy` | critical | `disposable_unassigned_policy_description_update_readback_stale_replay` |
| `update_access_policy_template` | critical | `disposable_grouped_policy_template_description_update_readback_stale_replay` |
| `update_category` | review | `revision_write_failure_rollback` |
| `update_context` | high | `disposable_context_name_update_readback_stale_replay_no_anonymous` |
| `update_context_access` | critical | `disposable_context_acl_update_readback_stale_replay` |
| `update_context_setting` | high | `disposable_context_setting_update_readback_stale_replay` |
| `update_element` | review | `regular_resource_revision_write_failure_rollback` |
| `update_element_property` | review | `disposable_chunk_single_property_expected_value_and_site_revision_cas` |
| `update_group_member` | critical | `disposable_inactive_user_group_role_update_readback_stale_replay` |
| `update_media_file` | high | `revision_reservation_refusal`, `positive_file_readback`, `path_traversal_denied`, `concurrent_media_cas` |
| `update_media_source` | review | `disposable_media_source_definition_description_update_readback_stale_replay` |
| `update_namespace` | high | `disposable_namespace_metadata_update_readback_stale_replay` |
| `update_property_set` | review | `disposable_unassigned_property_set_description_update_readback_stale_replay` |
| `update_provider` | review | `disposable_loopback_provider_description_update_readback_stale_replay` |
| `update_resource_group` | critical | `disposable_unassigned_resource_group_rename_readback_stale_replay` |
| `update_resource_tvs` | review | `revision_write_failure_rollback`, `concurrent_cas_conflict`, `raw_tv_and_inherit` |
| `update_resourcegroup_access` | critical | `disposable_resourcegroup_acl_update_readback_stale_replay` |
| `update_role` | critical | `manager_out_of_band_edit_stale_target_denied` |
| `update_system_setting` | review | `revision_write_failure_rollback` |
| `update_user` | critical | `manager_out_of_band_edit_stale_target_denied` |
| `update_user_group` | critical | `manager_out_of_band_edit_stale_target_denied` |
| `virtualpage_clear_cache` | review | `disposable_pinned_virtualpage_cache_native_readback_stale_replay` |
| `virtualpage_create_event` | review | `disposable_pinned_virtualpage_create_event_mcp_readback_stale_replay` |
| `virtualpage_create_handler` | review | `disposable_pinned_virtualpage_create_handler_mcp_readback_stale_replay` |
| `virtualpage_create_route` | review | `disposable_pinned_virtualpage_create_route_mcp_readback_stale_replay` |
| `virtualpage_delete_event` | review | `disposable_pinned_virtualpage_delete_event_mcp_readback_stale_replay` |
| `virtualpage_delete_handler` | review | `disposable_pinned_virtualpage_delete_handler_mcp_readback_stale_replay` |
| `virtualpage_delete_route` | review | `disposable_pinned_virtualpage_delete_route_mcp_readback_stale_replay` |
| `virtualpage_update_event` | review | `disposable_pinned_virtualpage_update_event_mcp_readback_stale_replay` |
| `virtualpage_update_handler` | review | `disposable_pinned_virtualpage_update_handler_mcp_readback_stale_replay` |
| `virtualpage_update_route` | review | `disposable_pinned_virtualpage_update_route_mcp_readback_stale_replay` |

## Правило учёта

Нельзя добавлять сценарий в `verified_scenarios` без реального
исполняемого теста на одноразовой **поддерживаемой платформе** (MODX 2
для miniShop2; MODX 2/3 для универсальных инструментов), читаемого маркера
и успешного GitHub Actions. Если подтверждён только ранний
отказ по ревизии, это не отдельный бизнес-сценарий.

