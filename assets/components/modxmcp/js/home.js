// MODX MCP SOURCE / ИСХОДНИК MODX MCP
// RU: Клиентский скрипт интерфейса MODX MCP: home.
// EN: MODX MCP Manager UI script: home.
Ext.onReady(function () {
    'use strict';
    if (typeof MODx === 'undefined' || !MODx.Ajax) { return; }
    var cfg = (typeof Modxmcp !== 'undefined') ? Modxmcp : {};
    var t = cfg.ui || {};
    var $ = function (id) { return document.getElementById(id); };
    var timeoutId = null;

    // ExtJS manager panels often clip overflow and disable document scrolling.
    // Measure the actual visible bounds of the nearest clipping parent, then
    // make this dashboard its own keyboard- and wheel-scrollable region.
    function fitDashboardScroll() {
        var panel = document.querySelector('.modxmcp-ui');
        if (!panel) { return; }
        var top = panel.getBoundingClientRect().top;
        var bottom = window.innerHeight;
        var parent = panel.parentElement;
        while (parent && parent !== document.body && parent !== document.documentElement) {
            var style = window.getComputedStyle(parent);
            if (/(hidden|clip|auto|scroll)/.test(style.overflowY) ||
                /(hidden|clip|auto|scroll)/.test(style.overflow)) {
                var box = parent.getBoundingClientRect();
                if (box.height > 0 && box.bottom > top) {
                    bottom = Math.min(bottom, box.bottom);
                }
            }
            parent = parent.parentElement;
        }
        var space = Math.floor(bottom - top - 8);
        if (space > 120) {
            panel.style.height = space + 'px';
            panel.style.maxHeight = space + 'px';
        }
    }
    fitDashboardScroll();
    window.addEventListener('resize', fitDashboardScroll);
    // MODX may finish laying out its ExtJS tabs after the CMP DOM becomes ready.
    window.setTimeout(fitDashboardScroll, 300);

    function report(id, message, error) {
        var item = $(id);
        if (item) { item.textContent = message || ''; item.setAttribute('data-error', error ? 'true' : 'false'); }
    }

    function request(action, params, success, failure) {
        var values = params || {};
        values.action = 'mgr/' + action;
        MODx.Ajax.request({
            url: cfg.connector_url,
            params: values,
            listeners: {
                success: {fn: function (r) {
                    if (r && r.success === false) { if (failure) failure(); return; }
                    if (success) success((r && r.object) || {});
                }},
                failure: {fn: function () { if (failure) failure(); }}
            }
        });
    }

    function fallbackCopy(value) {
        var area = document.createElement('textarea');
        area.value = value;
        area.setAttribute('readonly', 'readonly');
        area.style.position = 'fixed';
        area.style.left = '-9999px';
        document.body.appendChild(area);
        area.select();
        var okay = false;
        try { okay = document.execCommand('copy'); } catch (e) { /* browser denied clipboard */ }
        document.body.removeChild(area);
        return okay;
    }

    function copy(value, statusId) {
        function done(okay) { report(statusId, okay ? t.copied : t.copy_failed, !okay); }
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(value).then(function () { done(true); }, function () { done(fallbackCopy(value)); });
        } else { done(fallbackCopy(value)); }
    }

    function hideToken() {
        if (timeoutId !== null) { clearTimeout(timeoutId); timeoutId = null; }
        var input = $('mcp-token');
        if (!input) { return; }
        input.value = input.getAttribute('data-preview') || '';
        input.dataset.revealed = 'false';
        var btn = $('mcp-show-token');
        if (btn) { btn.textContent = t.show || 'Show'; }
    }

    var endpointCopy = $('mcp-copy-endpoint');
    if (endpointCopy) { endpointCopy.addEventListener('click', function () { copy($('mcp-endpoint').value, 'mcp-connection-status'); }); }

    var showToken = $('mcp-show-token');
    if (showToken) {
        showToken.addEventListener('click', function () {
            if ($('mcp-token').dataset.revealed === 'true') {
                hideToken(); report('mcp-connection-status', t.token_hidden); return;
            }
            showToken.disabled = true;
            request('gettoken3', {}, function (data) {
                showToken.disabled = false;
                if (!data.token) { report('mcp-connection-status', t.token_error, true); return; }
                var input = $('mcp-token');
                input.value = data.token;
                input.dataset.revealed = 'true';
                showToken.textContent = t.hide || 'Hide';
                report('mcp-connection-status', t.token_shown);
                if (timeoutId !== null) { clearTimeout(timeoutId); }
                timeoutId = setTimeout(hideToken, 60000);
            }, function () { showToken.disabled = false; report('mcp-connection-status', t.token_error, true); });
        });
    }

    var copyToken = $('mcp-copy-token');
    if (copyToken) {
        copyToken.addEventListener('click', function () {
            copyToken.disabled = true;
            request('gettoken3', {}, function (data) {
                copyToken.disabled = false;
                if (!data.token) { report('mcp-connection-status', t.token_error, true); return; }
                copy(data.token, 'mcp-connection-status');
            }, function () { copyToken.disabled = false; report('mcp-connection-status', t.token_error, true); });
        });
    }

    var regenerate = $('modxmcp-regenerate');
    if (regenerate) {
        regenerate.addEventListener('click', function () {
            if (!confirm(cfg.confirm_regenerate || 'Regenerate API token?')) { return; }
            regenerate.disabled = true;
            request('regeneratetoken3', {}, function (data) {
                regenerate.disabled = false;
                if (!data.token) { report('mcp-connection-status', t.token_error, true); return; }
                var fresh = $('modxmcp-token-value');
                var box = $('modxmcp-token-result');
                if (fresh && box) { fresh.value = data.token; box.hidden = false; }
                var input = $('mcp-token');
                if (input) {
                    input.setAttribute('data-preview', data.token.substring(0, 6) + '…' + data.token.slice(-4));
                    hideToken();
                }
                report('mcp-connection-status', t.new_token);
            }, function () { regenerate.disabled = false; report('mcp-connection-status', cfg.regenerate_failed, true); });
        });
    }
    var copyNew = $('mcp-copy-new-token');
    if (copyNew) { copyNew.addEventListener('click', function () { copy($('modxmcp-token-value').value, 'mcp-connection-status'); }); }

    function initGroupSettings(selector, saveId, statusId, endpoint, valueBuilder) {
        var button = $(saveId);
        if (!button) { return; }
        var inputs = Array.prototype.slice.call(document.querySelectorAll(selector));
        var serial = function () { return inputs.map(function (x) { return (x.checked ? '1' : '0') + ':' + (x.getAttribute('data-group') || x.getAttribute('data-key')); }).join('|'); };
        var initial = serial();
        function checkDirty() {
            button.disabled = serial() === initial;
            report(statusId, '');
        }
        inputs.forEach(function (input) { input.addEventListener('change', checkDirty); });
        button.addEventListener('click', function () {
            button.disabled = true;
            request(endpoint, valueBuilder(inputs), function () {
                initial = serial();
                button.disabled = true;
                report(statusId, t.saved);
            }, function () {
                button.disabled = false;
                report(statusId, t.error, true);
            });
        });
    }

    initGroupSettings('.mcp-setting', 'mcp-save-settings', 'mcp-settings-status', 'savesettings3', function (inputs) {
        var data = {};
        inputs.forEach(function (el) { data[el.getAttribute('data-key')] = el.checked ? '1' : '0'; });
        return data;
    });
    initGroupSettings('.modxmcp-grp', 'modxmcp-savegroups', 'modxmcp-groups-status', 'savegroups', function (inputs) {
        return {disabled: inputs.filter(function (el) { return !el.checked; }).map(function (el) { return el.getAttribute('data-group'); }).join(',')};
    });
});
