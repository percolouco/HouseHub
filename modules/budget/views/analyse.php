<?php
// modules/budget/views/analyse.php
// Onglet « Analyse » : évolution des dépenses en graphiques, avec filtres avancés.
// Les opérations sont chargées une fois (api/analyse-data.php) puis filtrées et agrégées dans le navigateur.
$anKeys = ['bud_an_title','bud_an_export','bud_an_reset','bud_an_period','bud_an_p3','bud_an_p6','bud_an_p12','bud_an_pyear','bud_an_pall','bud_an_from','bud_an_to',
    'bud_an_basis','bud_an_basis_gm','bud_an_basis_d','bud_an_flow','bud_an_flow_exp','bud_an_flow_inc','bud_an_flow_net','bud_an_group','bud_an_g_cat','bud_an_g_kind',
    'bud_an_g_item','bud_an_g_label','bud_an_g_none','bud_an_gran','bud_an_gran_m','bud_an_gran_w','bud_an_gran_d','bud_an_chart','bud_an_c_stack','bud_an_c_bar',
    'bud_an_c_line','bud_an_c_area','bud_an_cumul','bud_an_compare','bud_an_avg','bud_an_savings','bud_an_cats','bud_an_all','bud_an_none','bud_an_amount','bud_an_min',
    'bud_an_max','bud_an_search','bud_an_filters','bud_an_total','bud_an_avg_month','bud_an_peak','bud_an_vs_prev','bud_an_ops','bud_an_prev','bud_an_trend',
    'bud_an_others','bud_an_nocat','bud_an_kind_fixed','bud_an_kind_est','bud_an_kind_off','bud_an_kind_inc','bud_an_split','bud_an_top','bud_an_tx','bud_an_focus',
    'bud_an_clear_focus','bud_an_date','bud_an_label','bud_an_more','bud_an_empty','bud_an_hint','bud_an_locale'];
