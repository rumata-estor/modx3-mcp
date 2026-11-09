<?php

use MODX\Revolution\modExtraManagerController;
/**
 * modxMCP — dependency graph screen (Components > modxMCP > Граф связей).
 *
 * Its own manager action so the graph gets the full width and height of the content region —
 * on the settings page it competed with the dashboard and pushed it out of view.
 *
 * Class name MUST be Modxmcp + Graph + ManagerController for MODX 2.3+ namespaced controller
 * autoloading (namespace "modxmcp", action "graph").
 */
class ModxmcpGraphManagerController extends modExtraManagerController {

    public function checkPermissions() {
        return $this->modx->hasPermission('settings');
    }

    public function getPageTitle() {
        // getPageTitle() runs before getLanguageTopics() is applied, so load the topic ourselves
        // or the raw lexicon key ends up in the browser title.
        $this->modx->lexicon->load('modxmcp:default');
        return $this->modx->lexicon('modxmcp') . ' — ' . $this->modx->lexicon('modxmcp_graph_title');
    }

    public function getLanguageTopics() {
        return array('modxmcp:default');
    }

    public function loadCustomCssJs() {
        $this->modx->lexicon->load('modxmcp:default');
        $assetsUrl = $this->modx->getOption(
            'modxmcp.assets_url',
            null,
            $this->modx->getOption('assets_url') . 'components/modxmcp/'
        );
        $cfg = json_encode(array(
            'connector_url' => $assetsUrl . 'connector.php',
            'manager_url'   => $this->modx->getOption('manager_url'),
            'ui'            => $this->graphLabels(),
        ), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $this->addHtml('<script type="text/javascript">var ModxmcpGraph = ' . $cfg . ';</script>');
        // Isolated graph dashboard stylesheet, cache-busted alongside graph.js.
        $cssFile = $this->modx->getOption('assets_path') . 'components/modxmcp/css/graph.css';
        $cssVer = file_exists($cssFile) ? filemtime($cssFile) : '1';
        $this->addCss($assetsUrl . 'css/graph.css?v=' . $cssVer);
        // Cache-bust by mtime so the browser always gets the current build.
        $jsFile = $this->modx->getOption('assets_path') . 'components/modxmcp/js/graph.js';
        $ver = file_exists($jsFile) ? filemtime($jsFile) : '1';
        $this->addJavascript($assetsUrl . 'js/graph.js?v=' . $ver);
    }

    public function process(array $scriptProperties = array()) {
        $this->modx->lexicon->load('modxmcp:default');
        $this->setPlaceholders(array(
            'settings_url' => '?a=index&namespace=modxmcp',
            'g'            => $this->graphLabels(),
        ));
    }

    private function graphLabels() {
        $keys = array(
            'heading', 'subtitle', 'back', 'search_placeholder', 'layout_lanes', 'layout_lanes_help',
            'layout_clusters', 'layout_clusters_help', 'layout_free', 'layout_free_help', 'reload', 'fit',
            'reset', 'loading', 'loading_graph', 'filters_aria', 'node_types', 'filters',
            'hide_vendor_help', 'hide_vendor', 'hide_orphans', 'issues_only', 'resource_refs', 'density',
            'dense', 'sparse', 'broken_links', 'unused', 'canvas_help', 'template_plural',
            'chunk_plural', 'snippet_plural', 'tv_plural', 'plugin_plural', 'resource_plural', 'missing_plural',
            'template_singular', 'chunk_singular', 'snippet_singular', 'tv_singular', 'plugin_singular', 'resource_singular',
            'missing_singular', 'kind_tag', 'kind_attached', 'kind_property', 'kind_php', 'kind_migx',
            'kind_binding', 'kind_link', 'kind_template', 'kind_missing', 'empty_response', 'build_failed',
            'error', 'nodes_unit', 'of', 'links_unit', 'broken_unit', 'orphan_unit',
            'from', 'no_broken', 'all_used', 'unverified_note', 'candidate_note', 'steps',
            'all', 'uses', 'used_in', 'disabled', 'static_flag', 'broken_title',
            'snippet_cap', 'chunk_cap', 'missing_explanation', 'unreferenced_title', 'deletion_warning', 'used_externally',
            'system_setting', 'order_status', 'edit', 'isolate', 'resources_on_template', 'events',
            'more', 'uses_heading', 'used_in_heading', 'no_relations', 'no_results', 'reason_template',
            'reason_tv', 'reason_other'
        );
        $labels = array();
        foreach ($keys as $key) {
            $labels[$key] = $this->modx->lexicon('modxmcp_graph_' . $key);
        }
        return $labels;
    }

    public function getTemplateFile() {
        return $this->modx->getOption('core_path') . 'components/modxmcp/templates/graph.tpl';
    }
}