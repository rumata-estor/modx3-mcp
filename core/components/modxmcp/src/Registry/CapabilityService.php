<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Публичные группы возможностей и их политика, без legacy-исполнения.
 * EN: Public capability groups and policy, with no legacy execution.
 */
namespace ModxMcp\Registry;
final class CapabilityService
{
    public static function groups() {
        return array(
            'elements' => array(
                'list_categories',
                'get_category',
                'create_category',
                'update_category',
                'delete_category',
                'assign_element_category',
                'list_elements',
                'get_element',
                'create_element',
                'update_element',
                'update_element_property',
                'delete_element',
                'make_static',
                'view_element',
                'edit_element_lines',
                'bulk_resources',
                'duplicate_element',
                'duplicate_resource',
                'undelete_resource',
                'empty_recycle_bin',
                'reorder_resources',
            ),
            'resource_tvs' => array(
                'get_resource_tvs',
                'update_resource_tvs',
                'list_tv_values',
                'clear_tv_values',
            ),
            'tv_inputs' => array(
                'list_tv_input_types',
                'suggest_tv_type',
            ),
            'system' => array(
                'list_system_settings',
                'get_system_setting',
                'create_system_setting',
                'update_system_setting',
                'delete_system_setting',
            ),
            'media' => array(
                'list_media_sources',
                'get_media_source',
                'list_media_source_files',
                'read_media_source_file',
                'create_media_source',
                'update_media_source',
                'delete_media_source',
                'create_media_file',
                'update_media_file',
                'delete_media_file',
                'rename_media_file',
                'create_media_folder',
                'delete_media_folder',
            ),
            'components' => array(
                'list_installed_components',
                'get_component_files',
                'read_component_file',
                'check_integrations',
            ),
            'code_search' => array(
                'search_code',
                'find_usages',
                'list_resources',
                'replace_across',
                'dependency_graph',
            ),
            'versionx' => array(
                'versionx_list_versions',
                'versionx_get_version',
                'versionx_revert_version',
            ),
            'virtualpage' => array(
                'virtualpage_list_events',
                'virtualpage_get_event',
                'virtualpage_create_event',
                'virtualpage_update_event',
                'virtualpage_list_handlers',
                'virtualpage_get_handler',
                'virtualpage_create_handler',
                'virtualpage_update_handler',
                'virtualpage_list_routes',
                'virtualpage_get_route',
                'virtualpage_create_route',
                'virtualpage_update_route',
                'virtualpage_delete_event',
                'virtualpage_delete_handler',
                'virtualpage_delete_route',
                'virtualpage_resolve_route',
                'virtualpage_clear_cache',
            ),
            'minishop2' => array(
                'ms2_list_option_types',
                'ms2_list_options',
                'ms2_get_option',
                'ms2_create_option',
                'ms2_update_option',
                'ms2_assign_option_to_category',
                'ms2_get_product_options',
                'ms2_update_product_options',
                'ms2_list_link_types',
                'ms2_get_link_type',
                'ms2_create_link_type',
                'ms2_update_link_type',
                'ms2_delete_link_type',
                'ms2_list_product_links',
                'ms2_create_product_link',
                'ms2_delete_product_link',
                'ms2_list_categories',
                'ms2_create_category',
                'ms2_update_category',
                'ms2_list_orders',
                'ms2_get_order',
                'ms2_update_order',
            ),
            'migx' => array(
                'migx_list_configs',
                'migx_get_config',
                'migx_create_config',
                'migx_update_config',
                'migx_delete_config',
            ),
            'access' => array(
                'list_users',
                'get_user',
                'create_user',
                'update_user',
                'delete_user',
                'list_user_groups',
                'get_user_group',
                'create_user_group',
                'update_user_group',
                'delete_user_group',
                'list_user_group_members',
                'add_user_to_group',
                'update_group_member',
                'remove_user_from_group',
                'list_roles',
                'get_role',
                'create_role',
                'update_role',
                'delete_role',
                'list_access_policies',
                'get_access_policy',
                'create_access_policy',
                'update_access_policy',
                'delete_access_policy',
                'list_access_policy_templates',
                'get_access_policy_template',
                'create_access_policy_template',
                'update_access_policy_template',
                'delete_access_policy_template',
                'list_access_permissions',
                'list_resource_groups',
                'get_resource_group',
                'list_resource_group_resources',
                'create_resource_group',
                'update_resource_group',
                'delete_resource_group',
                'assign_resource_to_group',
                'remove_resource_from_group',
                'list_context_access',
                'grant_context_access',
                'update_context_access',
                'revoke_context_access',
                'list_resourcegroup_access',
                'grant_resourcegroup_access',
                'update_resourcegroup_access',
                'revoke_resourcegroup_access',
                'flush_permissions',
            ),
            'clientconfig' => array(
                'clientconfig_list_settings',
                'clientconfig_get_setting',
                'clientconfig_create_setting',
                'clientconfig_update_setting',
                'clientconfig_delete_setting',
            ),
            'property_sets' => array(
                'list_property_sets',
                'get_property_set',
                'create_property_set',
                'update_property_set',
                'delete_property_set',
                'assign_property_set',
                'unassign_property_set',
            ),
            'contexts' => array(
                'list_contexts',
                'get_context',
                'create_context',
                'update_context',
                'delete_context',
                'list_context_settings',
                'get_context_setting',
                'create_context_setting',
                'update_context_setting',
                'delete_context_setting',
            ),
            'package_management' => array(
                'install_package',
                'uninstall_package',
                'list_providers',
                'search_packages',
                'create_provider',
                'update_provider',
                'delete_provider',
            ),
            'namespaces' => array(
                'list_namespaces',
                'create_namespace',
                'update_namespace',
                'delete_namespace',
            ),
            'lexicon' => array(
                'list_lexicon_entries',
                'list_lexicon_topics',
                'set_lexicon_entry',
                'revert_lexicon_entry',
            ),
            'ops' => array(
                'list_actions',
                'get_capabilities',
                'get_site_state',
                'help',
                'run_processor',
                'clear_cache',
                'read_audit_log',
                'regenerate_token',
                'describe_object',
                'read_error_log',
                'refresh_uris',
                'remove_locks',
                'system_info',
                'project_overview',
            ),
        );
    }