$anT = [];
foreach ($anKeys as $k) $anT[substr($k, 7)] = tr($k);
for ($i = 1; $i <= 12; $i++) $anT['mo' . $i] = tr('month_' . str_pad($i, 2, '0', STR_PAD_LEFT));
?>
<div class="budget-view an-view" id="an-root">
    <div class="view-header">
        <h2>📈 <?= tr('bud_an_title') ?></h2>
        <div class="an-head-actions">
            <button type="button" class="pf-btn an-btn-ghost" id="an-filters-toggle">🎛️ <?= tr('bud_an_filters') ?></button>
            <button type="button" class="pf-btn an-btn-ghost" id="an-reset">↺ <?= tr('bud_an_reset') ?></button>
            <button type="button" class="pf-btn" id="an-export">⬇ <?= tr('bud_an_export') ?></button>
        </div>
    </div>

    <div class="an-panel" id="an-panel">
        <div class="an-row">
            <div class="an-field an-grow">
                <label><?= tr('bud_an_period') ?></label>
                <div class="an-chips" id="an-presets">
                    <button type="button" data-p="3"><?= tr('bud_an_p3') ?></button>
                    <button type="button" data-p="6"><?= tr('bud_an_p6') ?></button>
                    <button type="button" data-p="12"><?= tr('bud_an_p12') ?></button>
                    <button type="button" data-p="year"><?= tr('bud_an_pyear') ?></button>
                    <button type="button" data-p="all"><?= tr('bud_an_pall') ?></button>
                </div>
            </div>
            <div class="an-field"><label for="an-from"><?= tr('bud_an_from') ?></label><input type="month" id="an-from"></div>
            <div class="an-field"><label for="an-to"><?= tr('bud_an_to') ?></label><input type="month" id="an-to"></div>
            <div class="an-field">
                <label for="an-basis"><?= tr('bud_an_basis') ?></label>
                <select id="an-basis">
                    <option value="m"><?= tr('bud_an_basis_gm') ?></option>
                    <option value="d"><?= tr('bud_an_basis_d') ?></option>
                </select>
            </div>
        </div>

        <div class="an-row">
            <div class="an-field">
                <label><?= tr('bud_an_flow') ?></label>
                <div class="an-seg" id="an-flow">
                    <button type="button" data-v="exp"><?= tr('bud_an_flow_exp') ?></button>
                    <button type="button" data-v="inc"><?= tr('bud_an_flow_inc') ?></button>
                    <button type="button" data-v="net"><?= tr('bud_an_flow_net') ?></button>
                </div>
            </div>
            <div class="an-field">
                <label for="an-group"><?= tr('bud_an_group') ?></label>
                <select id="an-group">
                    <option value="cat"><?= tr('bud_an_g_cat') ?></option>
                    <option value="kind"><?= tr('bud_an_g_kind') ?></option>
                    <option value="item"><?= tr('bud_an_g_item') ?></option>
                    <option value="label"><?= tr('bud_an_g_label') ?></option>
                    <option value="none"><?= tr('bud_an_g_none') ?></option>
                </select>
            </div>
            <div class="an-field">
                <label><?= tr('bud_an_gran') ?></label>
                <div class="an-seg" id="an-gran">
                    <button type="button" data-v="m"><?= tr('bud_an_gran_m') ?></button>
                    <button type="button" data-v="w"><?= tr('bud_an_gran_w') ?></button>
                    <button type="button" data-v="d"><?= tr('bud_an_gran_d') ?></button>
                </div>
            </div>
            <div class="an-field">
                <label><?= tr('bud_an_chart') ?></label>
                <div class="an-seg" id="an-type">
                    <button type="button" data-v="stack" title="<?= tr('bud_an_c_stack') ?>">▥</button>
                    <button type="button" data-v="bar" title="<?= tr('bud_an_c_bar') ?>">▮▮</button>
                    <button type="button" data-v="line" title="<?= tr('bud_an_c_line') ?>">〰</button>
                    <button type="button" data-v="area" title="<?= tr('bud_an_c_area') ?>">◭</button>
                </div>
            </div>
        </div>

        <div class="an-row">
            <label class="an-check"><input type="checkbox" id="an-cumul"> <?= tr('bud_an_cumul') ?></label>
            <label class="an-check"><input type="checkbox" id="an-compare"> <?= tr('bud_an_compare') ?></label>
            <label class="an-check"><input type="checkbox" id="an-avg"> <?= tr('bud_an_avg') ?></label>
            <label class="an-check"><input type="checkbox" id="an-savings"> <?= tr('bud_an_savings') ?></label>
        </div>

        <div class="an-row">
            <div class="an-field an-grow">
                <label><?= tr('bud_an_cats') ?>
                    <a href="#" class="an-mini" id="an-cats-all"><?= tr('bud_an_all') ?></a> ·
                    <a href="#" class="an-mini" id="an-cats-none"><?= tr('bud_an_none') ?></a>
                </label>
                <div class="an-chips an-catchips" id="an-cats"></div>
            </div>
        </div>

        <div class="an-row">
            <div class="an-field"><label for="an-min"><?= tr('bud_an_amount') ?></label>
                <div class="an-pair"><input type="number" id="an-min" min="0" step="1" placeholder="<?= tr('bud_an_min') ?>"><input type="number" id="an-max" min="0" step="1" placeholder="<?= tr('bud_an_max') ?>"></div>
            </div>
            <div class="an-field an-grow"><label for="an-search"><?= tr('bud_an_g_label') ?></label><input type="search" id="an-search" placeholder="<?= htmlspecialchars(tr('bud_an_search')) ?>"></div>
        </div>
    </div>

    <div class="an-kpis" id="an-kpis"></div>

    <div class="an-card">
        <div class="an-chart-wrap"><canvas id="an-chart"></canvas></div>
        <div class="an-empty" id="an-empty" hidden><?= tr('bud_an_empty') ?></div>
        <p class="an-hint"><?= tr('bud_an_hint') ?></p>
    </div>

    <div class="an-two">
        <div class="an-card">
            <h3><?= tr('bud_an_split') ?></h3>
            <div class="an-donut-wrap"><canvas id="an-donut"></canvas></div>
        </div>
        <div class="an-card">
            <h3><?= tr('bud_an_top') ?></h3>
            <div id="an-top"></div>
        </div>
    </div>

    <div class="an-card">
        <div class="an-tx-head">
            <h3><?= tr('bud_an_tx') ?> <span id="an-focus-tag" class="an-focus-tag" hidden></span></h3>
            <a href="#" id="an-focus-clear" class="an-mini" hidden><?= tr('bud_an_clear_focus') ?></a>
        </div>
        <div class="table-responsive"><table class="pf-table an-tx">
            <thead><tr>
                <th data-s="d"><?= tr('bud_an_date') ?></th><th data-s="l"><?= tr('bud_an_label') ?></th>
                <th data-s="c"><?= tr('bud_an_g_cat') ?></th><th data-s="a" class="an-num"><?= tr('bud_an_amount') ?></th>
            </tr></thead>
            <tbody id="an-tx-body"></tbody>
        </table></div>
        <div class="an-more"><button type="button" class="pf-btn an-btn-ghost" id="an-more" hidden><?= tr('bud_an_more') ?></button></div>
    </div>
