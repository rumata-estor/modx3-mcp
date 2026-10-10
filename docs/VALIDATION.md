**English** | [Русский](VALIDATION.ru.md)

# Connector validation status

> User documentation: [installation](INSTALL.md), [capabilities](CAPABILITIES.md), [API reference](API.md), [security](SECURITY.md).

Stable-release 1.2.0 validation: 2026-10-07. Static, processor-compatibility, Runtime-CAS and platform transport checks were repeated for the 192-action release contract.

## Shared architecture

One source tree contains a shared modular core plus platform adapters:

- `Platform/Modx2Platform.php`
- `Platform/Modx3Platform.php`
- `Registry/ToolRegistry.php`
- `Legacy/LegacyActionAdapter.php`
- modular `Tools/`
- shared endpoint `endpoint/api.common.php`

The modular registry now owns the complete public action contract: 81 read-only actions and 111 mutation actions, 192/192 total. The legacy dispatcher remains only as a compatibility/reference layer; normal requests are handled by the modular runtime.

## 1.2.0 release readiness

Version 1.2.0 is the current shared-source MODX 2 / MODX 3 stable release. Both MODX 2.8.9-pl and MODX 3.2.4-pl completed dedicated transport release-smoke cycles and the 81-action live read-parity matrix. MODX 3.2.4-pl additionally completed the complete 14-suite live regression. On MODX 2 the positive MIGX lifecycle is not runnable because MIGX is absent; its positive 3/3 lifecycle passed on MODX 3 and the MODX 2 absence/static contracts remain covered.

## 1.2.0 modular read-only coverage

The 1.2.0 registry contains all 81 read-only actions. Compared with the 74 read-only actions in v1.1.0, version 1.2.0 adds `get_site_state`, two ClientConfig reads, and four access-policy/resource-group read helpers.

<!-- READONLY_MIGRATION_COVERAGE: 81/81 -->

The 1.2.0 read-only migration is complete: all 81 read-only server actions are registered in the modular runtime. The coverage marker above is checked by `_build/test.migration-coverage.py` so the documented current-source count cannot silently drift from the code.

Migrated actions:

- `get_capabilities`
- `get_site_state`
- `system_info`
- `project_overview`
- `list_resources`
- `check_integrations`
- `list_system_settings`
- `get_system_setting`
- `list_tv_input_types`
- `suggest_tv_type`
- `list_installed_components`
- `list_elements`
- `get_element`
- `list_media_sources`
- `get_media_source`
- `list_media_source_files`
- `read_media_source_file`
- `get_resource_tvs`
- `list_tv_values`
- `get_component_files`
- `read_component_file`
- `list_contexts`
- `get_context`
- `list_context_settings`
- `get_context_setting`
- `list_namespaces`
- `list_lexicon_entries`
- `list_lexicon_topics`
- `list_property_sets`
- `get_property_set`
- `list_providers`
- `search_packages`
- `list_users`
- `get_user`
- `list_user_groups`
- `get_user_group`
- `list_user_group_members`
- `list_roles`
- `get_role`
- `list_access_policies`
- `list_access_policy_templates`
- `list_access_permissions`
- `list_resource_groups`
- `list_context_access`
- `list_resourcegroup_access`
- `help`
- `list_actions`
- `read_error_log`
- `read_audit_log`
- `describe_object`
- `search_code`
- `find_usages`
- `view_element`
- `dependency_graph`
- `versionx_list_versions`
- `versionx_get_version`
- `migx_list_configs`
- `migx_get_config`
- `virtualpage_list_events`
- `virtualpage_get_event`
- `virtualpage_list_handlers`
- `virtualpage_get_handler`
- `virtualpage_list_routes`
- `virtualpage_get_route`
- `virtualpage_resolve_route`
- `ms2_list_link_types`
- `ms2_get_link_type`
- `ms2_list_product_links`
- `ms2_list_categories`
- `ms2_list_orders`
- `ms2_get_order`
- `ms2_list_option_types`
- `ms2_list_options`
- `ms2_get_option`
- `ms2_get_product_options`

## Current modular mutation coverage

The modular runtime now owns all 111 mutation actions.

<!-- MUTATION_MIGRATION_COVERAGE: 111/111 -->

The first mutation block is the direct MODX-processor layer: Access/ACL, Context,
Namespace and Lexicon writes. These actions now execute through
`ProcessorMutationTool` + `MutationProcessorCatalog` instead of the legacy
dispatcher. The mutation migration is now complete: all 111 mutation actions
are registered in the modular Runtime, so all 192 server actions have a modular
implementation.

On 2026-10-02 the then-current v1.1.0 modular registry was re-audited on the live MODX 3.2.4-pl sandbox with `_build/test.live-modular-parity.php`. That full live matrix covered the 182-action release contract, including all 74 read-only actions available at that time.

On 2026-10-06 the new `get_site_state` action and Runtime-CAS path first received a dedicated live smoke on both MODX 3.2.4-pl and MODX 2.8.9-pl sandboxes. Each sandbox confirmed `atomic_preconditions=true`; stale create/update attempts were rejected with `STALE_STATE`; intervening content was preserved; revision state persisted; and test data was cleaned up. On 2026-10-07 the 1.2.0 release validation then reran the full 81-action read parity on both platforms, the complete 14-suite live regression on MODX 3, and the MODX 2 core/runtime suites supported by the installed extras.

