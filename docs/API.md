# MODX MCP — API reference

**Version 1.2.1 · 10 October 2026 · 191 public MCP tools**

**English** | [Русский](API.ru.md) · [Installation](INSTALL.md) · [Capabilities](CAPABILITIES.md) · [Security](SECURITY.md)

Technical reference for developers and MCP agents using **MODX Revolution 2.8.x or 3.x**. The 191 public tools belong to the standard Node.js MCP client and use the `modx_` prefix. The PHP server exposes a 192nd action, `get_capabilities`, to negotiate capabilities internally. An installed site may enable fewer tools due to its version, settings, or optional add-ons. No third-party orchestration system is required.

## Connecting an MCP client

Install the MODX transport package for your platform, then configure a compatible **stdio** MCP host using **Node.js 18+**:

```json
{
  "mcpServers": {
    "modx": {
      "command": "npx",
      "args": ["-y", "github:rumata-estor/modx3-mcp#v1.2.1"],
      "env": {
        "MODX_MCP_SITE_URL": "https://example.com/assets/components/modxmcp/api.php",
        "MODX_MCP_TOKEN": "YOUR_PRIVATE_SITE_TOKEN"
      }
    }
  }
}
```

See [Installation](INSTALL.md) for platform-specific installation. Do not commit or disclose a real token.

## MCP calls versus direct HTTP

Use the public MCP name in a compatible MCP host:

```json
{"name":"modx_get_element","arguments":{"type":"chunk","name":"Header"}}
```

When developing a custom HTTP client, use the server action **without** `modx_` and authenticate with `X-MCP-Token` over HTTPS:

```http
POST /assets/components/modxmcp/api.php HTTP/1.1
Host: example.com
Content-Type: application/json
X-MCP-Token: <private token>

{"action":"get_element","type":"chunk","data":{"name":"Header"}}
```

The PHP endpoint expects an `action`, optional `type`, and `data` envelope. It is **not** a raw MCP JSON-RPC endpoint: do not send `tools/call` messages to `api.php`.

## How to read this reference

The tables reproduce the **declared MCP input schema**, including required fields, types, enum values, and field descriptions. “Required” is a schema designation. Some actions have additional conditional requirements (for example, supply either `id` or `name`) or version-specific conditions enforced by PHP. Nested objects/arrays appear using `.` and `[]` paths.

**Security:** possession of the token can enable privileged MODX operations. Some opt-in checks and pre-change backups belong to the standard Node.js **client** and are not inherited by custom direct-HTTP callers. Read [Security](SECURITY.md) before exposing endpoint access.

## Tool catalog

## 1. Elements and resources (14)

### `modx_list_elements`

List MODX elements of a type (id + name). Supports an optional name filter and pagination.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `type` | string | yes | Allowed: chunk, snippet, template, resource, tv, category, plugin |
| `query` | string | no | Filter by name (for resources: pagetitle/longtitle/alias). |
| `limit` | number | no | Max results (default 100; 1–500; zero is treated as 1). |
| `start` | number | no | Offset for pagination. |

### `modx_make_static`

Convert a chunk/snippet/template/plugin to a static file under core/elements/ (writes the file, sets static=1 + source=Filesystem). One element via {type,id}, or a batch via {items:[{type,id}]}. Edit the resulting files directly afterwards. (See also the modxmcp.auto_static setting to do this automatically on every create/update.)

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `type` | string | no | Allowed: chunk, snippet, template, plugin |
| `id` | number | no | — |
| `items` | array of object | no | Batch: list of {type,id} to convert. |
| `items[].type` | string | no | Allowed: chunk, snippet, template, plugin |
| `items[].id` | number | no | — |

### `modx_get_element`

Read a MODX element (returns its fields + full code). For a large chunk/snippet/template/plugin you only need to edit, prefer modx_view_element (numbered, windowable) so you don't pull the whole body into context.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `type` | string | yes | Allowed: chunk, snippet, template, resource, tv, category, plugin |
| `name` | string | no | — |
| `id` | number | no | — |

### `modx_update_element`

Update a MODX element by REPLACING its whole content (send the complete new `content`). Best for small elements or full rewrites. For a few changes inside a large chunk/snippet/template/plugin, do NOT use this — use modx_view_element + modx_edit_element_lines instead (sends only the changed lines, far fewer tokens, atomic with a safety anchor).

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `type` | string | yes | Allowed: chunk, snippet, template, resource, tv, category, plugin |
| `name` | string | no | — |
| `id` | number | no | — |
| `content` | string | no | — |
| `parent` | number | no | — |
| `template` | number | no | — |
| `published` | number | no | — |
| `alias` | string | no | — |
| `class_key` | string | no | — |
| `context_key` | string | no | — |
| `isfolder` | number | no | — |
| `hidemenu` | number | no | — |
| `introtext` | string | no | — |
| `menutitle` | string | no | — |
| `article` | string | no | — |
| `price` | number | no | — |
| `old_price` | number | no | — |
| `weight` | number | no | — |
| `remains` | number | no | — |
| `vendor` | number | no | — |
| `made_in` | string | no | — |
| `new` | number | no | — |
| `popular` | number | no | — |
| `favorite` | number | no | — |
| `tags` | string | no | — |
| `color` | string | no | — |
| `size` | string | no | — |
| `events` | array of string | no | — |
| `caption` | string | no | — |
| `field_type` | string | no | — |
| `templates` | array of number | no | — |
| `_runtime_preconditions` | object | no | Internal external infrastructure CAS preconditions. Not intended for manual model-authored calls. |
| `media_source` | number | no | — |
| `input_properties` | object | no | — |
| `category` | number | no | — |

### `modx_create_element`

Create a new MODX element.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `type` | string | yes | Allowed: chunk, snippet, template, resource, tv, category, plugin |
| `name` | string | yes | — |
| `content` | string | no | — |
| `parent` | number | no | — |
| `template` | number | no | — |
| `published` | number | no | — |
| `alias` | string | no | — |
| `class_key` | string | no | — |
| `context_key` | string | no | — |
| `isfolder` | number | no | — |
| `hidemenu` | number | no | — |
| `introtext` | string | no | — |
| `menutitle` | string | no | — |
| `article` | string | no | — |
| `price` | number | no | — |
| `old_price` | number | no | — |
| `weight` | number | no | — |
| `remains` | number | no | — |
| `vendor` | number | no | — |
| `made_in` | string | no | — |
| `new` | number | no | — |
| `popular` | number | no | — |
| `favorite` | number | no | — |
| `tags` | string | no | — |
| `color` | string | no | — |
| `size` | string | no | — |
| `events` | array of string | no | — |
| `caption` | string | no | — |
| `field_type` | string | no | — |
| `templates` | array of number | no | — |
| `_runtime_preconditions` | object | no | Internal external infrastructure CAS preconditions. Not intended for manual model-authored calls. |
| `media_source` | number | no | — |
| `input_properties` | object | no | — |
| `category` | number | no | — |