    public static function toggleableGroupKeys() {
        return array('versionx', 'virtualpage', 'minishop2', 'migx', 'clientconfig', 'access', 'property_sets', 'contexts', 'package_management', 'namespaces', 'lexicon');
    }

    public static function disabledGroups($modx) {
        $raw = (string) $modx->getOption('modxmcp.disabled_groups', null, '');
        $out = array();
        foreach (explode(',', $raw) as $g) { $g = trim($g); if ($g !== '') { $out[$g] = true; } }
        return $out;
    }

    private static function actionToGroup() {
        $groups = self::groups();
        $toggle = array_flip(self::toggleableGroupKeys());
        $map = array();
        foreach ($groups as $g => $actions) {
            if (!isset($toggle[$g])) { continue; }
            foreach ($actions as $a) { $map[$a] = $g; }
        }
        return $map;
    }

    public static function assertEnabled($modx, $action) {
        $map = self::actionToGroup();
        if (!isset($map[$action])) { return; }
        $disabled = self::disabledGroups($modx);
        if (isset($disabled[$map[$action]])) {
            throw new \ModxMCPClientException("Возможность '{$map[$action]}' выключена в modxMCP — включите её в админке: Дополнения → modxMCP. (Capability '{$map[$action]}' is disabled; enable it in Components > modxMCP.)");
        }
    }

    public static function state($modx, $ready) {
        return array(
            'site_revision' => max(
                0,
                (int)$modx->getOption(
                    'modxmcp.site_revision',
                    null,
                    0
                )
            ),
            'atomic_preconditions' => ($ready),
            'precondition_version' => ($ready ? 2 : 1),
            'cas_required' => true,
        );
    }

    public static function report($modx, $ready) {
        $siteState = self::state($modx, $ready);
        $groups = self::groups();
        $toggle = self::toggleableGroupKeys();
        $disabled = self::disabledGroups($modx);
        $disabledActions = array();
        foreach ($toggle as $g) {
            if (isset($disabled[$g]) && isset($groups[$g])) {
                foreach ($groups[$g] as $a) { $disabledActions[] = $a; }
            }
        }
        return array(
            'toggleable_groups' => $toggle,
            'disabled_groups'   => array_keys($disabled),
            'disabled_actions'  => $disabledActions,
            'features'          => array(
                'atomic_preconditions' => $siteState['atomic_preconditions'],
                'site_revision' => $siteState['atomic_preconditions'],
                'precondition_version' => $siteState['precondition_version'],
                'cas_required' => $siteState['cas_required'],
            ),
            'fingerprint'       => self::fingerprint($modx),
        );
    }

    public static function fingerprint($modx) {
        return (string) $modx->getOption('modxmcp.disabled_groups', null, '');
    }
}
