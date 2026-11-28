// MODX MCP SOURCE / ИСХОДНИК MODX MCP
// RU: Единая локальная политика клиента для диспетчеризации и списка инструментов.
// EN: Shared local policy for dispatch and tool discovery.
"use strict";

const RULES = [
  { id: "element_create", flag: "MODX_MCP_ALLOW_SAFE_ELEMENT_CREATE", names: ["modx_create_element"], applies: args => ["chunk","snippet","template"].includes(String(args.type)), label: "chunk/snippet/template creation" },
  { id: "plugin_create", flag: "MODX_MCP_ALLOW_SAFE_PLUGIN_CREATE", names: ["modx_create_element"], applies: args => String(args.type) === "plugin", label: "plugin creation" },
  { id: "plugin_events", flag: "MODX_MCP_ALLOW_SAFE_PLUGIN_EVENTS", names: ["modx_update_element"], applies: args => String(args.type) === "plugin" && Object.prototype.hasOwnProperty.call(args, "events"), label: "plugin event changes" },
  { id: "tv_delete", flag: "MODX_MCP_ALLOW_STAGED_TV_DELETE", names: ["modx_delete_element"], applies: args => !args.dry_run && args.type === "tv", label: "TV deletion (not preview)" },
  { id: "element_delete", flag: "MODX_MCP_ALLOW_STAGED_ELEMENT_DELETE", names: ["modx_delete_element"], applies: args => !args.dry_run && ["chunk","snippet","template"].includes(String(args.type)), label: "chunk/snippet/template deletion (not preview)" },
  { id: "resource_delete", flag: "MODX_MCP_ALLOW_STAGED_RESOURCE_DELETE", names: ["modx_delete_element"], applies: args => !args.dry_run && String(args.type) === "resource", label: "resource deletion (not preview)" },
  { id: "plugin_delete", flag: "MODX_MCP_ALLOW_STAGED_PLUGIN_DELETE", names: ["modx_delete_element"], applies: args => !args.dry_run && String(args.type) === "plugin", label: "plugin deletion (not preview)" },
  { id: "bulk_delete", flag: "MODX_MCP_ALLOW_STAGED_BULK_RESOURCE_DELETE", names: ["modx_bulk_resources"], applies: args => args.dry_run !== true && String(args.operation) === "delete", label: "bulk deletion (not preview)" },
  { id: "bulk_change", flag: "MODX_MCP_ALLOW_SAFE_BULK_RESOURCES", names: ["modx_bulk_resources"], applies: args => args.dry_run !== true && String(args.operation) !== "delete", label: "other bulk changes (not preview)" },
  { id: "recycle_purge", flag: "MODX_MCP_ALLOW_RECYCLE_PURGE", names: ["modx_empty_recycle_bin"], applies: args => true, label: "recycle-bin purge" },
  { id: "package_change", flag: "MODX_MCP_ALLOW_SAFE_PACKAGE_CHANGE", names: ["modx_install_package", "modx_uninstall_package"], applies: args => true, label: "direct mutation" },
  { id: "system_setting", flag: "MODX_MCP_ALLOW_SAFE_SYSTEM_SETTING", names: ["modx_create_system_setting", "modx_update_system_setting", "modx_delete_system_setting"], applies: args => true, label: "direct mutation" },
  { id: "media_source", flag: "MODX_MCP_ALLOW_SAFE_MEDIA_SOURCE", names: ["modx_create_media_source", "modx_update_media_source", "modx_delete_media_source"], applies: args => true, label: "direct mutation" },
  { id: "contexts", flag: "MODX_MCP_ALLOW_SAFE_CONTEXTS", names: ["modx_create_context", "modx_update_context", "modx_delete_context", "modx_create_context_setting", "modx_update_context_setting", "modx_delete_context_setting"], applies: args => true, label: "direct mutation" },
  { id: "clientconfig", flag: "MODX_MCP_ALLOW_SAFE_CLIENTCONFIG", names: ["modx_clientconfig_create_setting", "modx_clientconfig_update_setting", "modx_clientconfig_delete_setting"], applies: args => true, label: "direct mutation" },
  { id: "property_set", flag: "MODX_MCP_ALLOW_SAFE_PROPERTY_SET", names: ["modx_create_property_set", "modx_update_property_set", "modx_delete_property_set", "modx_assign_property_set", "modx_unassign_property_set"], applies: args => true, label: "direct mutation" },
  { id: "migx", flag: "MODX_MCP_ALLOW_SAFE_MIGX", names: ["modx_migx_create_config", "modx_migx_update_config", "modx_migx_delete_config"], applies: args => true, label: "direct mutation" },
  { id: "namespace_lexicon", flag: "MODX_MCP_ALLOW_SAFE_NAMESPACE_LEXICON", names: ["modx_create_namespace", "modx_update_namespace", "modx_delete_namespace", "modx_set_lexicon_entry", "modx_revert_lexicon_entry"], applies: args => true, label: "direct mutation" },
  { id: "access", flag: "MODX_MCP_ALLOW_SAFE_ACCESS", names: ["modx_create_user", "modx_update_user", "modx_delete_user", "modx_create_user_group", "modx_update_user_group", "modx_delete_user_group", "modx_add_user_to_group", "modx_update_group_member", "modx_remove_user_from_group", "modx_create_role", "modx_update_role", "modx_delete_role", "modx_create_access_policy", "modx_update_access_policy", "modx_delete_access_policy", "modx_create_access_policy_template", "modx_update_access_policy_template", "modx_delete_access_policy_template", "modx_create_resource_group", "modx_update_resource_group", "modx_delete_resource_group", "modx_assign_resource_to_group", "modx_remove_resource_from_group", "modx_grant_context_access", "modx_update_context_access", "modx_revoke_context_access", "modx_grant_resourcegroup_access", "modx_update_resourcegroup_access", "modx_revoke_resourcegroup_access", "modx_flush_permissions"], applies: args => true, label: "direct mutation" },
  { id: "provider", flag: "MODX_MCP_ALLOW_PROVIDER_CHANGE", names: ["modx_create_provider", "modx_update_provider", "modx_delete_provider"], applies: args => true, label: "direct mutation" },
];

function activeRules(name, env) {
  // Preserve the existing externally managed backup/workflow mode. This is not
  // server authorization; CAS and all server guards still apply.
  if (env.MODX_MCP_SKIP_AUTO_BACKUP === "1") return [];
  return RULES.filter(r => r.names.includes(name) && env[r.flag] !== "1");
}

function deniedGate(name, args = {}, env = process.env) {
  const rule = activeRules(name, env).find(r => r.applies(args));
  return rule ? rule.id : null;
}

function describeTool(tool, env = process.env) {
  const blocked = activeRules(tool.name, env);
  if (!blocked.length) return tool;
  return {
    ...tool,
    description: tool.description + " Local client policy blocks direct writes for: "
      + blocked.map(r => r.label).join("; ")
      + ". Read/preview tools may still be used where available. Administrator approval is required; do not change safety settings yourself. Server authorization and CAS remain independently required."
  };
}

module.exports = { deniedGate, describeTool };