### `modx_delete_element`

Delete a MODX element. Run with dry_run:true FIRST — it returns what would be deleted plus where the element is still referenced (for resources: child-resource count), without deleting. Review that before the real delete.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `type` | string | yes | Allowed: chunk, snippet, template, resource, tv, category, plugin |
| `name` | string | no | — |
| `id` | number | no | — |
| `dry_run` | boolean | no | Preview what would be deleted + its usages, without deleting. |

### `modx_view_element`

View a chunk/snippet/template/plugin as NUMBERED lines (like `cat -n`), optionally windowed by start_line/end_line. Use this to find the exact line numbers to edit with modx_edit_element_lines — cheaper than pulling the whole element. Returns total_lines and the numbered window.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `type` | string | yes | Allowed: chunk, snippet, template, plugin |
| `id` | number | no | — |
| `name` | string | no | — |
| `start_line` | number | no | 1-based first line of the window (default 1). |
| `end_line` | number | no | 1-based last line of the window (default = end of file). |

### `modx_edit_element_lines`

Edit a chunk/snippet/template/plugin BY LINE, sending only the changed lines (no need to resend the whole element). Each edit replaces the inclusive range [start_line..end_line] with `replacement`. Conventions: delete = replacement ""; insert before a line = set end_line to start_line-1. Strongly recommended: pass `expect` (the current text of those lines) as a safety anchor — it is verified, and relocated to its unique match if the line numbers drifted; any mismatch aborts the WHOLE call (atomic, nothing is written). MULTI-EDIT RULES: all line numbers refer to the file exactly as you last saw it in modx_view_element (the original) — do NOT adjust them for the effect of your other edits; the server applies edits together (bottom-up) so earlier inserts/deletes never shift later ones. Ranges must not overlap. So to change several spots in one big file, send them all in ONE call with original line numbers. Read first with modx_view_element to get line numbers.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `type` | string | yes | Allowed: chunk, snippet, template, plugin |
| `id` | number | no | — |
| `name` | string | no | — |
| `edits` | array of object | yes | List of line edits applied atomically. |
| `edits[].start_line` | number | yes | 1-based first line of the range. |
| `edits[].end_line` | number | no | 1-based last line (inclusive). Omit = same as start_line. For insert, set to start_line-1. |
| `edits[].replacement` | string | no | New text for the range (multi-line ok). "" deletes the lines. |
| `edits[].expect` | string | no | Current text of the targeted lines — safety anchor; mismatch aborts the whole call. |

### `modx_bulk_resources`

Apply ONE operation to many resources at once: publish, unpublish, set_template (needs `template`), move (needs `parent_to` and/or `context_to`), or delete. Select targets by explicit `ids` OR by a parent/context/query filter. ALWAYS run dry_run:true FIRST — it reports the change per resource (and child-resource counts for delete) without applying. Changes go through the core resource processors (correct URI/events). Note: delete is a soft delete (moves resources to the MODX trash, recoverable), matching the manager.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `operation` | string | yes | Allowed: publish, unpublish, set_template, move, delete |
| `ids` | array of number | no | Explicit resource ids (preferred). |
| `parent` | number | no | Filter: select children of this parent id. |
| `context` | string | no | Filter: context key. |
| `query` | string | no | Filter: pagetitle/alias/uri substring. |
| `template` | integer | no | For set_template: the new template id. Use 0 to assign no template (MODX empty template). |
| `parent_to` | number | no | For move: the new parent id. |
| `context_to` | string | no | For move: the new context key. |
| `dry_run` | boolean | no | Preview the change per resource without applying. Do this first. |
| `limit` | number | no | Max resources to touch (default 200). |

### `modx_duplicate_resource`

Duplicate a resource. `id` = source resource; optional `name` (new pagetitle), `duplicate_children` (bool), `published_mode` (preserve|publish|unpublish).

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `id` | number | yes | — |
| `name` | string | no | — |
| `duplicate_children` | boolean | no | — |
| `published_mode` | string | no | Allowed: preserve, publish, unpublish |

### `modx_duplicate_element`

Duplicate an element (chunk/snippet/template/plugin/tv). `type` + `id`; optional `name` for the copy.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `type` | string | yes | Allowed: chunk, snippet, template, plugin, tv |
| `id` | number | yes | — |
| `name` | string | no | — |

### `modx_undelete_resource`

Restore a soft-deleted resource from the trash (un-deletes it). `id` = resource id.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `id` | number | yes | — |

### `modx_empty_recycle_bin`

Permanently purge ALL trashed (soft-deleted) resources. Irreversible — there is no further undo.

**Parameters:** none.

### `modx_reorder_resources`

Reorder resources in the tree by setting menuindex (and optionally parent). `items` = [{id, menuindex, parent?}], applied per resource.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `items` | array of object | yes | — |
| `items[].id` | number | yes | — |
| `items[].menuindex` | number | yes | — |
| `items[].parent` | number | no | — |

## 2. Resource TVs and values (4)

### `modx_get_resource_tvs`

Get a resource's template-variable (TV) values.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `resource_id` | number | yes | — |

### `modx_update_resource_tvs`

Update a resource's template-variable (TV) values.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `resource_id` | number | yes | — |
| `tvs` | object | yes | — |

### `modx_list_tv_values`

List explicitly stored values for one TV across all resources, including stale values on resources whose current template may no longer use that TV. Use before deleting or restructuring a TV.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `tv_id` | number | no | — |
| `tv_name` | string | no | — |
| `start` | number | no | — |
| `limit` | number | no | Page size, max 500. |

### `modx_clear_tv_values`

Preview or clear every explicitly stored value for one TV. Safe by default: without confirm:true it only returns the number of stored values. Destructive; use only as part of the staged TV deletion workflow after backup.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `tv_id` | number | no | — |
| `tv_name` | string | no | — |
| `confirm` | boolean | no | Must be true to actually remove stored values. |

## 3. TV input types (2)

### `modx_suggest_tv_type`

Unsure which TV input type fits a need? Describe it (English or Russian) and get ranked candidate `field_type`s with reasons + a ready-to-edit create_element skeleton for the top pick (with the extra keys that type needs). Deterministic helper for picking the right TV type; then create it with modx_create_element. See also the tv_input_types help topic.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `description` | string | yes | What the field should hold, e.g. 'a hero background image', 'repeating gallery rows', 'выбор статуса из списка'. |

### `modx_list_tv_input_types`

List the TV input (widget) types available on this site. Core types come with `use` (when to pick each) and `requires` (extra create_element keys like elements/media_source) so you choose the right `field_type` for the task; plus any custom types other components register (e.g. MIGX adds 'migx'/'migxdb'). Use a key as `field_type` when creating a TV. See the `tv_input_types` help topic for full examples.

