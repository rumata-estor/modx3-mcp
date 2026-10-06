**English** | [Русский](VALIDATION.ru.md)

# Connector validation status

Stable-release live validation: 2026-10-02. Current development-source static validation and dedicated MODX 3 Runtime-CAS smoke: 2026-10-06.

## Shared architecture

One source tree contains a shared modular core plus platform adapters:

- `Platform/Modx2Platform.php`
- `Platform/Modx3Platform.php`
- `Registry/ToolRegistry.php`
- `Legacy/LegacyActionAdapter.php`
- modular `Tools/`
- shared endpoint `endpoint/api.common.php`

The modular registry now owns the complete public action contract: 75 read-only actions and 108 mutation actions, 183/183 total. The legacy dispatcher remains only as a compatibility/reference layer; normal requests are handled by the modular runtime.

## 1.1.0 release readiness

Version 1.1.0 is the first shared-source MODX 2 / MODX 3 stable release. MODX 2.8.9-pl and MODX 3.2.4-pl both completed dedicated transport release-smoke cycles and live validation; the final artifacts/checksums were verified and GitHub release `v1.1.0` was published.

## Current modular read-only coverage

The current development registry contains all 75 read-only tools. The 75th action is `get_site_state`, added after v1.1.0 for Runtime stale-state/CAS coordination.

<!-- READONLY_MIGRATION_COVERAGE: 75/75 -->

The current development-source read-only migration is complete: all 75 read-only server actions are registered in the modular runtime. The coverage marker above is checked by `_build/test.migration-coverage.py` so the documented current-source count cannot silently drift from the code.

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

The modular runtime now owns all 108 mutation actions.

<!-- MUTATION_MIGRATION_COVERAGE: 108/108 -->

The first mutation block is the direct MODX-processor layer: Access/ACL, Context,
Namespace and Lexicon writes. These actions now execute through
`ProcessorMutationTool` + `MutationProcessorCatalog` instead of the legacy
dispatcher. The mutation migration is now complete: all 108 mutation actions
are registered in the modular Runtime, so all 183 server actions have a modular
implementation.

On 2026-10-02 the then-current v1.1.0 modular registry was re-audited on the live MODX 3.2.4-pl sandbox with `_build/test.live-modular-parity.php`. That live matrix covered the 182-action release contract, including all 74 read-only actions available at that time. The development-only `get_site_state` action and Runtime-CAS path were added later and are not included in that historical live-validation claim.

Filesystem media-source reads remain disabled on the sandbox by `modxmcp.allow_root_filesystem_read=0`; parity therefore verifies the same security denial on both paths rather than weakening the setting for a test. Capability groups that are disabled in persistent settings are enabled only in the parity process memory, so the live audit does not leave the sandbox with broader permissions.

## Automated tests

- Architecture: PASS.
- Build architecture: PASS.
- Current development client/server contract: PASS, 183 actions (static).
- Endpoint architecture: PASS.
- MODX2 PHP compatibility: PASS.
- Platform mapping: PASS.
- Modular core: PASS.
- MODX3 processor compatibility: PASS, 113 processor files on the live MODX3 sandbox.
- Release portability: PASS.
- Release staging: PASS.
- Current development migration coverage consistency: PASS, read 75/75; mutations 108/108 (static).
- Stable v1.1.0 live modular-vs-legacy read parity: PASS for all 74 read-only actions in that release. The new `get_site_state` action is not yet included in the full live parity matrix.
- Live mutation processor parity: PASS, 46 transactional checks across 40 modular mutation actions; all writes rolled back.
- Live system/TV/ops mutation parity: PASS, 8/8 actions.
- Live Property-set mutation parity: PASS, 5/5 actions with transactional rollback.
- Live media mutation parity: PASS, 9/9 actions in an isolated temporary media source; all artifacts removed.
- Live resource/ops mutation parity: PASS, 6/6 safe-live actions; empty_recycle_bin skipped because the sandbox had a pre-existing deleted resource, regenerate_token skipped to preserve the active API token.
- Live VersionX mutation parity: PASS for the safe confirm=true + missing-version contract; no live content was reverted.
- Live miniShop2 mutation parity: PASS, 12/12 actions; positive create paths were transactionally rolled back and real orders/products were not modified.
- Live final mutation parity: PASS, bulk_resources and replace_across both match legacy for dry-run and real writes on temporary test objects.
- Development Runtime-CAS smoke on MODX 3: PASS for `get_site_state`, stale update rejection (`target_changed`), stale create rejection (`target_now_exists`), unchanged revision on rejected writes, and successful CAS mutation with unchanged target fingerprint. This is a focused smoke test, not the full parity/release matrix.
- Stable v1.1.0 full live regression: PASS, all 14 live parity suites completed with exit code 0 on the release Runtime.
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

Live-tested on a dedicated MODX 3.2.4-pl sandbox. After the MODX 2 compatibility fixes, the full transport release-smoke was repeated and all 14 live parity suites completed with exit code 0. The original 16 `modxmcp.*` settings were restored and compared after the destructive install/reinstall/uninstall cycle.

Status: LIVE-VALIDATED.

## MODX 2

Live-tested on a clean MODX 2.8.9-pl sandbox.

- transport release-smoke: PASS for build, fresh install, endpoint CRUD, same-package reinstall with settings preserved, clean uninstall, and final installation;
- stable v1.1.0 modular read parity: PASS for all 74 read-only actions in that release; absent element fixtures are skipped by the read-only matrix and covered separately by element lifecycle tests;
- processor mutation parity: PASS, 46 checks across 40 processor mutations;
- element lifecycle parity: PASS for all 7 supported element types;
- property sets: 5/5; resource/ops: 6/6 safe-live actions; system/TV/ops: 8/8; media: 9/9; package management: 5/5;
- final bulk/replace suite: 4 checks across 2/2 actions; element DB/static-file suite: all 4 supported file-backed element types;
- `empty_recycle_bin` and `regenerate_token` remain intentionally skipped live for the same safety reasons as on MODX 3;
- positive third-party-extra lifecycles are not claimed on this clean MODX 2 sandbox because MIGX, miniShop2, VersionX and VirtualPage are not installed there; their absence/error contracts are covered by the common parity matrix.

Status: LIVE-VALIDATED FOR CORE/RUNTIME AND RELEASE-SMOKE.
