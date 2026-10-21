
<div id="mcpg" tabindex="0">
    <header class="g-page-header">
        <div class="g-page-heading">
            <h2>{$g.heading}</h2>
            <p>{$g.subtitle}</p>
        </div>
        <a class="g-back" href="{$settings_url}">{$g.back}</a>
    </header>
    <div class="g-bar">
        <div class="g-search">
            <input type="search" id="g-q" placeholder="{$g.search_placeholder}" autocomplete="off">
            <div class="g-results" id="g-res"></div>
        </div>
        <span class="g-seg">
            <button class="g-btn g-lay on" data-lay="lanes" title="{$g.layout_lanes_help}">{$g.layout_lanes}</button><button class="g-btn g-lay" data-lay="clusters" title="{$g.layout_clusters_help}">{$g.layout_clusters}</button><button class="g-btn g-lay" data-lay="free" title="{$g.layout_free_help}">{$g.layout_free}</button>
        </span>
        <button class="g-btn" id="g-reload">{$g.reload}</button>
        <button class="g-btn" id="g-fit">{$g.fit}</button>
        <button class="g-btn" id="g-reset" disabled>{$g.reset}</button>
        <span class="g-stat" id="g-stat">{$g.loading}</span>
    </div>

    <div class="g-body">
        <aside class="g-side" aria-label="{$g.filters_aria}">
            <section>
                <h3>{$g.node_types}</h3>
                <div id="g-types"></div>
            </section>
            <section>
                <h3>{$g.filters}</h3>
                <label title="{$g.hide_vendor_help}"><input type="checkbox" id="g-hide-vendor"><span class="c">{$g.hide_vendor}</span><span class="n" id="g-nvendor">0</span></label>
                <label><input type="checkbox" id="g-hide-orphans"><span class="c">{$g.hide_orphans}</span><span class="n" id="g-norph2">0</span></label>
                <label><input type="checkbox" id="g-only-issues"><span class="c">{$g.issues_only}</span></label>
                <label><input type="checkbox" id="g-res-nodes"><span class="c">{$g.resource_refs} <code>[[~id]]</code></span></label>
            </section>
            <section>
                <h3>{$g.density}</h3>
                <input type="range" id="g-spread" min="60" max="260" value="100" style="width:100%">
                <div style="display:flex;justify-content:space-between;color:#7b8794;font-size:11px;">
                    <span>{$g.dense}</span><span>{$g.sparse}</span>
                </div>
            </section>
            <section>
                <h3>{$g.broken_links} (<span id="g-nmiss">0</span>)</h3>
                <ul class="g-list" id="g-miss"></ul>
            </section>
            <section>
                <h3>{$g.unused} (<span id="g-norph">0</span>)</h3>
                <ul class="g-list" id="g-orph"></ul>
                <p class="g-empty-note" id="g-orph-note" style="font-size:11px;margin-top:6px;"></p>
            </section>
        </aside>

        <div class="g-stage">
            <canvas id="g-canvas"></canvas>
            <div class="g-crumb" id="g-crumb"></div>
            <div class="g-overlay" id="g-overlay">{$g.loading_graph}</div>
            <div class="g-hint">{$g.canvas_help}</div>
            <div class="g-legend" id="g-legend"></div>
        </div>

        <div class="g-info" id="g-info"></div>
    </div>
</div>