**Parameters:** none.

## 4. System settings (5)

### `modx_list_system_settings`

List MODX system settings.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `namespace` | string | no | — |
| `area` | string | no | — |

### `modx_get_system_setting`

Get one MODX system setting by key.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `key` | string | yes | — |

### `modx_create_system_setting`

Create a MODX system setting.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `key` | string | yes | — |
| `value` | string | no | — |
| `xtype` | string | no | — |
| `namespace` | string | no | — |
| `area` | string | no | — |

### `modx_update_system_setting`

Update a MODX system setting by key.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `key` | string | yes | — |
| `value` | string | no | — |
| `xtype` | string | no | — |
| `namespace` | string | no | — |
| `area` | string | no | — |

### `modx_delete_system_setting`

Delete a MODX system setting by key.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `key` | string | yes | — |

## 5. Media sources, files and folders (13)

### `modx_list_media_sources`

List MODX media sources.

**Parameters:** none.

### `modx_get_media_source`

Get one MODX media source.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `id` | number | no | — |
| `name` | string | no | — |

### `modx_create_media_source`

Create a media (file) source. class_key defaults to sources.modFileMediaSource. 'properties' is a {name:value} map of source params (e.g. {"basePath":"assets/files/","baseUrl":"assets/files/"}) merged into the source.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `name` | string | yes | — |
| `description` | string | no | — |
| `class_key` | string | no | — |
| `properties` | object | no | {paramName: value} map, e.g. basePath/baseUrl. |

### `modx_update_media_source`

Update a media source. 'properties' is a {name:value} map merged into the source's params (others preserved) — use it to set basePath/baseUrl/etc.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `id` | number | yes | — |
| `name` | string | no | — |
| `description` | string | no | — |
| `properties` | object | no | {paramName: value} map merged into the source. |

### `modx_delete_media_source`

Delete a media source by id.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `id` | number | yes | — |

### `modx_list_media_source_files`

List files and folders inside a media source.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `id` | number | no | — |
| `name` | string | no | — |
| `path` | string | no | — |

### `modx_read_media_source_file`

Read a file inside a media source.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `id` | number | no | — |
| `name` | string | no | — |
| `path` | string | yes | — |

### `modx_create_media_file`

Create a file inside a media source (writes content). `source` = source id or name, `path` = target directory (relative to the source base), `name` = filename, `content` = file body.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `source` | string | yes | Media source id or name. |
| `path` | string | no | Directory within the source (default root). |
| `name` | string | yes | — |
| `content` | string | no | — |

### `modx_update_media_file`

Overwrite a file's content inside a media source. `source` = id/name, `path` = file path within the source, `content` = new body.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `source` | string | yes | — |
| `path` | string | yes | — |
| `content` | string | yes | — |

### `modx_delete_media_file`

Delete a file inside a media source. `source` = id/name, `path` = file path within the source.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `source` | string | yes | — |
| `path` | string | yes | — |

### `modx_rename_media_file`

Rename a file inside a media source. `source` = id/name, `path` = current file path, `new_name` = new filename.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `source` | string | yes | — |
| `path` | string | yes | — |
| `new_name` | string | yes | — |

### `modx_create_media_folder`

Create a folder inside a media source. `source` = id/name, `parent` = parent directory (default root), `name` = folder name.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `source` | string | yes | — |
| `parent` | string | no | — |
| `name` | string | yes | — |

### `modx_delete_media_folder`

Delete a folder (and its contents) inside a media source. `source` = id/name, `path` = folder path within the source.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `source` | string | yes | — |
| `path` | string | yes | — |

## 6. Installed components (4)

### `modx_list_installed_components`

List installed MODX components from core/components and assets/components.

**Parameters:** none.

### `modx_get_component_files`

List files and folders inside an installed MODX component.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `name` | string | yes | — |
| `scope` | string | no | Allowed: core, assets, all |
| `path` | string | no | — |

### `modx_read_component_file`

Read a file from an installed MODX component.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `name` | string | yes | — |
| `scope` | string | no | Allowed: core, assets, all |
| `path` | string | yes | — |

### `modx_check_integrations`

Report which popular MODX add-ons (miniShop2, MIGX, pdoTools, Tickets, etc.) are installed on this site and what modxMCP can do with each. Read-only.

**Parameters:** none.

## 7. Code search and dependencies (5)

### `modx_search_code`

Full-text search across element and resource CONTENT (and names). Finds the chunk/snippet/template/plugin/TV/resource that contains a string, including where an element is referenced or mentioned. Matches static elements by reading their static file. Use this to navigate the codebase (e.g. query 'header' finds the header chunk and everything that uses or mentions it). Each content hit returns `id`, `line` (1-based line of the match) and `line_text` (the exact line, verbatim) — so for a one-line change you can go straight to modx_edit_element_lines using `line` as start_line/end_line and `line_text` as `expect`, with no separate read. For multi-line context, open a window with modx_view_element around `line`.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `query` | string | yes | Substring to search for. |
| `types` | array of string | no | Element/resource types to search. Default: all. |
| `limit` | number | no | Max results (default 50, max 200). |
| `case_sensitive` | boolean | no | Case-sensitive match (default false). |

### `modx_find_usages`

Find where an element is used: content matches for its name across all code, plus — if it is a template — the resources assigned to that template. Use before renaming/deleting an element.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `name` | string | yes | Element name (chunk/snippet/template/TV name). |
| `limit` | number | no | Max results (default 100). |

### `modx_list_resources`

List resources (the content tree), optionally filtered by parent, context, deleted state, or a pagetitle/alias/uri query. Returns id, pagetitle, alias, uri, parent, template, published, deleted, isfolder, class_key, context_key.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `parent` | number | no | Parent resource id (e.g. 0 for top level). |
| `context` | string | no | Context key (e.g. 'web'). |
| `query` | string | no | Filter by pagetitle/alias/uri substring. |
| `deleted` | boolean | no | When set, filter resources by deleted state. |
| `limit` | number | no | Max results (default 100, max 500). |
| `start` | number | no | Offset for pagination. |

### `modx_replace_across`

Site-wide search & replace across code elements (chunk/snippet/template/plugin): find every element whose CONTENT contains `find` and replace ALL occurrences with `replacement`, in ONE call (no per-element reads/writes from your side). Honours static files vs DB. SUBSTRING match — `find:"foo"` also matches inside `footer`/`food`; make `find` specific. ALWAYS run dry_run:true FIRST and review the returned `preview` (the exact lines that would change) before the real run. case_sensitive defaults to TRUE here. Use for renames / URL / class changes across the codebase. (Does not touch resources — edit those individually.)

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `find` | string | yes | Exact substring to find in element content. |
| `replacement` | string | yes | Replacement ("" removes the substring). |
| `types` | array of string | no | Element types to scan. Default: all four. |
| `case_sensitive` | boolean | no | Case-sensitive match (default false). |
| `dry_run` | boolean | no | Preview only — report matches without writing (default false). Do this first. |
| `limit` | number | no | Max elements to touch (default 200). |

