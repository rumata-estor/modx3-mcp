# MODX MCP — capabilities and tool catalog

**English** | [Русский](CAPABILITIES.ru.md) · [Installation](INSTALL.md) · [API reference](API.md)

**MODX MCP 1.2.1 · 191 public tools · 19 functional groups · MODX Revolution 2.8.x / 3.x**

MODX MCP gives an AI-capable MCP client named operations for working with MODX data and elements, rather than requiring every task to be expressed as raw SQL or filesystem commands. It supports site inspection, content and code editing, TV values, packages, access administration, and selected third-party extras. The actual tool list depends on the installed build and enabled capabilities.

This guide answers **“What can I do with MODX MCP?”**. For the complete fields, types, enumerations, and integration details see the [API reference](API.md). To get connected, start with [Installation](INSTALL.md).

**Impact labels are explanations, not permission checks.** “Read” can still return confidential data such as configuration values. “High impact” warns that a command can break the site or compromise its security if misused.

## Tool index

| Group | Tools |
| --- | ---: |
| [Elements and resources](#elements-and-resources) | 14 |
| [Resource TVs](#resource-tvs) | 4 |
| [TV field types](#tv-field-types) | 2 |
| [System settings](#system-settings) | 5 |
| [Media sources and files](#media-sources-and-files) | 13 |
| [Components and files](#components-and-files) | 4 |
| [Code search and dependencies](#code-search-and-dependencies) | 5 |
| [VersionX](#versionx) | 3 |
| [VirtualPage](#virtualpage) | 17 |
| [miniShop2](#minishop2) | 22 |
| [MIGX](#migx) | 5 |
| [Users and access rights](#users-and-access-rights) | 47 |
| [ClientConfig](#clientconfig) | 5 |
| [Property sets](#property-sets) | 7 |
| [Contexts and context settings](#contexts-and-context-settings) | 10 |
| [Packages and providers](#packages-and-providers) | 7 |
| [Namespaces](#namespaces) | 4 |
| [Lexicon and translations](#lexicon-and-translations) | 4 |
| [Maintenance and diagnostics](#maintenance-and-diagnostics) | 13 |
| **Total** | **191** |

## Elements and resources

Manage pages, templates, chunks, snippets, plugins, TVs, and categories as MODX objects.

| Tool | What it does | Impact |
| --- | --- | --- |
| `modx_list_elements` | List MODX elements of a type (id + name). | Read |
| `modx_make_static` | Convert a chunk/snippet/template/plugin to a static file under core/elements/ (writes the file, sets static=1 + source=Filesystem). | High impact |
| `modx_get_element` | Read a MODX element (returns its fields + full code). | Read |
| `modx_update_element` | Update a MODX element by REPLACING its whole content (send the complete new content). | High impact |
| `modx_create_element` | Create a new MODX element. | High impact |
| `modx_delete_element` | Delete a MODX element. Run with dry_run:true FIRST — it returns what would be deleted plus where the element is still referenced (for resources: child-resource count), without deleting. Review that before the real delete. | High impact |
| `modx_view_element` | View a chunk/snippet/template/plugin as NUMBERED lines (like cat -n), optionally windowed by start_line/end_line. | Changes site |
| `modx_edit_element_lines` | Edit a chunk/snippet/template/plugin BY LINE, sending only the changed lines (no need to resend the whole element). | High impact |
| `modx_bulk_resources` | Apply ONE operation to many resources at once: publish, unpublish, set_template (needs template), move (needs parent_to and/or context_to), or delete. | High impact |
| `modx_duplicate_resource` | Duplicate a resource. id = source resource; optional name (new pagetitle), duplicate_children (bool), published_mode (preserve\|publish\|unpublish). | Changes site |
| `modx_duplicate_element` | Duplicate an element (chunk/snippet/template/plugin/tv). type + id; optional name for the copy. | Changes site |
| `modx_undelete_resource` | Restore a soft-deleted resource from the trash (un-deletes it). id = resource id. | Changes site |
| `modx_empty_recycle_bin` | Permanently purge ALL trashed (soft-deleted) resources. | High impact |
| `modx_reorder_resources` | Reorder resources in the tree by setting menuindex (and optionally parent). items = [{id, menuindex, parent?}], applied per resource. | Changes site |

## Resource TVs

Read or update custom resource fields, and inspect saved TV values.

| Tool | What it does | Impact |
| --- | --- | --- |
| `modx_get_resource_tvs` | Get a resource's template-variable (TV) values. | Read |
| `modx_update_resource_tvs` | Update a resource's template-variable (TV) values. | Changes site |
| `modx_list_tv_values` | List explicitly stored values for one TV across all resources, including stale values on resources whose current template may no longer use that TV. | Read |
| `modx_clear_tv_values` | Preview or clear every explicitly stored value for one TV. | Changes site |

## TV field types

Discover available TV field types and choose one based on a requirement.

| Tool | What it does | Impact |
| --- | --- | --- |
| `modx_suggest_tv_type` | Unsure which TV input type fits a need? Describe it (English or Russian) and get ranked candidate field_types with reasons + a ready-to-edit create_element skeleton for the top pick (with the extra keys that type needs). | Read |
| `modx_list_tv_input_types` | List the TV input (widget) types available on this site. | Read |

## System settings

Inspect and edit configuration that may affect the entire site.

| Tool | What it does | Impact |
| --- | --- | --- |
| `modx_list_system_settings` | List MODX system settings. | Read |
| `modx_get_system_setting` | Get one MODX system setting by key. | Read |
| `modx_create_system_setting` | Create a MODX system setting. | Changes site |
| `modx_update_system_setting` | Update a MODX system setting by key. | High impact |
| `modx_delete_system_setting` | Delete a MODX system setting by key. | High impact |

## Media sources and files

Navigate media sources and manage files and folders exposed through them.

| Tool | What it does | Impact |
| --- | --- | --- |
| `modx_list_media_sources` | List MODX media sources. | Read |
| `modx_get_media_source` | Get one MODX media source. | Read |
| `modx_create_media_source` | Create a media (file) source. class_key defaults to sources.modFileMediaSource. 'properties' is a {name:value} map of source params (e.g. {"basePath":"assets/files/","baseUrl":"assets/files/"}) merged into the source. | Changes site |
| `modx_update_media_source` | Update a media source. 'properties' is a {name:value} map merged into the source's params (others preserved) — use it to set basePath/baseUrl/etc. | Changes site |
| `modx_delete_media_source` | Delete a media source by id. | Changes site |
| `modx_list_media_source_files` | List files and folders inside a media source. | Read |
| `modx_read_media_source_file` | Read a file inside a media source. | Read |
| `modx_create_media_file` | Create a file inside a media source (writes content). source = source id or name, path = target directory (relative to the source base), name = filename, content = file body. | Changes site |
| `modx_update_media_file` | Overwrite a file's content inside a media source. source = id/name, path = file path within the source, content = new body. | Changes site |
| `modx_delete_media_file` | Delete a file inside a media source. source = id/name, path = file path within the source. | High impact |
| `modx_rename_media_file` | Rename a file inside a media source. source = id/name, path = current file path, new_name = new filename. | Changes site |
| `modx_create_media_folder` | Create a folder inside a media source. source = id/name, parent = parent directory (default root), name = folder name. | Changes site |
| `modx_delete_media_folder` | Delete a folder (and its contents) inside a media source. source = id/name, path = folder path within the source. | High impact |

## Components and files

Discover installed extras and inspect their permitted files.

| Tool | What it does | Impact |
| --- | --- | --- |
| `modx_list_installed_components` | List installed MODX components from core/components and assets/components. | Read |
| `modx_get_component_files` | List files and folders inside an installed MODX component. | Read |
| `modx_read_component_file` | Read a file from an installed MODX component. | Read |
| `modx_check_integrations` | Report which popular MODX add-ons (miniShop2, MIGX, pdoTools, Tickets, etc.) are installed on this site and what modxMCP can do with each. | Read |

## Code search and dependencies

Locate implementation details, references, and relationships between site elements.

| Tool | What it does | Impact |
| --- | --- | --- |
| `modx_search_code` | Full-text search across element and resource CONTENT (and names). | Read |
| `modx_find_usages` | Find where an element is used: content matches for its name across all code, plus — if it is a template — the resources assigned to that template. | Read |
| `modx_list_resources` | List resources (the content tree), optionally filtered by parent, context, deleted state, or a pagetitle/alias/uri query. | Read |
| `modx_replace_across` | Site-wide search & replace across code elements (chunk/snippet/template/plugin): find every element whose CONTENT contains find and replace ALL occurrences with replacement, in ONE call (no per-element reads/writes from your side). | High impact |
| `modx_dependency_graph` | Map how the site's elements WIRE TOGETHER, in one call: which template pulls which chunk/snippet/TV, which snippet renders which chunk, where each TV is attached. | Read |

## VersionX

Inspect or restore historical revisions when VersionX is installed.

| Tool | What it does | Impact |
| --- | --- | --- |
| `modx_versionx_list_versions` | List VersionX versions for a MODX resource, chunk, snippet, template, plugin, or TV. | Read |
| `modx_versionx_get_version` | Read one VersionX version payload before deciding whether to revert to it. | Changes site |
| `modx_versionx_revert_version` | Revert a MODX object to a specific VersionX version. | Changes site |

## VirtualPage

Manage event bindings, handlers, and routes provided by VirtualPage.

| Tool | What it does | Impact |
| --- | --- | --- |
| `modx_virtualpage_list_events` | List VirtualPage events that bind route groups to MODX events. | Changes site |
| `modx_virtualpage_get_event` | Read one VirtualPage event by id or name, including its routes. | Changes site |
| `modx_virtualpage_create_event` | Create a VirtualPage event, usually OnPageNotFound or OnHandleRequest. | Changes site |
| `modx_virtualpage_update_event` | Update a VirtualPage event by id or name. | Changes site |
| `modx_virtualpage_list_handlers` | List VirtualPage handlers that render resources, snippets, chunks, or dynamic resources. | Changes site |
| `modx_virtualpage_get_handler` | Read one VirtualPage handler by id or name, including routes that use it. | Changes site |
| `modx_virtualpage_create_handler` | Create a VirtualPage handler. type can be resource, snippet, chunk, dynamic_resource/template, or 0..3. | Changes site |
| `modx_virtualpage_update_handler` | Update a VirtualPage handler by id or name. | Changes site |
| `modx_virtualpage_list_routes` | List VirtualPage routes with their event and handler names. | Changes site |
| `modx_virtualpage_get_route` | Read one VirtualPage route by id, or by route plus optional method. | Changes site |
| `modx_virtualpage_create_route` | Create a VirtualPage route and bind its event plugin if needed. | Changes site |
| `modx_virtualpage_update_route` | Update a VirtualPage route by id. | Changes site |
| `modx_virtualpage_resolve_route` | Simulate VirtualPage route matching and return handler plus vp.* placeholders. | Read |
| `modx_virtualpage_delete_event` | Delete a VirtualPage event by id or name (removes its routes too, per the VP schema). | Changes site |
| `modx_virtualpage_delete_handler` | Delete a VirtualPage handler by id or name. | Changes site |
| `modx_virtualpage_delete_route` | Delete a VirtualPage route by id or name. | Changes site |
| `modx_virtualpage_clear_cache` | Clear VirtualPage route/cache files and refresh MODX cache. | Changes site |

## miniShop2

Work with shop options, categories, product relationships, and orders.

| Tool | What it does | Impact |
| --- | --- | --- |
| `modx_ms2_list_option_types` | List available miniShop2 product option field types. | Changes site |
| `modx_ms2_list_options` | List miniShop2 product options from the settings options tab. | Changes site |
| `modx_ms2_get_option` | Read one miniShop2 product option by id. | Changes site |
| `modx_ms2_create_option` | Create a miniShop2 product option in settings options. | Changes site |
| `modx_ms2_update_option` | Update a miniShop2 product option in settings options. | Changes site |
| `modx_ms2_assign_option_to_category` | Assign an existing miniShop2 option to an msCategory. | Changes site |
| `modx_ms2_get_product_options` | Read miniShop2 option values assigned to a product. | Changes site |
| `modx_ms2_update_product_options` | Create, update, or remove miniShop2 option values for a product. | Changes site |
| `modx_ms2_list_link_types` | List miniShop2 link types (msLink): id, type, name, description. | Changes site |
| `modx_ms2_get_link_type` | Get a miniShop2 link type by id. | Changes site |
| `modx_ms2_create_link_type` | Create a miniShop2 link type. 'type' is the relation kind that decides how products get linked. | Changes site |
| `modx_ms2_update_link_type` | Update a miniShop2 link type. | Changes site |
| `modx_ms2_delete_link_type` | Delete a miniShop2 link type by id. | Changes site |
| `modx_ms2_list_product_links` | List product-to-product links (msProductLink), optionally for one product. | Changes site |
| `modx_ms2_create_product_link` | Link two products under a link type. The link type's relation kind decides whether the reverse link is also created. | Changes site |
| `modx_ms2_delete_product_link` | Remove a product link (by link type + master + slave). | Changes site |
| `modx_ms2_list_categories` | List miniShop2 categories (msCategory). | Changes site |
| `modx_ms2_create_category` | Create a miniShop2 category (msCategory resource). | Changes site |
| `modx_ms2_update_category` | Update a miniShop2 category. | Changes site |
| `modx_ms2_list_orders` | List miniShop2 orders (msOrder), filterable by status/customer/context/date range. | Changes site |
| `modx_ms2_get_order` | Get a miniShop2 order by id (with items/customer). | Changes site |
| `modx_ms2_update_order` | Update a miniShop2 order — typically to change its status (triggers ms2 status-change logic), delivery or payment. | Changes site |

## MIGX

Read or change MIGX configurations when the extra is installed.

| Tool | What it does | Impact |
| --- | --- | --- |
| `modx_migx_list_configs` | List MIGX configurations (migxConfig): id, name, category, published. | Changes site |
| `modx_migx_get_config` | Get a full MIGX configuration by id (formtabs, columns, buttons, filters, permissions — as stored JSON strings). | Changes site |
| `modx_migx_create_config` | Create a MIGX configuration. formtabs/columns/contextmenus/actionbuttons/columnbuttons/filters are JSON strings (same format MIGX stores in the manager). | Changes site |
| `modx_migx_update_config` | Update a MIGX configuration. Only the fields you pass are changed. | Changes site |
| `modx_migx_delete_config` | Delete a MIGX configuration by id. | Changes site |

## Users and access rights

Administer users, groups, roles, policies, and resource/context permissions.

| Tool | What it does | Impact |
| --- | --- | --- |
| `modx_flush_permissions` | Flush cached access permissions (the manager's 'Flush Permissions') so ACL changes apply. | Changes site |
| `modx_list_users` | List manager/web users (modUser). | Read |
| `modx_get_user` | Get a user by id. | Read |
| `modx_create_user` | Create a user. Pass 'password' to set it explicitly; omit to auto-generate. Profile fields (email, fullname, phone…) are accepted alongside. | Changes site |
| `modx_update_user` | Update a user (profile fields, active state). | Changes site |
| `modx_delete_user` | Delete a user by id. | Changes site |
| `modx_list_user_groups` | List MODX user groups (id, name, parent, rank). | Read |
| `modx_get_user_group` | Get a single user group by id. | Read |
| `modx_create_user_group` | Create a user group. | Changes site |
| `modx_update_user_group` | Update a user group. | Changes site |
| `modx_delete_user_group` | Delete a user group by id. | Changes site |
| `modx_list_user_group_members` | List members of a user group. | Read |
| `modx_add_user_to_group` | Add a user to a user group, optionally with a role. | Changes site |
| `modx_update_group_member` | Update a user's membership in a group (e.g. change role). | Changes site |
| `modx_remove_user_from_group` | Remove a user from a user group. | Changes site |
| `modx_list_roles` | List access roles (id, name, authority). | Read |
| `modx_get_role` | Get a role by id. | Read |
| `modx_create_role` | Create a role. | Changes site |
| `modx_update_role` | Update a role. | Changes site |
| `modx_delete_role` | Delete a role by id. | Changes site |
| `modx_list_access_policies` | List access policies (id, name, description, template). | Read |
| `modx_get_access_policy` | Get an access policy by id. | Read |
| `modx_create_access_policy` | Create an access policy. | Changes site |
| `modx_update_access_policy` | Update an access policy (e.g. its permissions map). | High impact |
| `modx_delete_access_policy` | Delete an access policy by id. | Changes site |
| `modx_list_access_policy_templates` | List access policy templates. | Read |
| `modx_get_access_policy_template` | Get an access policy template by id. | Read |
| `modx_create_access_policy_template` | Create an access policy template. | Changes site |
| `modx_update_access_policy_template` | Update an access policy template. | Changes site |
| `modx_delete_access_policy_template` | Delete an access policy template by id. | Changes site |
| `modx_list_access_permissions` | List permissions defined by a policy template (read-only catalogue). | Read |
| `modx_list_resource_groups` | List resource groups (id, name). | Read |
| `modx_get_resource_group` | Get a resource group by id. | Read |
| `modx_list_resource_group_resources` | List resources assigned to a resource group. | Read |
| `modx_create_resource_group` | Create a resource group. | Changes site |
| `modx_update_resource_group` | Update a resource group. | Changes site |
| `modx_delete_resource_group` | Delete a resource group by id. | Changes site |
| `modx_assign_resource_to_group` | Add a resource (document) to a resource group. | Changes site |
| `modx_remove_resource_from_group` | Remove a resource (document) from a resource group. | Changes site |
| `modx_list_context_access` | List context-access ACL entries, optionally filtered by usergroup/context/policy. | Read |
| `modx_grant_context_access` | Grant a user group access to a context with a policy and minimum role authority. | Changes site |
| `modx_update_context_access` | Update a context-access ACL entry by id. | Changes site |
| `modx_revoke_context_access` | Remove a context-access ACL entry by id. | Changes site |
| `modx_list_resourcegroup_access` | List resource-group access ACL entries. | Read |
| `modx_grant_resourcegroup_access` | Grant a user group access to a resource group with a policy and minimum role authority. | Changes site |
| `modx_update_resourcegroup_access` | Update a resource-group access ACL entry by id. | Changes site |
| `modx_revoke_resourcegroup_access` | Remove a resource-group access ACL entry by id. | Changes site |

## ClientConfig

Manage settings supplied by the ClientConfig extra.

| Tool | What it does | Impact |
| --- | --- | --- |
| `modx_clientconfig_list_settings` | List ClientConfig settings including context-specific values. | Changes site |
| `modx_clientconfig_get_setting` | Get a ClientConfig setting by id or key. | Changes site |
| `modx_clientconfig_create_setting` | Create a ClientConfig setting. | Changes site |
| `modx_clientconfig_update_setting` | Update a ClientConfig setting by id or key. | Changes site |
| `modx_clientconfig_delete_setting` | Delete a ClientConfig setting by id or key. | Changes site |

## Property sets

Create and assign reusable MODX property sets.

| Tool | What it does | Impact |
| --- | --- | --- |
| `modx_list_property_sets` | List property sets (modPropertySet): id, name, description, category. | Read |
| `modx_get_property_set` | Get a property set by id (incl. its properties). | Read |
| `modx_create_property_set` | Create a property set. 'properties' is a JSON object of property definitions. | Changes site |
| `modx_update_property_set` | Update a property set. | Changes site |
| `modx_delete_property_set` | Delete a property set by id. | Changes site |
| `modx_assign_property_set` | Attach a property set to an element. Identify the element with its id + either element_class (e.g. 'modSnippet') or element_type ('snippet'/'chunk'/'template'/'plugin'/'tv'). | Changes site |
| `modx_unassign_property_set` | Detach a property set from an element (same identification as assign). | Changes site |

## Contexts and context settings

Manage context metadata and scoped configuration.

| Tool | What it does | Impact |
| --- | --- | --- |
| `modx_list_contexts` | List contexts (modContext): key, name, description, rank. | Read |
| `modx_get_context` | Get a context by key. | Read |
| `modx_create_context` | Create a context. 'key' is the unique identifier (e.g. 'mobile'). | Changes site |
| `modx_update_context` | Update a context (name/description/rank). 'settings' may be a JSON array of context settings (advanced). | Changes site |
| `modx_delete_context` | Delete a context by key. | High impact |
| `modx_list_context_settings` | List settings of a context (modContextSetting). | Read |
| `modx_get_context_setting` | Get one context setting (by context_key + key). | Read |
| `modx_create_context_setting` | Create a context-level setting (overrides the system setting for that context). | Changes site |
| `modx_update_context_setting` | Update a context setting (by context_key + key). | Changes site |
| `modx_delete_context_setting` | Delete a context setting (by context_key + key). | Changes site |

## Packages and providers

Search package providers and install or uninstall MODX extras.

| Tool | What it does | Impact |
| --- | --- | --- |
| `modx_install_package` | Install a transport package from a provider (default modx.com) by name, e.g. {package:'MIGX'}. | High impact |
| `modx_uninstall_package` | Uninstall a transport package by signature (e.g. 'migx-2.13.0-pl'). | High impact |
| `modx_list_providers` | List transport providers (modx.com and custom, e.g. modstore.pro): id, name, service_url. | Read |
| `modx_search_packages` | Search a provider's catalogue (use before install_package). | Read |
| `modx_create_provider` | Add a transport provider (e.g. modstore.pro). | Changes site |
| `modx_update_provider` | Update a transport provider. | Changes site |
| `modx_delete_provider` | Delete a transport provider by id. | Changes site |

## Namespaces

Register and manage addon namespaces and paths.

| Tool | What it does | Impact |
| --- | --- | --- |
| `modx_list_namespaces` | List namespaces (modNamespace): name, path, assets_path. | Read |
| `modx_create_namespace` | Create a namespace. | Changes site |
| `modx_update_namespace` | Update a namespace (by name). | Changes site |
| `modx_delete_namespace` | Delete a namespace by name ('core' cannot be deleted). | Changes site |

## Lexicon and translations

Read or override translated strings and lexicon topics.

| Tool | What it does | Impact |
| --- | --- | --- |
| `modx_list_lexicon_entries` | List lexicon entries, filtered by namespace + topic + language (and optional search). | Read |
| `modx_list_lexicon_topics` | List lexicon topics for a namespace/language. | Read |
| `modx_set_lexicon_entry` | Create or override a lexicon entry (DB override of the file value). | Changes site |
| `modx_revert_lexicon_entry` | Revert a lexicon entry — remove its DB override so the file value applies. | Changes site |

## Maintenance and diagnostics

Inspect site metadata, status, logs, and perform maintenance.

| Tool | What it does | Impact |
| --- | --- | --- |
| `modx_get_site_state` | Read the connector-authoritative site revision and atomic-precondition capability used by external tooling. | Read |
| `modx_describe_object` | Schema introspection: list an xPDO class's fields (name + php/db type, null, default) and its primary key, so you use REAL field names instead of guessing. | Read |
| `modx_read_error_log` | Read the tail of the MODX error log (core/cache/logs/error.log) for diagnostics. | Read |
| `modx_refresh_uris` | Regenerate all resource URIs (run after bulk alias/structure changes that left stale URIs). | Changes site |
| `modx_remove_locks` | Clear stale manager edit locks (when an element/resource is reported locked by a dead session). | Changes site |
| `modx_project_overview` | Orient on the whole installed site in ONE compact, cheap call. | Read |
| `modx_system_info` | Environment/diagnostic info: MODX version, modxMCP version, PHP version, db type, key paths. | Read |
| `modx_regenerate_token` | Rotate the modxmcp.api_token to a fresh random value and return it. | High impact |
| `modx_help` | Built-in documentation. Call with no args to list topics; pass {topic} for a guide (e.g. 'tv_input_types', 'study_component', 'minishop2', 'migx', 'acl', 'getting_started'). Read the relevant topic before unfamiliar work, or to learn how to use a newly-installed component. | Read |
| `modx_list_actions` | List the action names this modxMCP server build supports, grouped by area. | Read |
| `modx_clear_cache` | Refresh the MODX cache. Pass partitions (e.g. ['resource','context_settings']) to refresh only those; omit for a full refresh. | Changes site |
| `modx_read_audit_log` | Read the modxMCP write-audit trail (newest last). | Read |
| `modx_run_processor` | Run ANY MODX processor directly (escape hatch). | High impact |

## How to use the catalog

A compatible MCP host will discover available tools and pass validated arguments to the local MCP client. You do not need to memorize command names. For instance, discovering how a page is rendered may involve listing its resource, reading its assigned template, and inspecting the referenced chunk or snippet. These are standard public operations, not a prescribed agent workflow.

If an action is absent, check whether its capability group has been disabled, whether the required extra is installed, and whether the client/server versions match. `modx_list_actions` reports what the installed server knows about.

## Security reminder

The `X-MCP-Token` credential authorizes a high-privilege service user. Knowing the names of these tools alone does not grant access. However, an attacker who obtains a live token can potentially modify PHP elements and system settings. Do not rely on a “read” label in this document as a security boundary. See [Security](SECURITY.md).

For implementation details see the [public client definitions](https://github.com/rumata-estor/modx3-mcp/blob/main/client/index.js).