Filesystem media-source reads remain disabled on the sandbox by `modxmcp.allow_root_filesystem_read=0`; parity therefore verifies the same security denial on both paths rather than weakening the setting for a test. Capability groups that are disabled in persistent settings are enabled only in the parity process memory, so the live audit does not leave the sandbox with broader permissions.

## Automated tests

- Architecture: PASS.
- Build architecture: PASS.
- v1.2.0 client/server contract: PASS, 192 actions (static).
- Endpoint architecture: PASS.
- MODX2 PHP compatibility: PASS.
- Platform mapping: PASS.
- Modular core: PASS.
- MODX3 processor compatibility: PASS, 113 processor files on the live MODX3 sandbox.
- Release portability: PASS.
- Release staging: PASS.
- v1.2.0 migration coverage consistency: PASS, read 81/81; mutations 111/111 (static).
- v1.2.0 live modular-vs-legacy read parity: PASS for all 81 read-only actions on MODX 3.2.4-pl and MODX 2.8.9-pl; CAS-only capability flags are validated explicitly rather than treated as legacy-equivalent fields.
- Live mutation parity: PASS, 46 transactional checks across 39 generic processor mutations plus the dedicated `set_lexicon_entry` tool; all writes rolled back and CAS revision metadata is validated separately from the domain payload.
- Live system/TV/ops mutation parity: PASS, 8/8 actions.
- Live Property-set mutation parity: PASS, 5/5 actions with transactional rollback.
- Live media mutation parity: PASS, 9/9 actions in an isolated temporary media source; all artifacts removed.
- Live resource/ops mutation parity: PASS, 6/6 safe-live actions; empty_recycle_bin skipped because the sandbox had a pre-existing deleted resource, regenerate_token skipped to preserve the active API token.
- Live VersionX mutation parity: PASS for the safe confirm=true + missing-version contract; no live content was reverted.
- Live miniShop2 mutation parity: PASS, 12/12 actions; positive create paths were transactionally rolled back and real orders/products were not modified.
- Live final mutation parity: PASS, bulk_resources and replace_across both match legacy for dry-run and real writes on temporary test objects.
- v1.2.0 Runtime-CAS smoke on MODX 3.2.4-pl and MODX 2.8.9-pl: PASS for `get_site_state`, stale update rejection (`target_changed`), stale create rejection (`target_now_exists`), unchanged revision on rejected writes, preservation of the intervening external content, revision persistence, and cleanup.
- v1.2.0 full MODX 3 live regression: PASS, all 14 live parity suites completed with exit code 0 on the release Runtime.
- Two globally destructive actions were not invoked live: empty_recycle_bin (the sandbox had a pre-existing deleted resource) and regenerate_token (to preserve the active API token); both remain covered by modular registration, static contract checks, and legacy-equivalent implementation review.
- Live Package-management mutation parity: PASS, 5/5 actions; package install/uninstall tested only on non-destructive paths.
- Live MIGX mutation parity: PASS, 3/3 actions with transactional rollback.
- Live VirtualPage mutation absence parity: PASS, 10/10 actions match legacy when VirtualPage is not installed; positive lifecycle remains to be verified on a VirtualPage-enabled test site.
- Live element mutation parity: PASS for create/update/dry-run delete/delete across all 7 element types.
- Live element file mutation parity: PASS for make_static + DB/static line editing across chunk/snippet/template/plugin, with test files removed afterwards.
- Element list/get live parity matrix: PASS for all 7 supported element types.
- Element view live parity matrix: PASS for all 4 supported viewable element types.
- Filesystem media-source disabled-security contract: PASS through the full parity run.

## MODX 3

Live-tested with v1.2.0 on a dedicated MODX 3.2.4-pl sandbox. The full transport release-smoke passed, all 14 live parity suites completed with exit code 0, and the original 16 configurable `modxmcp.*` settings were restored after the destructive install/reinstall/uninstall cycle. The CAS `modxmcp.site_revision` value is treated as runtime state rather than a 17th administrator setting.

Status: LIVE-VALIDATED.

## MODX 2

Live-tested on a clean MODX 2.8.9-pl sandbox.

- transport release-smoke: PASS for build, fresh install, endpoint CRUD, same-package reinstall with settings preserved, clean uninstall, and final installation;
- v1.2.0 modular read parity: PASS for all 81 read-only actions; absent element fixtures are skipped by the read-only matrix and covered separately by element lifecycle tests;
- mutation parity: PASS, 46 checks across 39 generic processor mutations plus `set_lexicon_entry`;
- element lifecycle parity: PASS for all 7 supported element types;
- property sets: 5/5; resource/ops: 6/6 safe-live actions; system/TV/ops: 8/8; media: 9/9; package management: 5/5;
- final bulk/replace suite: 4 checks across 2/2 actions; element DB/static-file suite: all 4 supported file-backed element types;
- `empty_recycle_bin` and `regenerate_token` remain intentionally skipped live for the same safety reasons as on MODX 3;
- positive MIGX lifecycle is skipped because MIGX is not installed on this MODX 2 sandbox; the same MIGX mutation lifecycle passes 3/3 on MODX 3. miniShop2 mutation parity passes 12/12 here, VersionX passes its safe not-found contract, and VirtualPage absence parity passes 10/10.

Status: LIVE-VALIDATED FOR CORE/RUNTIME AND RELEASE-SMOKE.