### `modx_dependency_graph`

Map how the site's elements WIRE TOGETHER, in one call: which template pulls which chunk/snippet/TV, which snippet renders which chunk, where each TV is attached. Returns `nodes` (elements, with in/out reference degree) + `edges` ([from_index, to_index, kind]) — plus `missing` (tags pointing at a chunk/snippet that does NOT exist — broken references) and `orphans` (elements nothing references — safe-to-delete candidates). Detects references in MODX tags, `&tpl=`chunk`` properties, `$modx->getChunk()/runSnippet()` in PHP, MIGX `inputTV`/`renderchunktpl`, and template↔TV attachments. Token-safe: it scales with the number of ELEMENTS, never with content (resources are counts, not nodes). USE IT: to understand an unfamiliar site's code structure after modx_project_overview; with `focus` before editing or deleting an element, to see exactly what depends on it (cheaper and more precise than modx_find_usages, which is a text search); with format:"summary" as a fast health check for broken/dead elements.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `format` | string | no | 'summary' returns only stats + missing + orphans (cheapest health check). Default 'graph'.; Allowed: graph, summary |
| `focus` | string | no | Limit to the neighbourhood of ONE element: its name, or 'type:name' to disambiguate (e.g. 'chunk:header'). Strongly preferred on big sites. |
| `depth` | number | no | With focus: how many hops to expand (1-5, default 1). |
| `direction` | string | no | With focus: 'out' = what it uses, 'in' = what uses it, 'both' (default).; Allowed: out, in, both |
| `types` | array of string | no | Keep only these node types. |
| `include_resources` | boolean | no | Also add resources that elements link to via [[~id]] (default false). |
| `verify_orphans` | boolean | no | Cross-check orphan candidates against resource content, so chunks used only inside pages are not falsely listed. Defaults to on for sites under 5000 resources. |
| `max_nodes` | number | no | Cap on returned nodes (default 1500). |

## 8. VersionX (3)

### `modx_versionx_list_versions`

List VersionX versions for a MODX resource, chunk, snippet, template, plugin, or TV.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `type` | string | yes | Allowed: resource, chunk, snippet, template, plugin, tv |
| `content_id` | number | yes | — |
| `limit` | number | no | — |

### `modx_versionx_get_version`

Read one VersionX version payload before deciding whether to revert to it.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `type` | string | yes | Allowed: resource, chunk, snippet, template, plugin, tv |
| `content_id` | number | yes | — |
| `version_id` | number | yes | — |

### `modx_versionx_revert_version`

Revert a MODX object to a specific VersionX version. This changes live MODX data and requires confirm=true.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `type` | string | yes | Allowed: resource, chunk, snippet, template, plugin, tv |
| `content_id` | number | yes | — |
| `version_id` | number | yes | — |
| `confirm` | boolean | yes | — |

## 9. VirtualPage (17)

### `modx_virtualpage_list_events`

List VirtualPage events that bind route groups to MODX events.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `id` | number | no | — |
| `name` | string | no | — |
| `active` | number | no | — |
| `include_routes` | boolean | no | — |
| `limit` | number | no | — |
| `start` | number | no | — |

### `modx_virtualpage_get_event`

Read one VirtualPage event by id or name, including its routes.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `id` | number | no | — |
| `name` | string | no | — |

### `modx_virtualpage_create_event`

Create a VirtualPage event, usually OnPageNotFound or OnHandleRequest.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `name` | string | yes | — |
| `description` | string | no | — |
| `rank` | number | no | — |
| `active` | number | no | — |

### `modx_virtualpage_update_event`

Update a VirtualPage event by id or name.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `id` | number | no | — |
| `name` | string | no | — |
| `description` | string | no | — |
| `rank` | number | no | — |
| `active` | number | no | — |

### `modx_virtualpage_list_handlers`

List VirtualPage handlers that render resources, snippets, chunks, or dynamic resources.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `id` | number | no | — |
| `name` | string | no | — |
| `type` | number | no | — |
| `entry` | number | no | — |
| `active` | number | no | — |
| `include_routes` | boolean | no | — |
| `limit` | number | no | — |
| `start` | number | no | — |

### `modx_virtualpage_get_handler`

Read one VirtualPage handler by id or name, including routes that use it.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `id` | number | no | — |
| `name` | string | no | — |

### `modx_virtualpage_create_handler`

Create a VirtualPage handler. type can be resource, snippet, chunk, dynamic_resource/template, or 0..3.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `name` | string | yes | — |
| `type` | string / number | no | — |
| `entry` | number | no | — |
| `content` | string | no | — |
| `description` | string | no | — |
| `cache` | number | no | — |
| `rank` | number | no | — |
| `active` | number | no | — |

### `modx_virtualpage_update_handler`

Update a VirtualPage handler by id or name.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `id` | number | no | — |
| `name` | string | no | — |
| `type` | string / number | no | — |
| `entry` | number | no | — |
| `content` | string | no | — |
| `description` | string | no | — |
| `cache` | number | no | — |
| `rank` | number | no | — |
| `active` | number | no | — |

### `modx_virtualpage_list_routes`

List VirtualPage routes with their event and handler names.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `id` | number | no | — |
| `route` | string | no | — |
| `method` | string | no | — |
| `event` | number | no | — |
| `event_name` | string | no | — |
| `handler` | number | no | — |
| `handler_name` | string | no | — |
| `active` | number | no | — |
| `limit` | number | no | — |
| `start` | number | no | — |

### `modx_virtualpage_get_route`

Read one VirtualPage route by id, or by route plus optional method.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `id` | number | no | — |
| `route` | string | no | — |
| `method` | string | no | — |

### `modx_virtualpage_create_route`

Create a VirtualPage route and bind its event plugin if needed.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `route` | string | yes | — |
| `method` | string | yes | — |
| `handler` | number | no | — |
| `handler_name` | string | no | — |
| `event` | number | no | — |
| `event_name` | string | no | — |
| `description` | string | no | — |
| `properties` | object | no | — |
| `rank` | number | no | — |
| `active` | number | no | — |

### `modx_virtualpage_update_route`

Update a VirtualPage route by id.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `id` | number | yes | — |
| `route` | string | no | — |
| `method` | string | no | — |
| `handler` | number | no | — |
| `handler_name` | string | no | — |
| `event` | number | no | — |
| `event_name` | string | no | — |
| `description` | string | no | — |
| `properties` | object | no | — |
| `rank` | number | no | — |
| `active` | number | no | — |

### `modx_virtualpage_resolve_route`

Simulate VirtualPage route matching and return handler plus vp.* placeholders.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `path` | string | no | — |
| `uri` | string | no | — |
| `method` | string | no | — |

