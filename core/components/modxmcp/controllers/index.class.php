<?php

use MODX\Revolution\modExtraManagerController;
/**
 * modxMCP — manager CMP controller (Components > modxMCP).
 *
 * Read-only status dashboard plus a "regenerate token" button. The class name
 * MUST be Modxmcp + Index + ManagerController for MODX 2.3+ namespaced controller
 * autoloading (namespace "modxmcp", action "index").
 *
 * UI strings are lexicon-driven (en + ru), so the panel follows the manager language.
 */
class ModxmcpIndexManagerController extends modExtraManagerController {

    public function checkPermissions() {
        return $this->modx->hasPermission('settings');
    }

    public function getPageTitle() {
        return $this->modx->lexicon('modxmcp');
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
            'connector_url'      => $assetsUrl . 'connector.php',
            'confirm_regenerate' => $this->modx->lexicon('modxmcp_cmp_confirm_regenerate'),
            'regenerate_failed'  => $this->modx->lexicon('modxmcp_cmp_regenerate_failed'),
            'ui' => array(
                'show' => $this->modx->lexicon('modxmcp_cmp_ui_show'),
                'hide' => $this->modx->lexicon('modxmcp_cmp_ui_hide'),
                'saved' => $this->modx->lexicon('modxmcp_cmp_ui_saved'),
                'error' => $this->modx->lexicon('modxmcp_cmp_ui_error'),
                'copied' => $this->modx->lexicon('modxmcp_cmp_ui_copied'),
                'copy_failed' => $this->modx->lexicon('modxmcp_cmp_ui_copy_failed'),
                'token_error' => $this->modx->lexicon('modxmcp_cmp_ui_token_error'),
                'token_shown' => $this->modx->lexicon('modxmcp_cmp_ui_token_shown'),
                'token_hidden' => $this->modx->lexicon('modxmcp_cmp_ui_token_hidden'),
                'new_token' => $this->modx->lexicon('modxmcp_cmp_ui_new_token'),
            ),
        ), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $this->addHtml('<script type="text/javascript">var Modxmcp = ' . $cfg . ';</script>');
        // Cache-bust home.js by its mtime so browsers always load the current version.
        $jsFile = $this->modx->getOption('assets_path') . 'components/modxmcp/js/home.js';
        $ver = file_exists($jsFile) ? filemtime($jsFile) : '1';
        // The responsive dashboard stylesheet is isolated under .modxmcp-ui.
        $cssFile = $this->modx->getOption('assets_path') . 'components/modxmcp/css/dashboard.css';
        $cssVer = file_exists($cssFile) ? filemtime($cssFile) : '1';
        $this->addCss($assetsUrl . 'css/dashboard.css?v=' . $cssVer);
        $this->addJavascript($assetsUrl . 'js/home.js?v=' . $ver);
    }

    public function process(array $scriptProperties = array()) {
        $this->modx->lexicon->load('modxmcp:default');
        $token = (string) $this->modx->getOption('modxmcp.api_token');
        $siteUrl = rtrim((string) $this->modx->getOption('site_url'), '/');

        $integrations = array();
        $modelFile = $this->modx->getOption('core_path') . 'components/modxmcp/model/modxmcp.class.php';
        if (file_exists($modelFile)) {
            require_once $modelFile;
            try {
                $mcp = new modxMCP($this->modx);
                $report = $mcp->getIntegrationsReport();
                $integrations = isset($report['integrations']) ? $report['integrations'] : array();
            } catch (Exception $e) {
                $integrations = array();
            }
        }

        // Capability groups the admin can toggle on/off, split into add-on components vs core MODX.
        $components = array();
        $features = array();
        if (isset($mcp)) {
            try {
                $caps = $mcp->getCapabilities();
                $disabled = array_flip(isset($caps['disabled_groups']) ? $caps['disabled_groups'] : array());
                $meta = array(
                    'versionx'           => array($this->modx->lexicon('modxmcp_cmp_cap_versionx'), 'component'),
                    'virtualpage'        => array($this->modx->lexicon('modxmcp_cmp_cap_virtualpage'), 'component'),
                    'minishop2'          => array($this->modx->lexicon('modxmcp_cmp_cap_minishop2'), 'component'),
                    'migx'               => array($this->modx->lexicon('modxmcp_cmp_cap_migx'), 'component'),
                    'access'             => array($this->modx->lexicon('modxmcp_cmp_cap_access'), 'feature'),
                    'contexts'           => array($this->modx->lexicon('modxmcp_cmp_cap_contexts'), 'feature'),
                    'property_sets'      => array($this->modx->lexicon('modxmcp_cmp_cap_property_sets'), 'feature'),
                    'namespaces'         => array($this->modx->lexicon('modxmcp_cmp_cap_namespaces'), 'feature'),
                    'lexicon'            => array($this->modx->lexicon('modxmcp_cmp_cap_lexicon'), 'feature'),
                    'package_management' => array($this->modx->lexicon('modxmcp_cmp_cap_package_management'), 'feature'),
                );
                foreach (isset($caps['toggleable_groups']) ? $caps['toggleable_groups'] : array() as $g) {
                    $label = isset($meta[$g]) ? $meta[$g][0] : $g;
                    $kind = isset($meta[$g]) ? $meta[$g][1] : 'feature';
                    $row = array('key' => $g, 'label' => $label, 'enabled' => isset($disabled[$g]) ? 0 : 1);
                    if ($kind === 'component') { $components[] = $row; } else { $features[] = $row; }
                }
            } catch (Exception $e) {
                $components = array();
                $features = array();
            }
        }

        $labelKeys = array(
            'intro', 'endpoint', 'enabled', 'yes', 'no', 'enable_hint', 'token', 'token_set',
            'token_notset', 'auto_static', 'audit_log', 'package_install', 'on', 'off',
            'regenerate', 'new_token', 'settings_hint', 'integrations', 'integrations_intro',
            'ui_subtitle', 'ui_enabled', 'ui_disabled', 'ui_connection', 'ui_connection_help',
            'ui_endpoint', 'ui_copy', 'ui_show', 'ui_hide', 'ui_token_help', 'ui_regenerate_help',
            'ui_settings', 'ui_settings_help', 'ui_auto_static_desc', 'ui_audit_desc',
            'ui_save_settings', 'ui_capabilities', 'ui_capabilities_help', 'ui_components',
            'ui_modx_features', 'ui_save_capabilities', 'ui_tools', 'ui_graph',
            'ui_graph_help', 'ui_open_graph',
        );
        $l = array();
        foreach ($labelKeys as $k) {
            $l[$k] = $this->modx->lexicon('modxmcp_cmp_' . $k);
        }

        $this->setPlaceholders(array(
            'l'               => $l,
            'enabled'         => $this->modx->getOption('modxmcp.enabled') ? 1 : 0,
            'token_set'       => $token !== '' ? 1 : 0,
            'token_preview'   => $token !== '' ? (substr($token, 0, 6) . '…' . substr($token, -4)) : '—',
            'auto_static'     => $this->modx->getOption('modxmcp.auto_static') ? 1 : 0,
            'audit_log'       => $this->modx->getOption('modxmcp.audit_log') ? 1 : 0,
            'endpoint'        => $siteUrl . '/assets/components/modxmcp/api.php',
            'integrations'    => $integrations,
            'components'      => $components,
            'features'        => $features,
        ));
    }

    public function getTemplateFile() {
        return $this->modx->getOption('core_path') . 'components/modxmcp/templates/home.tpl';
    }
}