</div>

<script src="/modules/budget/vendor/chart.umd.min.js"></script>
<script>
(function () {
    'use strict';
    var T = <?= json_encode($anT, JSON_UNESCAPED_UNICODE) ?>;
    var KEY = 'hh-budget-analyse-v1';
    var PALETTE = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#ec4899', '#84cc16', '#f97316', '#6366f1', '#14b8a6', '#a855f7'];
    var OTHERS_COLOR = '#94a3b8';
    var nf = new Intl.NumberFormat(T.locale || 'fr-FR', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 });
    var nf2 = new Intl.NumberFormat(T.locale || 'fr-FR', { style: 'currency', currency: 'EUR', minimumFractionDigits: 2, maximumFractionDigits: 2 });
    var $ = function (id) { return document.getElementById(id); };
    var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };

    var data = null, catLabel = {}, catOrder = [], itemById = {}, chart = null, donut = null, txLimit = 100, txSort = { k: 'd', dir: -1 };
    var defaults = { p: '12', from: '', to: '', basis: 'm', flow: 'exp', group: 'cat', gran: 'm', type: 'stack', cumul: false, compare: false, avg: false, savings: false, cats: null, min: '', max: '', q: '' };
    var st = Object.assign({}, defaults);
    try { Object.assign(st, JSON.parse(localStorage.getItem(KEY) || '{}')); } catch (e) { /* stockage indisponible */ }
    var focus = null; // { bucket: index | null, series: nom | null }

    var pad = function (n) { return (n < 10 ? '0' : '') + n; };
    var ym = function (d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1); };
    var parseD = function (s) { var p = s.split('-'); return new Date(+p[0], +p[1] - 1, +(p[2] || 1)); };
    var isoD = function (d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); };
    var addDays = function (d, n) { var x = new Date(d.getTime()); x.setDate(x.getDate() + n); return x; };
    var monday = function (d) { var x = new Date(d.getTime()); x.setDate(x.getDate() - ((x.getDay() + 6) % 7)); return x; };
    var monthLabel = function (k) { var p = k.split('-'); var n = T['mo' + (+p[1])]; return (n.length > 4 ? n.slice(0, 3) + '.' : n) + ' ' + p[0].slice(2); };

    function save() { try { localStorage.setItem(KEY, JSON.stringify(st)); } catch (e) { /* ignore */ } }

    // ----- Période -----
    function applyPreset(p) {
        var now = new Date(), end = ym(now), start;
        if (p === 'all') { start = data.rows.length ? (st.basis === 'm' ? data.rows[0][1] : data.rows[0][0].slice(0, 7)) : end; data.rows.forEach(function (r) { var k = st.basis === 'm' ? r[1] : r[0].slice(0, 7); if (k && k < start) start = k; }); }
        else if (p === 'year') start = now.getFullYear() + '-01';
        else start = ym(new Date(now.getFullYear(), now.getMonth() - (+p - 1), 1));
        st.p = p; st.from = start; st.to = end;
    }
    function periodBounds() {
        var from = parseD((st.from || ym(new Date())) + '-01'), to = parseD((st.to || ym(new Date())) + '-01');
        if (to < from) { var t = from; from = to; to = t; }
        return { start: from, end: new Date(to.getFullYear(), to.getMonth() + 1, 0) };
    }

    // ----- Filtrage -----
    function kindOf(r) {
        if (r[6] || r[4] > 0) return 'inc';
        var it = r[5] != null ? itemById[r[5]] : null;
        if (!it) return 'off';
        return +it.is_estimate ? 'est' : 'fixed';
    }
    var KIND_LABEL = function (k) { return { fixed: T.kind_fixed, est: T.kind_est, off: T.kind_off, inc: T.kind_inc }[k]; };
    function valueOf(r) { // montant « affichable » selon le flux ; null = ligne ignorée
        if (st.flow === 'exp') return r[4] < 0 ? -r[4] : null;
        if (st.flow === 'inc') return r[4] > 0 ? r[4] : null;
        return r[4];
    }
    function dateKey(r) { return st.basis === 'm' && r[1] ? r[1] : r[0].slice(0, 7); }
    function rowsIn(start, end) {
        var s = isoD(start), e = isoD(end), sm = s.slice(0, 7), em = e.slice(0, 7);
        var cats = st.cats ? new Set(st.cats) : null, qs = st.q.split(',').map(function (x) { return x.trim().toLowerCase(); }).filter(Boolean);
        var mn = st.min === '' ? null : +st.min, mx = st.max === '' ? null : +st.max, out = [];
        data.rows.forEach(function (r) {
            if (st.gran === 'm') { var k = dateKey(r); if (k < sm || k > em) return; } else if (r[0] < s || r[0] > e) return;
            if (!st.savings && r[2] === 'SAVINGS') return;
            if (cats && !cats.has(r[2] || '')) return;
            var v = valueOf(r); if (v === null) return;
            var av = Math.abs(r[4]);
            if (mn !== null && av < mn) return;
            if (mx !== null && av > mx) return;
            if (qs.length) { var l = r[3].toLowerCase(); if (!qs.some(function (q) { return l.indexOf(q) !== -1; })) return; }
            out.push(r);
        });
        return out;
    }

    // ----- Seaux (axe du temps) -----
    function bucketOf(r) {
        if (st.gran === 'm') return dateKey(r);
        if (st.gran === 'w') return isoD(monday(parseD(r[0])));
        return r[0];
    }
    function bucketList(start, end) {
        var out = [];
        if (st.gran === 'm') { for (var d = new Date(start.getFullYear(), start.getMonth(), 1); d <= end; d = new Date(d.getFullYear(), d.getMonth() + 1, 1)) out.push(ym(d)); }
        else if (st.gran === 'w') { for (var w = monday(start); w <= end; w = addDays(w, 7)) out.push(isoD(w)); }
        else { for (var x = start; x <= end; x = addDays(x, 1)) out.push(isoD(x)); }
        return out;
    }
    function bucketLabel(b) {
        if (st.gran === 'm') return monthLabel(b);
        var d = parseD(b);
        return pad(d.getDate()) + '/' + pad(d.getMonth() + 1) + (st.gran === 'w' ? ' →' : '');
    }
    function bucketTitle(b) {
        if (st.gran === 'm') { var p = b.split('-'); return T['mo' + (+p[1])] + ' ' + p[0]; }
        var d = parseD(b), f = function (x) { return pad(x.getDate()) + '/' + pad(x.getMonth() + 1) + '/' + x.getFullYear(); };
        return st.gran === 'w' ? f(d) + ' → ' + f(addDays(d, 6)) : f(d);
    }

    // ----- Séries -----
    function seriesName(r) {
        switch (st.group) {
            case 'cat': return r[2] ? (catLabel[r[2]] || r[2]) : T.nocat;
            case 'kind': return KIND_LABEL(kindOf(r));
            case 'item': return r[5] != null && itemById[r[5]] ? itemById[r[5]].name : T.kind_off;
            case 'label': return r[3].replace(/\s+/g, ' ').trim().slice(0, 40) || '—';
            default: return T.total;
        }
    }
    function aggregate(rows, buckets) {
        var idx = {}; buckets.forEach(function (b, i) { idx[b] = i; });
        var map = {}, totals = new Array(buckets.length).fill(0);
        rows.forEach(function (r) {
            var i = idx[bucketOf(r)]; if (i === undefined) return;
            var n = seriesName(r), v = valueOf(r);
            (map[n] = map[n] || { name: n, sum: 0, vals: new Array(buckets.length).fill(0) });
            map[n].vals[i] += v; map[n].sum += v; totals[i] += v;
        });
        var list = Object.keys(map).map(function (k) { return map[k]; }).sort(function (a, b) { return Math.abs(b.sum) - Math.abs(a.sum); });
        if (list.length > 10) { // les plus petites séries sont regroupées
            var rest = list.slice(9), o = { name: T.others, sum: 0, vals: new Array(buckets.length).fill(0), others: true };
            rest.forEach(function (s) { o.sum += s.sum; s.vals.forEach(function (v, i) { o.vals[i] += v; }); });
            list = list.slice(0, 9).concat([o]);
        }
        list.forEach(function (s, i) { s.color = s.others ? OTHERS_COLOR : PALETTE[i % PALETTE.length]; });
        return { series: list, totals: totals };
    }
    var cumulate = function (a) { var c = 0; return a.map(function (v) { c += v; return c; }); };
    var movAvg = function (a, n) { return a.map(function (_, i) { var s = a.slice(Math.max(0, i - n + 1), i + 1); return s.reduce(function (x, y) { return x + y; }, 0) / s.length; }); };

    // ----- Rendu -----
    function themeColors() {
        var cs = getComputedStyle(document.body), txt = cs.color || '#334155';
        return { text: txt, grid: 'rgba(148,163,184,.25)' };
    }
    function render() {
        save();
        syncControls();
        var b = periodBounds(), buckets = bucketList(b.start, b.end), rows = rowsIn(b.start, b.end), agg = aggregate(rows, buckets);
        var useGroup = st.group !== 'none', series = useGroup ? agg.series : [{ name: T.total, vals: agg.totals, sum: agg.totals.reduce(function (x, y) { return x + y; }, 0), color: PALETTE[0] }];
        var tc = themeColors();
        var isLine = st.type === 'line' || st.type === 'area', stacked = st.type === 'stack' || st.type === 'area';
        var datasets = series.map(function (s) {
            var v = st.cumul ? cumulate(s.vals) : s.vals;
            var base = { label: s.name, data: v.map(function (x) { return Math.round(x * 100) / 100; }), backgroundColor: s.color + (st.type === 'area' ? '66' : 'cc'), borderColor: s.color, borderWidth: isLine ? 2 : 0, _series: s.name };
            if (isLine) { base.type = 'line'; base.fill = st.type === 'area'; base.tension = .25; base.pointRadius = buckets.length > 40 ? 0 : 3; }
            else base.type = 'bar';
            base.stack = stacked ? 's' : undefined;
            return base;
        });
        var totalLine = st.cumul ? cumulate(agg.totals) : agg.totals;
        if (st.compare) {
            var len = Math.round((b.end - b.start) / 864e5) + 1, pStart = addDays(b.start, -len), pEnd = addDays(b.start, -1);
            if (st.gran === 'm') { var n = buckets.length; pStart = new Date(b.start.getFullYear(), b.start.getMonth() - n, 1); pEnd = new Date(b.start.getFullYear(), b.start.getMonth(), 0); }
            var pb = bucketList(pStart, pEnd), pa = aggregate(rowsIn(pStart, pEnd), pb).totals, pv = buckets.map(function (_, i) { return pa[i] || 0; });
            datasets.push({ type: 'line', label: T.prev, data: (st.cumul ? cumulate(pv) : pv), borderColor: '#64748b', borderDash: [6, 4], borderWidth: 2, pointRadius: 0, fill: false, tension: .25, _series: null, order: -1 });
            agg.prevSum = pv.reduce(function (x, y) { return x + y; }, 0);
        }
        if (st.avg) datasets.push({ type: 'line', label: T.trend, data: movAvg(st.cumul ? totalLine : agg.totals, 3), borderColor: '#0f172a', borderWidth: 2, borderDash: [2, 3], pointRadius: 0, fill: false, tension: .3, _series: null, order: -1 });

        $('an-empty').hidden = rows.length > 0;
        $('an-chart').parentNode.style.visibility = rows.length ? 'visible' : 'hidden';
        var cfg = {
            data: { labels: buckets.map(bucketLabel), datasets: datasets },
            options: {
                responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'bottom', labels: { color: tc.text, boxWidth: 12, usePointStyle: true } },
                    tooltip: { callbacks: { title: function (it) { return bucketTitle(buckets[it[0].dataIndex]); }, label: function (c) { return ' ' + c.dataset.label + ' : ' + nf2.format(c.parsed.y); },
                        footer: function (items) { if (!stacked || items.length < 2) return ''; var t = 0; items.forEach(function (i) { if (i.dataset._series) t += i.parsed.y; }); return T.total + ' : ' + nf2.format(t); } } }
                },
                scales: { x: { stacked: stacked, grid: { display: false }, ticks: { color: tc.text, maxRotation: 60, autoSkip: true } },
                          y: { stacked: stacked, grid: { color: tc.grid }, ticks: { color: tc.text, callback: function (v) { return nf.format(v); } } } },
                onClick: function (ev, els) {
                    if (!els.length) return;
                    var el = els[0], ds = datasets[el.datasetIndex];
                    focus = { bucket: buckets[el.index], series: useGroup ? ds._series : null };
                    renderTx();
                    $('an-focus-tag').scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        };
        if (chart) chart.destroy();
        chart = new Chart($('an-chart'), { type: 'bar', data: cfg.data, options: cfg.options });

        renderKpis(rows, buckets, agg);
        renderDonut(series, useGroup);
        renderTop(rows);
        window.__anCtx = { rows: rows, buckets: buckets };
        renderTx();
    }

    function renderKpis(rows, buckets, agg) {
        var total = agg.totals.reduce(function (x, y) { return x + y; }, 0);
        var months = {}; rows.forEach(function (r) { months[dateKey(r)] = 1; });
        var b = periodBounds(), nm = Math.max(1, (b.end.getFullYear() - b.start.getFullYear()) * 12 + b.end.getMonth() - b.start.getMonth() + 1);
        var peak = 0, peakI = -1; agg.totals.forEach(function (v, i) { if (Math.abs(v) > Math.abs(peak)) { peak = v; peakI = i; } });
        var html = '<div class="an-kpi"><span>' + esc(T.total) + '</span><b>' + nf.format(total) + '</b></div>' +
            '<div class="an-kpi"><span>' + esc(T.avg_month) + '</span><b>' + nf.format(total / nm) + '</b></div>' +
            '<div class="an-kpi"><span>' + esc(T.peak) + '</span><b>' + nf.format(peak) + '</b><small>' + (peakI >= 0 ? esc(bucketTitle(buckets[peakI])) : '') + '</small></div>' +
            '<div class="an-kpi"><span>' + esc(T.ops) + '</span><b>' + rows.length + '</b></div>';
        var prevTotal = agg.prevSum;
        if (prevTotal === undefined) { // la comparaison est toujours affichée dans la carte, même sans courbe
            var len = Math.round((b.end - b.start) / 864e5) + 1, pS = addDays(b.start, -len), pE = addDays(b.start, -1);
            if (st.gran === 'm') { pS = new Date(b.start.getFullYear(), b.start.getMonth() - nm, 1); pE = new Date(b.start.getFullYear(), b.start.getMonth(), 0); }
            prevTotal = rowsIn(pS, pE).reduce(function (s, r) { return s + valueOf(r); }, 0);
        }
        var delta = prevTotal ? (total - prevTotal) / Math.abs(prevTotal) * 100 : null;
        var bad = st.flow === 'inc' ? delta < 0 : delta > 0; // dépenses en hausse = rouge ; revenus en baisse = rouge
        html += '<div class="an-kpi"><span>' + esc(T.vs_prev) + '</span><b class="' + (delta === null ? '' : (bad ? 'an-bad' : 'an-good')) + '">' +
            (delta === null ? '—' : (delta > 0 ? '+' : '') + delta.toFixed(1).replace('.', ',') + ' %') + '</b><small>' + nf.format(prevTotal) + '</small></div>';
        $('an-kpis').innerHTML = html;
    }

    function renderDonut(series, useGroup) {
        var s = series.filter(function (x) { return x.sum > 0; });
        if (donut) donut.destroy();
        var tc = themeColors();
        donut = new Chart($('an-donut'), {
            type: 'doughnut',
            data: { labels: s.map(function (x) { return x.name; }), datasets: [{ data: s.map(function (x) { return Math.round(x.sum * 100) / 100; }), backgroundColor: s.map(function (x) { return x.color; }), borderWidth: 1 }] },
            options: { responsive: true, maintainAspectRatio: false, cutout: '58%',
                plugins: { legend: { position: window.innerWidth < 760 ? 'bottom' : 'right', labels: { color: tc.text, boxWidth: 12 } }, tooltip: { callbacks: { label: function (c) { var t = c.dataset.data.reduce(function (a, b) { return a + b; }, 0); return ' ' + c.label + ' : ' + nf2.format(c.parsed) + ' (' + Math.round(c.parsed / t * 100) + ' %)'; } } } },
                onClick: function (ev, els) { if (!els.length || !useGroup) return; focus = { bucket: null, series: s[els[0].index].name }; renderTx(); $('an-focus-tag').scrollIntoView({ behavior: 'smooth', block: 'center' }); }
            }
        });
    }

    function renderTop(rows) {
        var m = {};
        rows.forEach(function (r) { var k = r[3].replace(/\s+/g, ' ').trim().slice(0, 40) || '—', v = valueOf(r); (m[k] = m[k] || { n: 0, s: 0 }); m[k].n++; m[k].s += v; });
        var list = Object.keys(m).map(function (k) { return { k: k, n: m[k].n, s: m[k].s }; }).sort(function (a, b) { return Math.abs(b.s) - Math.abs(a.s); }).slice(0, 10);
        var max = list.length ? Math.abs(list[0].s) : 1;
        $('an-top').innerHTML = list.length ? list.map(function (x) {
            return '<div class="an-bar"><div class="an-bar-top"><span title="' + esc(x.k) + '">' + esc(x.k) + (x.n > 1 ? ' <small>×' + x.n + '</small>' : '') + '</span><b>' + nf.format(x.s) + '</b></div>' +
                '<div class="an-bar-track"><i style="width:' + Math.max(2, Math.abs(x.s) / max * 100) + '%"></i></div></div>';
        }).join('') : '<p class="an-muted">' + esc(T.empty) + '</p>';
    }

    function renderTx() {
        var ctx = window.__anCtx || { rows: [], buckets: [] }, rows = ctx.rows.slice();
        var tag = [];
        if (focus) {
            if (focus.bucket) { rows = rows.filter(function (r) { return bucketOf(r) === focus.bucket; }); tag.push(bucketTitle(focus.bucket)); }
            if (focus.series && st.group !== 'none') { rows = rows.filter(function (r) { var n = seriesName(r); return focus.series === T.others ? true : n === focus.series; }); tag.push(focus.series); }
        }
        $('an-focus-tag').hidden = !tag.length; $('an-focus-clear').hidden = !tag.length;
        $('an-focus-tag').textContent = tag.length ? T.focus + ' : ' + tag.join(' · ') : '';
        var k = txSort.k;
        rows.sort(function (a, b) {
            var x = k === 'd' ? a[0] : k === 'l' ? a[3].toLowerCase() : k === 'c' ? (catLabel[a[2]] || a[2] || '') : Math.abs(a[4]);
            var y = k === 'd' ? b[0] : k === 'l' ? b[3].toLowerCase() : k === 'c' ? (catLabel[b[2]] || b[2] || '') : Math.abs(b[4]);
            return (x < y ? -1 : x > y ? 1 : 0) * txSort.dir;
        });
        var shown = rows.slice(0, txLimit);
        $('an-tx-body').innerHTML = shown.length ? shown.map(function (r) {
            var d = r[0].split('-');
            return '<tr><td>' + d[2] + '/' + d[1] + '/' + d[0] + '</td><td>' + esc(r[3]) + '</td><td>' + esc(r[2] ? (catLabel[r[2]] || r[2]) : T.nocat) + '</td><td class="an-num ' + (r[4] < 0 ? 'an-neg' : 'an-pos') + '">' + nf2.format(r[4]) + '</td></tr>';
        }).join('') : '<tr><td colspan="4" class="an-muted">' + esc(T.empty) + '</td></tr>';
        $('an-more').hidden = rows.length <= txLimit;
    }

    // ----- Contrôles -----
    function buildCats() {
        var used = {}; data.rows.forEach(function (r) { used[r[2] || ''] = 1; });
        var codes = Object.keys(used).sort(function (a, b) { return (catLabel[a] || a || '~').localeCompare(catLabel[b] || b || '~'); });
        $('an-cats').innerHTML = codes.map(function (c) {
            var ic = (data.categories.filter(function (x) { return x.code === c; })[0] || {}).icon || '';
            return '<button type="button" data-c="' + esc(c) + '">' + esc(ic) + ' ' + esc(c ? (catLabel[c] || c) : T.nocat) + '</button>';
        }).join('');
        catOrder = codes;
    }
    function syncControls() {
        $('an-from').value = st.from; $('an-to').value = st.to; $('an-basis').value = st.basis; $('an-group').value = st.group;
        $('an-cumul').checked = st.cumul; $('an-compare').checked = st.compare; $('an-avg').checked = st.avg; $('an-savings').checked = st.savings;
        $('an-min').value = st.min; $('an-max').value = st.max; $('an-search').value = st.q;
        [['an-presets', 'p', 'p'], ['an-flow', 'flow', 'v'], ['an-gran', 'gran', 'v'], ['an-type', 'type', 'v']].forEach(function (c) {
            Array.prototype.forEach.call($(c[0]).children, function (b) { b.classList.toggle('on', b.dataset[c[2]] === st[c[1]]); });
        });
        Array.prototype.forEach.call($('an-cats').children, function (b) { b.classList.toggle('on', !st.cats || st.cats.indexOf(b.dataset.c) !== -1); b.hidden = b.dataset.c === 'SAVINGS' && !st.savings; });
    }
    function bind() {
        var upd = function () { focus = null; txLimit = 100; render(); };
        $('an-presets').addEventListener('click', function (e) { var b = e.target.closest('button'); if (!b) return; applyPreset(b.dataset.p); upd(); });
        [['an-flow', 'flow'], ['an-gran', 'gran'], ['an-type', 'type']].forEach(function (c) {
            $(c[0]).addEventListener('click', function (e) { var b = e.target.closest('button'); if (!b) return; st[c[1]] = b.dataset.v; upd(); });
        });
        ['an-from', 'an-to'].forEach(function (id) { $(id).addEventListener('change', function () { st[id === 'an-from' ? 'from' : 'to'] = this.value; st.p = ''; upd(); }); });
        $('an-basis').addEventListener('change', function () { st.basis = this.value; if (st.p) applyPreset(st.p); upd(); });
        $('an-group').addEventListener('change', function () { st.group = this.value; upd(); });
        [['an-cumul', 'cumul'], ['an-compare', 'compare'], ['an-avg', 'avg'], ['an-savings', 'savings']].forEach(function (c) { $(c[0]).addEventListener('change', function () { st[c[1]] = this.checked; upd(); }); });
        ['an-min', 'an-max'].forEach(function (id) { $(id).addEventListener('input', function () { st[id === 'an-min' ? 'min' : 'max'] = this.value; upd(); }); });
        $('an-search').addEventListener('input', function () { st.q = this.value; upd(); });
        $('an-cats').addEventListener('click', function (e) {
            var b = e.target.closest('button'); if (!b) return;
            var cur = st.cats ? st.cats.slice() : catOrder.slice(), c = b.dataset.c, i = cur.indexOf(c);
            if (st.cats === null && e.detail >= 0) cur = [c]; // premier clic : on isole la catégorie ; ensuite ajout / retrait
            else if (i === -1) cur.push(c); else cur.splice(i, 1);
            st.cats = cur.length === catOrder.length ? null : cur; upd();
        });
        $('an-cats-all').addEventListener('click', function (e) { e.preventDefault(); st.cats = null; upd(); });
        $('an-cats-none').addEventListener('click', function (e) { e.preventDefault(); st.cats = []; upd(); });
        $('an-reset').addEventListener('click', function () { st = Object.assign({}, defaults); applyPreset('12'); upd(); });
        $('an-filters-toggle').addEventListener('click', function () { $('an-panel').classList.toggle('an-open'); });
        $('an-focus-clear').addEventListener('click', function (e) { e.preventDefault(); focus = null; renderTx(); });
        $('an-more').addEventListener('click', function () { txLimit += 200; renderTx(); });
        document.querySelector('.an-tx thead').addEventListener('click', function (e) {
            var th = e.target.closest('th'); if (!th) return;
            txSort = { k: th.dataset.s, dir: txSort.k === th.dataset.s ? -txSort.dir : (th.dataset.s === 'd' || th.dataset.s === 'a' ? -1 : 1) }; renderTx();
        });
        $('an-export').addEventListener('click', function () {
            var rows = (window.__anCtx || { rows: [] }).rows, q = function (s) { return '"' + String(s).replace(/"/g, '""') + '"'; };
            var csv = '﻿' + 'date;libelle;categorie;montant\n' + rows.map(function (r) { return [r[0], q(r[3]), q(catLabel[r[2]] || r[2]), String(r[4]).replace('.', ',')].join(';'); }).join('\n');
            var a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' })); a.download = 'budget-analyse.csv'; a.click(); setTimeout(function () { URL.revokeObjectURL(a.href); }, 1000);
        });
    }

    fetch('/modules/budget/includes/api/analyse-data.php', { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (d) {
        data = d;
        d.categories.forEach(function (c) { catLabel[c.code] = c.label; });
        d.items.forEach(function (i) { itemById[i.id] = i; });
        if (!st.from || !st.to) applyPreset(st.p || '12');
        else if (st.p && st.p !== '') applyPreset(st.p); // un préréglage mémorisé est recalculé à chaque visite
        buildCats(); bind(); render();
    }).catch(function () { $('an-empty').hidden = false; });
})();
</script>