### `modx_virtualpage_delete_event`

Delete a VirtualPage event by id or name (removes its routes too, per the VP schema).

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `id` | number | no | — |
| `name` | string | no | — |

### `modx_virtualpage_delete_handler`

Delete a VirtualPage handler by id or name.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `id` | number | no | — |
| `name` | string | no | — |

### `modx_virtualpage_delete_route`

Delete a VirtualPage route by id or name.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `id` | number | no | — |
| `name` | string | no | — |

### `modx_virtualpage_clear_cache`

Clear VirtualPage route/cache files and refresh MODX cache.

**Parameters:** none.

## 10. miniShop2 (22)

### `modx_ms2_list_option_types`

List available miniShop2 product option field types.

**Parameters:** none.

### `modx_ms2_list_options`

List miniShop2 product options from the settings options tab.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `query` | string | no | — |
| `category` | number | no | — |
| `modcategory` | number | no | — |
| `limit` | number | no | — |

### `modx_ms2_get_option`

Read one miniShop2 product option by id.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `id` | number | yes | — |

### `modx_ms2_create_option`

Create a miniShop2 product option in settings options.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `key` | string | yes | — |
| `caption` | string | yes | — |
| `description` | string | no | — |
| `measure_unit` | string | no | — |
| `category` | number | no | — |
| `type` | string | yes | — |
| `properties` | object | no | — |
| `category_ids` | array of number | no | — |

### `modx_ms2_update_option`

Update a miniShop2 product option in settings options.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `id` | number | yes | — |
| `key` | string | no | — |
| `caption` | string | no | — |
| `description` | string | no | — |
| `measure_unit` | string | no | — |
| `category` | number | no | — |
| `type` | string | no | — |
| `properties` | object | no | — |
| `category_ids` | array of number | no | — |

### `modx_ms2_assign_option_to_category`

Assign an existing miniShop2 option to an msCategory.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `option_id` | number | yes | — |
| `category_id` | number | yes | — |

### `modx_ms2_get_product_options`

Read miniShop2 option values assigned to a product.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `product_id` | number | yes | — |

### `modx_ms2_update_product_options`

Create, update, or remove miniShop2 option values for a product. Use null value to remove an option value.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `product_id` | number | yes | — |
| `options` | object | yes | — |

### `modx_ms2_list_link_types`

List miniShop2 link types (msLink): id, type, name, description.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `query` | string | no | — |
| `limit` | number | no | — |
| `start` | number | no | — |

### `modx_ms2_get_link_type`

Get a miniShop2 link type by id.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `id` | number | yes | — |

### `modx_ms2_create_link_type`

Create a miniShop2 link type. 'type' is the relation kind that decides how products get linked.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `name` | string | yes | Display name (must be unique). |
| `type` | string | yes | Relation kind.; Allowed: many_to_many, one_to_many, many_to_one, one_to_one |
| `description` | string | no | — |

### `modx_ms2_update_link_type`

Update a miniShop2 link type.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `id` | number | yes | — |
| `name` | string | no | — |
| `type` | string | no | Allowed: many_to_many, one_to_many, many_to_one, one_to_one |
| `description` | string | no | — |

### `modx_ms2_delete_link_type`

Delete a miniShop2 link type by id.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `id` | number | yes | — |

### `modx_ms2_list_product_links`

List product-to-product links (msProductLink), optionally for one product. Returns link type + master/slave with pagetitles.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `master` | number | no | Filter to links involving this product id. |
| `query` | string | no | — |
| `limit` | number | no | — |
| `start` | number | no | — |

### `modx_ms2_create_product_link`

Link two products under a link type. The link type's relation kind decides whether the reverse link is also created.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `link` | number | yes | Link type id (msLink). |
| `master` | number | yes | Master product id. |
| `slave` | number | yes | Slave product id. |

### `modx_ms2_delete_product_link`

Remove a product link (by link type + master + slave).

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `link` | number | yes | Link type id. |
| `master` | number | yes | Master product id. |
| `slave` | number | yes | Slave product id. |

### `modx_ms2_list_categories`

List miniShop2 categories (msCategory).

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `parent` | number | no | Parent category id. |
| `query` | string | no | — |
| `limit` | number | no | — |
| `start` | number | no | — |

### `modx_ms2_create_category`

Create a miniShop2 category (msCategory resource).

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `pagetitle` | string | yes | — |
| `parent` | number | yes | Parent resource/category id. |
| `published` | number | no | — |
| `alias` | string | no | — |

### `modx_ms2_update_category`

Update a miniShop2 category.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `id` | number | yes | — |
| `pagetitle` | string | no | — |
| `parent` | number | no | — |
| `published` | number | no | — |
| `alias` | string | no | — |

### `modx_ms2_list_orders`

List miniShop2 orders (msOrder), filterable by status/customer/context/date range.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `status` | number | no | Order status id. |
| `customer` | number | no | — |
| `context` | string | no | — |
| `query` | string | no | — |
| `date_start` | string | no | — |
| `date_end` | string | no | — |
| `limit` | number | no | — |
| `start` | number | no | — |

### `modx_ms2_get_order`

Get a miniShop2 order by id (with items/customer).

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `id` | number | yes | — |

### `modx_ms2_update_order`

Update a miniShop2 order — typically to change its status (triggers ms2 status-change logic), delivery or payment.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `id` | number | yes | — |
| `status` | number | no | New order status id. |
| `delivery` | number | no | — |
| `payment` | number | no | — |

## 11. MIGX (5)

### `modx_migx_list_configs`

List MIGX configurations (migxConfig): id, name, category, published.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `query` | string | no | Filter by name/category. |
| `category` | string | no | — |
| `limit` | number | no | — |
| `start` | number | no | — |

### `modx_migx_get_config`

Get a full MIGX configuration by id (formtabs, columns, buttons, filters, permissions — as stored JSON strings).

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `id` | number | yes | — |

### `modx_migx_create_config`

Create a MIGX configuration. formtabs/columns/contextmenus/actionbuttons/columnbuttons/filters are JSON strings (same format MIGX stores in the manager).

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `name` | string | yes | — |
| `category` | string | no | — |
| `formtabs` | string | no | JSON: tab/field definitions. |
| `columns` | string | no | JSON: grid column definitions. |
| `contextmenus` | string | no | — |
| `actionbuttons` | string | no | — |
| `columnbuttons` | string | no | — |
| `filters` | string | no | — |
| `extended` | string | no | — |
| `permissions` | string | no | — |
| `fieldpermissions` | string | no | — |
| `published` | number | no | 0 or 1. |

### `modx_migx_update_config`

