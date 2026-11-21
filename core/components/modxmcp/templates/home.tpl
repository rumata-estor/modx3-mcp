{* MODX MCP SOURCE / ИСХОДНИК MODX MCP
   RU: Smarty-шаблон панели управления MODX MCP.
   EN: MODX MCP Manager dashboard Smarty template. *}
<div class="modxmcp-ui" tabindex="0">
    <header class="mcp-head">
        <div>
            <h2>MODX MCP</h2>
            <p>{$l.ui_subtitle}</p>
        </div>
        <div class="mcp-health {if $enabled}mcp-health-on{else}mcp-health-off{/if}">
            <span class="mcp-health-dot" aria-hidden="true"></span>
            {if $enabled}{$l.ui_enabled}{else}{$l.ui_disabled}{/if}
        </div>
    </header>

    <div class="mcp-layout">
        <div class="mcp-column">
            <section class="mcp-panel" aria-labelledby="mcp-connect-title">
                <div class="mcp-panel-head">
                    <h3 id="mcp-connect-title">{$l.ui_connection}</h3>
                    <p>{$l.ui_connection_help}</p>
                </div>
                <div class="mcp-field">
                    <label for="mcp-endpoint">{$l.ui_endpoint}</label>
                    <div class="mcp-input-line">
                        <input id="mcp-endpoint" type="text" readonly value="{$endpoint|escape:'html'}" spellcheck="false" autocomplete="off">
                        <button type="button" class="mcp-button" id="mcp-copy-endpoint">{$l.ui_copy}</button>
                    </div>
                </div>
                <div class="mcp-field">
                    <label for="mcp-token">{$l.token}</label>
                    {if $token_set}
                        <div class="mcp-input-line">
                            <input id="mcp-token" type="text" readonly value="{$token_preview|escape:'html'}" data-preview="{$token_preview|escape:'html'}" spellcheck="false" autocomplete="off" aria-label="{$l.token|escape:'html'}">
                            <button type="button" class="mcp-button" id="mcp-show-token">{$l.ui_show}</button>
                            <button type="button" class="mcp-button" id="mcp-copy-token">{$l.ui_copy}</button>
                        </div>
                        <p class="mcp-field-note">{$l.ui_token_help}</p>
                    {else}
                        <p class="mcp-no-token">{$l.token_notset}</p>
                    {/if}
                </div>
                <div class="mcp-danger-zone">
                    <p>{$l.ui_regenerate_help}</p>
                    <button type="button" class="mcp-button mcp-button-outline" id="modxmcp-regenerate">{$l.regenerate}</button>
                    <div id="modxmcp-token-result" class="mcp-new-token" hidden>
                        <label for="modxmcp-token-value">{$l.new_token}</label>
                        <div class="mcp-input-line">
                            <input id="modxmcp-token-value" type="text" readonly spellcheck="false" autocomplete="off">
                            <button type="button" class="mcp-button" id="mcp-copy-new-token">{$l.ui_copy}</button>
                        </div>
                    </div>
                </div>
                <p class="mcp-status" id="mcp-connection-status" role="status" aria-live="polite"></p>
            </section>

            <section class="mcp-panel" aria-labelledby="mcp-settings-title">
                <div class="mcp-panel-head">
                    <h3 id="mcp-settings-title">{$l.ui_settings}</h3>
                    <p>{$l.ui_settings_help}</p>
                </div>
                <label class="mcp-toggle-row" for="mcp-auto-static">
                    <span><strong>{$l.auto_static}</strong><small>{$l.ui_auto_static_desc}</small></span>
                    <input id="mcp-auto-static" type="checkbox" class="mcp-setting" data-key="auto_static"{if $auto_static} checked{/if}>
                </label>
                <label class="mcp-toggle-row" for="mcp-audit-log">
                    <span><strong>{$l.audit_log}</strong><small>{$l.ui_audit_desc}</small></span>
                    <input id="mcp-audit-log" type="checkbox" class="mcp-setting" data-key="audit_log"{if $audit_log} checked{/if}>
                </label>
                <div class="mcp-actions">
                    <button type="button" class="mcp-button mcp-button-primary" id="mcp-save-settings" disabled>{$l.ui_save_settings}</button>
                    <span class="mcp-status" id="mcp-settings-status" role="status" aria-live="polite"></span>
                </div>
                <p class="mcp-field-note">{$l.settings_hint}</p>
            </section>
        </div>

        <div class="mcp-column">
            {if $components || $features}
            <section class="mcp-panel" aria-labelledby="mcp-capabilities-title">
                <div class="mcp-panel-head">
                    <h3 id="mcp-capabilities-title">{$l.ui_capabilities}</h3>
                    <p>{$l.ui_capabilities_help}</p>
                </div>
                {if $components}
                <h4 class="mcp-subhead">{$l.ui_components}</h4>
                <div class="mcp-check-grid">
                {foreach $components as $g}
                    <label class="mcp-check-row"><input type="checkbox" class="modxmcp-grp" data-group="{$g.key|escape:'html'}"{if $g.enabled} checked{/if}><span>{$g.label|escape:'html'}<small>{$g.key|escape:'html'}</small></span></label>
                {/foreach}
                </div>
                {/if}
                {if $features}
                <h4 class="mcp-subhead">{$l.ui_modx_features}</h4>
                <div class="mcp-check-grid">
                {foreach $features as $g}
                    <label class="mcp-check-row"><input type="checkbox" class="modxmcp-grp" data-group="{$g.key|escape:'html'}"{if $g.enabled} checked{/if}><span>{$g.label|escape:'html'}<small>{$g.key|escape:'html'}</small></span></label>
                {/foreach}
                </div>
                {/if}
                <div class="mcp-actions">
                    <button type="button" class="mcp-button mcp-button-primary" id="modxmcp-savegroups" disabled>{$l.ui_save_capabilities}</button>
                    <span class="mcp-status" id="modxmcp-groups-status" role="status" aria-live="polite"></span>
                </div>
            </section>
            {/if}

            <section class="mcp-panel" aria-labelledby="mcp-tools-title">
                <div class="mcp-panel-head"><h3 id="mcp-tools-title">{$l.ui_tools}</h3></div>
                <div class="mcp-tool-row">
                    <div><strong>{$l.ui_graph}</strong><p>{$l.ui_graph_help}</p></div>
                    <a class="mcp-button" href="?a=graph&amp;namespace=modxmcp">{$l.ui_open_graph} &rarr;</a>
                </div>
                {if $integrations}
                <details class="mcp-integrations">
                    <summary>{$l.integrations}</summary>
                    <p>{$l.integrations_intro}</p>
                    {foreach $integrations as $i}
                    <div class="mcp-integration-row"><strong>{$i.label|escape:'html'}</strong><span>{if $i.installed}{if $i.version}{$i.version|escape:'html'}{else}{$l.yes}{/if}{else}—{/if}</span></div>
                    {/foreach}
                </details>
                {/if}
            </section>
        </div>
    </div>
</div>