Update a MIGX configuration. Only the fields you pass are changed.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `id` | number | yes | — |
| `name` | string | no | — |
| `category` | string | no | — |
| `formtabs` | string | no | — |
| `columns` | string | no | — |
| `contextmenus` | string | no | — |
| `actionbuttons` | string | no | — |
| `columnbuttons` | string | no | — |
| `filters` | string | no | — |
| `extended` | string | no | — |
| `permissions` | string | no | — |
| `fieldpermissions` | string | no | — |
| `published` | number | no | — |

### `modx_migx_delete_config`

Delete a MIGX configuration by id.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `id` | number | yes | — |

## 12. Users and access control (47)

### `modx_flush_permissions`

Flush cached access permissions (the manager's 'Flush Permissions') so ACL changes apply. ACL write tools already do this automatically; use this for a manual flush.

**Parameters:** none.

### `modx_list_users`

List manager/web users (modUser).

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `query` | string | no | — |
| `limit` | number | no | — |
| `start` | number | no | — |

### `modx_get_user`

Get a user by id.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `id` | number | yes | — |

### `modx_create_user`

Create a user. Pass 'password' to set it explicitly; omit to auto-generate. Profile fields (email, fullname, phone…) are accepted alongside.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `username` | string | yes | — |
| `password` | string | no | — |
| `email` | string | no | — |
| `fullname` | string | no | — |
| `active` | number | no | 1 or 0. |

### `modx_update_user`

Update a user (profile fields, active state).

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `id` | number | yes | — |
| `email` | string | no | — |
| `fullname` | string | no | — |
| `active` | number | no | — |

### `modx_delete_user`

Delete a user by id.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `id` | number | yes | — |

### `modx_list_user_groups`

List MODX user groups (id, name, parent, rank).

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `query` | string | no | Filter by name. |
| `limit` | number | no | — |
| `start` | number | no | — |

### `modx_get_user_group`

Get a single user group by id.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `id` | number | yes | — |

### `modx_create_user_group`

Create a user group.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `name` | string | yes | — |
| `parent` | number | no | Parent user group id (0 = none). |

### `modx_update_user_group`

Update a user group.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `id` | number | yes | — |
| `name` | string | no | — |
| `parent` | number | no | — |

### `modx_delete_user_group`

Delete a user group by id.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `id` | number | yes | — |

### `modx_list_user_group_members`

List members of a user group.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `usergroup` | number | yes | User group id. |
| `limit` | number | no | — |
| `start` | number | no | — |

### `modx_add_user_to_group`

Add a user to a user group, optionally with a role.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `user` | number | yes | User id. |
| `usergroup` | number | yes | User group id. |
| `role` | number | no | Role id (optional). |

### `modx_update_group_member`

Update a user's membership in a group (e.g. change role).

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `user` | number | yes | — |
| `usergroup` | number | yes | — |
| `role` | number | no | — |

### `modx_remove_user_from_group`

Remove a user from a user group.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `user` | number | yes | User id. |
| `usergroup` | number | yes | User group id. |

### `modx_list_roles`

List access roles (id, name, authority).

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `query` | string | no | — |
| `limit` | number | no | — |
| `start` | number | no | — |

### `modx_get_role`

Get a role by id.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `id` | number | yes | — |

### `modx_create_role`

Create a role.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `name` | string | yes | — |
| `authority` | number | no | Authority level (lower = more authority). |

### `modx_update_role`

Update a role.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `id` | number | yes | — |
| `name` | string | no | — |
| `authority` | number | no | — |

### `modx_delete_role`

Delete a role by id.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `id` | number | yes | — |

### `modx_list_access_policies`

List access policies (id, name, description, template).

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `query` | string | no | — |
| `limit` | number | no | — |
| `start` | number | no | — |

### `modx_get_access_policy`

Get an access policy by id.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `id` | number | yes | — |

### `modx_create_access_policy`

Create an access policy.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `name` | string | yes | — |
| `description` | string | no | — |
| `template` | number | yes | Policy template id. |
| `data` | string | no | JSON map of permission=>bool (optional). |

### `modx_update_access_policy`

Update an access policy (e.g. its permissions map).

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `id` | number | yes | — |
| `name` | string | no | — |
| `description` | string | no | — |
| `permissions` | string | no | JSON map of permission=>bool. |

### `modx_delete_access_policy`

Delete an access policy by id.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `id` | number | yes | — |

### `modx_list_access_policy_templates`

List access policy templates.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `query` | string | no | — |
| `limit` | number | no | — |
| `start` | number | no | — |

### `modx_get_access_policy_template`

Get an access policy template by id.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `id` | number | yes | — |

### `modx_create_access_policy_template`

Create an access policy template.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `name` | string | yes | — |
| `description` | string | no | — |
| `template_group` | number | no | — |

### `modx_update_access_policy_template`

Update an access policy template.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `id` | number | yes | — |
| `name` | string | no | — |
| `description` | string | no | — |

### `modx_delete_access_policy_template`

Delete an access policy template by id.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `id` | number | yes | — |

### `modx_list_access_permissions`

List permissions defined by a policy template (read-only catalogue).

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `template` | number | no | Policy template id. |
| `query` | string | no | — |
| `limit` | number | no | — |
| `start` | number | no | — |

### `modx_list_resource_groups`

List resource groups (id, name).

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `query` | string | no | — |
| `limit` | number | no | — |
| `start` | number | no | — |

### `modx_get_resource_group`

Get a resource group by id.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `id` | number | yes | — |

### `modx_list_resource_group_resources`

List resources assigned to a resource group.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `resourceGroup` | number | yes | — |

### `modx_create_resource_group`

Create a resource group.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `name` | string | yes | — |

### `modx_update_resource_group`

Update a resource group.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `id` | number | yes | — |
| `name` | string | no | — |

### `modx_delete_resource_group`

Delete a resource group by id.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `id` | number | yes | — |

### `modx_assign_resource_to_group`

Add a resource (document) to a resource group.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `resource` | number | yes | Resource id. |
| `resourceGroup` | number | yes | Resource group id. |

### `modx_remove_resource_from_group`

Remove a resource (document) from a resource group.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `resource` | number | yes | Resource id. |
| `resourceGroup` | number | yes | Resource group id. |

### `modx_list_context_access`

List context-access ACL entries, optionally filtered by usergroup/context/policy.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `usergroup` | number | no | — |
| `context` | string | no | — |
| `policy` | number | no | — |
| `limit` | number | no | — |
| `start` | number | no | — |

### `modx_grant_context_access`

Grant a user group access to a context with a policy and minimum role authority.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `principal` | number | yes | User group id. |
| `target` | string | yes | Context key (e.g. 'web', 'mgr'). |
| `policy` | number | yes | Access policy id. |
| `authority` | number | no | Minimum role authority (0 = all). |

### `modx_update_context_access`

Update a context-access ACL entry by id.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `id` | number | yes | — |
| `principal` | number | no | — |
| `target` | string | no | — |
| `policy` | number | no | — |
| `authority` | number | no | — |

### `modx_revoke_context_access`

Remove a context-access ACL entry by id.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `id` | number | yes | — |

### `modx_list_resourcegroup_access`

List resource-group access ACL entries.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `usergroup` | number | no | — |
| `limit` | number | no | — |
| `start` | number | no | — |

### `modx_grant_resourcegroup_access`

Grant a user group access to a resource group with a policy and minimum role authority.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `principal` | number | yes | User group id. |
| `target` | number | yes | Resource group id. |
| `policy` | number | yes | Access policy id. |
| `authority` | number | no | Minimum role authority (0 = all). |

### `modx_update_resourcegroup_access`

Update a resource-group access ACL entry by id.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `id` | number | yes | — |
| `principal` | number | no | — |
| `target` | number | no | — |
| `policy` | number | no | — |
| `authority` | number | no | — |

### `modx_revoke_resourcegroup_access`

Remove a resource-group access ACL entry by id.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `id` | number | yes | — |

## 13. ClientConfig (5)

### `modx_clientconfig_list_settings`

List ClientConfig settings including context-specific values.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `query` | string | no | — |

### `modx_clientconfig_get_setting`

Get a ClientConfig setting by id or key.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `id` | number | no | — |
| `key` | string | no | — |

### `modx_clientconfig_create_setting`

Create a ClientConfig setting.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `key` | string | yes | — |
| `label` | string | no | — |
| `xtype` | string | no | — |
| `description` | string | no | — |
| `is_required` | boolean | no | — |
| `sortorder` | number | no | — |
| `value` | string | no | — |
| `default` | string | no | — |
| `group` | number | no | — |
| `options` | string | no | — |
| `process_options` | boolean | no | — |
| `source` | number | no | — |
| `context_values` | object | no | — |

### `modx_clientconfig_update_setting`

Update a ClientConfig setting by id or key.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `id` | number | no | — |
| `key` | string | no | — |
| `label` | string | no | — |
| `xtype` | string | no | — |
| `description` | string | no | — |
| `is_required` | boolean | no | — |
| `sortorder` | number | no | — |
| `value` | string | no | — |
| `default` | string | no | — |
| `group` | number | no | — |
| `options` | string | no | — |
| `process_options` | boolean | no | — |
| `source` | number | no | — |
| `context_values` | object | no | — |

### `modx_clientconfig_delete_setting`

Delete a ClientConfig setting by id or key.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `id` | number | no | — |
| `key` | string | no | — |

## 14. Property sets (7)

### `modx_list_property_sets`

List property sets (modPropertySet): id, name, description, category.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `query` | string | no | — |
| `limit` | number | no | — |
| `start` | number | no | — |

### `modx_get_property_set`

Get a property set by id (incl. its properties).

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `id` | number | yes | — |

### `modx_create_property_set`

Create a property set. 'properties' is a JSON object of property definitions.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `name` | string | yes | — |
| `description` | string | no | — |
| `category` | number | no | — |
| `properties` | object | no | — |

### `modx_update_property_set`

Update a property set.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `id` | number | yes | — |
| `name` | string | no | — |
| `description` | string | no | — |
| `category` | number | no | — |
| `properties` | object | no | — |

### `modx_delete_property_set`

Delete a property set by id.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `id` | number | yes | — |

### `modx_assign_property_set`

Attach a property set to an element. Identify the element with its id + either element_class (e.g. 'modSnippet') or element_type ('snippet'/'chunk'/'template'/'plugin'/'tv').

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `element` | number | yes | Element id. |
| `property_set` | number | yes | Property set id. |
| `element_class` | string | no | — |
| `element_type` | string | no | Allowed: snippet, chunk, template, plugin, tv |

### `modx_unassign_property_set`

Detach a property set from an element (same identification as assign).

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `element` | number | yes | — |
| `property_set` | number | yes | — |
| `element_class` | string | no | — |
| `element_type` | string | no | Allowed: snippet, chunk, template, plugin, tv |

## 15. Contexts (10)

### `modx_list_contexts`

List contexts (modContext): key, name, description, rank.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `query` | string | no | — |
| `limit` | number | no | — |
| `start` | number | no | — |

### `modx_get_context`

Get a context by key.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `key` | string | yes | — |

### `modx_create_context`

Create a context. 'key' is the unique identifier (e.g. 'mobile').

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `key` | string | yes | — |
| `name` | string | no | — |
| `description` | string | no | — |
| `rank` | number | no | — |

### `modx_update_context`

Update a context (name/description/rank). 'settings' may be a JSON array of context settings (advanced).

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `key` | string | yes | — |
| `name` | string | no | — |
| `description` | string | no | — |
| `rank` | number | no | — |
| `settings` | string | no | — |

### `modx_delete_context`

Delete a context by key.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `key` | string | yes | — |

### `modx_list_context_settings`

List settings of a context (modContextSetting).

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `context_key` | string | yes | — |
| `namespace` | string | no | — |
| `area` | string | no | — |
| `limit` | number | no | — |
| `start` | number | no | — |

### `modx_get_context_setting`

Get one context setting (by context_key + key).

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `context_key` | string | yes | — |
| `key` | string | yes | — |

### `modx_create_context_setting`

Create a context-level setting (overrides the system setting for that context).

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `context_key` | string | yes | — |
| `key` | string | yes | — |
| `value` | string | no | — |
| `xtype` | string | no | — |
| `namespace` | string | no | — |
| `area` | string | no | — |

### `modx_update_context_setting`

Update a context setting (by context_key + key).

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `context_key` | string | yes | — |
| `key` | string | yes | — |
| `value` | string | no | — |
| `xtype` | string | no | — |
| `namespace` | string | no | — |
| `area` | string | no | — |

### `modx_delete_context_setting`

Delete a context setting (by context_key + key).

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `context_key` | string | yes | — |
| `key` | string | yes | — |

## 16. Packages and providers (7)

### `modx_install_package`

Install a transport package from a provider (default modx.com) by name, e.g. {package:'MIGX'}. Returns 'already_installed' if present.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `package` | string | yes | — |
| `provider` | number | no | Provider id (defaults to modx.com). |
| `target_signature` | string | no | Optional exact provider signature for controlled update/downgrade. |

### `modx_uninstall_package`

Uninstall a transport package by signature (e.g. 'migx-2.13.0-pl').

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `signature` | string | yes | — |

### `modx_list_providers`

List transport providers (modx.com and custom, e.g. modstore.pro): id, name, service_url.

**Parameters:** none.

### `modx_search_packages`

Search a provider's catalogue (use before install_package). Defaults to modx.com.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `query` | string | no | — |
| `provider` | number | no | — |
| `limit` | number | no | — |
| `start` | number | no | — |

### `modx_create_provider`

Add a transport provider (e.g. modstore.pro). For paid providers pass username + api_key.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `name` | string | yes | — |
| `service_url` | string | yes | — |
| `description` | string | no | — |
| `username` | string | no | — |
| `api_key` | string | no | — |

### `modx_update_provider`

Update a transport provider.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `id` | number | yes | — |
| `name` | string | no | — |
| `service_url` | string | no | — |
| `description` | string | no | — |
| `username` | string | no | — |
| `api_key` | string | no | — |

### `modx_delete_provider`

Delete a transport provider by id.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `id` | number | yes | — |

## 17. Namespaces (4)

### `modx_list_namespaces`

List namespaces (modNamespace): name, path, assets_path.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `query` | string | no | — |
| `limit` | number | no | — |
| `start` | number | no | — |

### `modx_create_namespace`

Create a namespace.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `name` | string | yes | — |
| `path` | string | no | — |
| `assets_path` | string | no | — |

### `modx_update_namespace`

Update a namespace (by name).

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `name` | string | yes | — |
| `path` | string | no | — |
| `assets_path` | string | no | — |

### `modx_delete_namespace`

Delete a namespace by name ('core' cannot be deleted).

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `name` | string | yes | — |

## 18. Lexicon (4)

### `modx_list_lexicon_entries`

List lexicon entries, filtered by namespace + topic + language (and optional search).

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `namespace` | string | no | — |
| `topic` | string | no | — |
| `language` | string | no | — |
| `search` | string | no | — |
| `limit` | number | no | — |
| `start` | number | no | — |

### `modx_list_lexicon_topics`

List lexicon topics for a namespace/language.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `namespace` | string | no | — |
| `language` | string | no | — |
| `query` | string | no | — |

### `modx_set_lexicon_entry`

Create or override a lexicon entry (DB override of the file value).

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `name` | string | yes | — |
| `value` | string | no | — |
| `namespace` | string | yes | — |
| `topic` | string | yes | — |
| `language` | string | no | — |

### `modx_revert_lexicon_entry`

Revert a lexicon entry — remove its DB override so the file value applies.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `name` | string | yes | — |
| `namespace` | string | no | — |
| `topic` | string | no | — |
| `language` | string | no | — |

## 19. Operations and diagnostics (13)

### `modx_get_site_state`

Read the connector-authoritative site revision and atomic-precondition capability used by external infrastructure.

**Parameters:** none.

### `modx_describe_object`

Schema introspection: list an xPDO class's fields (name + php/db type, null, default) and its primary key, so you use REAL field names instead of guessing. Accepts a class name (e.g. modResource) or an alias (resource/chunk/snippet/template/plugin/tv/category/user/context/setting). Use before create/update on an unfamiliar object.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `class` | string | yes | xPDO class or alias, e.g. 'modResource' or 'resource'. |

### `modx_read_error_log`

Read the tail of the MODX error log (core/cache/logs/error.log) for diagnostics. Optional `limit` (lines, default 100).

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `limit` | number | no | — |

### `modx_refresh_uris`

Regenerate all resource URIs (run after bulk alias/structure changes that left stale URIs).

**Parameters:** none.

### `modx_remove_locks`

Clear stale manager edit locks (when an element/resource is reported locked by a dead session).

**Parameters:** none.

### `modx_project_overview`

Orient on the whole installed site in ONE compact, cheap call. Returns STRUCTURE + COUNTS (not content): template↔TV map, resource/product COUNTS (overall + by template + by context), a shallow resource tree (roots + child counts only), element categories, content types, contexts, installed integrations. Token-safe on huge sites (100k resources return the same small payload) — for per-item browsing use list_resources / list_elements. Call this FIRST when starting on an unfamiliar project. Optional `sections` to fetch only some parts; `max_tree_nodes` caps the tree roots.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `sections` | array of string | no | Subset of sections to return (default: all). |
| `max_tree_nodes` | number | no | Max root resources in resource_tree (default 50). |

### `modx_system_info`

Environment/diagnostic info: MODX version, modxMCP version, PHP version, db type, key paths.

**Parameters:** none.

### `modx_regenerate_token`

Rotate the modxmcp.api_token to a fresh random value and return it. NOTE: this invalidates the current token — update MODX_MCP_TOKEN in your client config immediately, or the next call will be unauthorized.

**Parameters:** none.

**Caution:** invalidates the previous API token; update client credentials immediately.

### `modx_help`

Built-in documentation. Call with no args to list topics; pass {topic} for a guide (e.g. 'tv_input_types', 'study_component', 'minishop2', 'migx', 'acl', 'getting_started'). Read the relevant topic before unfamiliar work, or to learn how to use a newly-installed component.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `topic` | string | no | Help topic; omit to list topics. |

### `modx_list_actions`

List the action names this modxMCP server build supports, grouped by area. Use it to detect client/server version skew.

**Parameters:** none.

### `modx_clear_cache`

Refresh the MODX cache. Pass partitions (e.g. ['resource','context_settings']) to refresh only those; omit for a full refresh.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `partitions` | array of string | no | — |

### `modx_read_audit_log`

Read the modxMCP write-audit trail (newest last). Optionally filter to one action.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `action` | string | no | Filter to this action name. |
| `limit` | number | no | Max entries (default 100). |

### `modx_run_processor`

Run ANY MODX processor directly (escape hatch). Requires the modxmcp.allow_run_processor setting to be enabled. High privilege — prefer a dedicated tool when one exists.

| Parameter | Type | Required | Notes and allowed values |
| --- | --- | :---: | --- |
| `processor` | string | yes | Processor path, e.g. 'security/user/getlist' (relative to processors_path). |
| `properties` | object | no | Processor properties/params. |
| `processors_path` | string | no | Override processors base path (for component processors). |

**Caution:** disabled by default through `modxmcp.allow_run_processor`; can modify the site.

## Errors and diagnostics

The PHP endpoint returns `{"success":true,"data":...}` on success and usually `{"success":false,"error":"..."}` on failure. Typical HTTP statuses include `401` (invalid token), `403` (component disabled, IP restriction, or HTTPS required), `405` (unsupported method), `413` (payload too large), `400` (invalid input), and `500` (internal error).

If an action is missing, use `modx_list_actions`, inspect disabled capability groups, compare client/server versions, and check whether the required addon is installed. An unauthenticated `GET` health probe returns only limited version/status metadata. Administration requires authenticated `POST`.

## Authoritative implementation

- [MCP client schemas and tool descriptions](https://github.com/rumata-estor/modx3-mcp/blob/main/client/index.js)
- [PHP action catalog](https://github.com/rumata-estor/modx3-mcp/blob/main/core/components/modxmcp/model/modxmcp.class.php)
- [HTTP endpoint](https://github.com/rumata-estor/modx3-mcp/blob/main/core/components/modxmcp/endpoint/api.common.php)

This reference describes the published API 1.2.1 contract; exact availability depends on the installed build.
