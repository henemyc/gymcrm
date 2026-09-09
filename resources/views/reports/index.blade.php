@extends('layouts.app')
@section('title', 'Reports')
@section('heading', 'Reports')
@section('subhead', 'Staff performance, conversions, meetings, and pipeline mix.')
@section('actions')
    <div class="seg" id="range-seg">
        <button type="button" data-range="7">7 days</button>
        <button type="button" class="on" data-range="30">30 days</button>
        <button type="button" data-range="90">90 days</button>
        <button type="button" data-range="all">All time</button>
    </div>
@endsection
@section('content')
<style>
.rpt-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; margin-bottom: 16px; }
.rpt-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.chart-box { position: relative; height: 280px; }
.rpt-top { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border: 1px solid #abefc6; background: #ecfdf3; border-radius: 12px; margin-bottom: 16px; }
.rpt-top strong { display: block; font-size: 16px; }
.medal { font-size: 16px; }
@media (max-width: 860px) {
    .rpt-grid { grid-template-columns: 1fr; }
    .chart-box { height: 240px; }
}
</style>

@php $k = $boot['kpis'] ?? []; $top = $boot['top'] ?? null; @endphp

@if(!empty($boot['is_admin']) && $top)
<div id="rpt-top" class="rpt-top">
    <span class="avatar teal">{{ strtoupper(substr($top['name'] ?? '?', 0, 1)) }}</span>
    <div>
        <span class="muted">Top performer</span>
        <strong>{{ $top['name'] }}</strong>
        <div class="sub">{{ $top['converted'] }} converted · {{ $top['meetings'] }} meetings · {{ $top['conversion_rate'] }}% rate</div>
    </div>
</div>
@else
<div id="rpt-top" class="rpt-top" hidden></div>
@endif

<div class="rpt-kpis" id="rpt-kpis">
    <article class="stat"><div class="stat-ico"><svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M8 6h13M8 12h13M8 18h13"/><circle cx="4" cy="6" r="1.2"/><circle cx="4" cy="12" r="1.2"/><circle cx="4" cy="18" r="1.2"/></svg></div><div><span>Leads</span><strong id="k-leads">{{ $k['leads'] ?? 0 }}</strong></div></article>
    <article class="stat"><div class="stat-ico ok"><svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m5 13 4 4L19 7"/></svg></div><div><span>Converted</span><strong id="k-converted">{{ $k['converted'] ?? 0 }}</strong></div></article>
    <article class="stat"><div class="stat-ico"><svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="5" width="16" height="15" rx="2"/><path d="M8 3v4M16 3v4M4 10h16"/></svg></div><div><span>Meetings</span><strong id="k-meetings">{{ $k['meetings'] ?? 0 }}</strong></div></article>
    <article class="stat"><div class="stat-ico warn"><svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19V5l8 4 8-4v14"/></svg></div><div><span>Conversion rate</span><strong id="k-rate">{{ $k['conversion_rate'] ?? 0 }}%</strong></div></article>
    <article class="stat"><div class="stat-ico"><svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 8v4l3 2"/><circle cx="12" cy="12" r="9"/></svg></div><div><span>Interested</span><strong id="k-interested">{{ $k['interested'] ?? 0 }}</strong></div></article>
    <article class="stat"><div class="stat-ico slate"><svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 8v8M8 12h8"/><circle cx="12" cy="12" r="9"/></svg></div><div><span>Follow-ups due</span><strong id="k-follow">{{ $k['follow_ups_due'] ?? 0 }}</strong></div></article>
</div>

<div class="rpt-grid">
    <section class="panel"><header class="panel-h"><h2>Conversions by staff</h2></header><div class="chart-box"><canvas id="ch-converted"></canvas></div></section>
    <section class="panel"><header class="panel-h"><h2>Meetings booked</h2></header><div class="chart-box"><canvas id="ch-meetings"></canvas></div></section>
    <section class="panel"><header class="panel-h"><h2>Leads owned</h2></header><div class="chart-box"><canvas id="ch-leads"></canvas></div></section>
    <section class="panel"><header class="panel-h"><h2>Conversion rate</h2></header><div class="chart-box"><canvas id="ch-rate"></canvas></div></section>
    <section class="panel"><header class="panel-h"><h2>Pipeline mix</h2></header><div class="chart-box"><canvas id="ch-pipeline"></canvas></div></section>
    <section class="panel"><header class="panel-h"><h2>Lead sources</h2></header><div class="chart-box"><canvas id="ch-source"></canvas></div></section>
</div>

<section class="panel">
    <header class="panel-h"><h2>Activity over time</h2></header>
    <div class="chart-box" style="height:300px"><canvas id="ch-trend"></canvas></div>
</section>

<section class="panel">
    <header class="panel-h"><h2>Staff leaderboard</h2><small>Score = converted × 5 + meetings × 3 + interested × 2 + contacted</small></header>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th><th>Staff</th><th>Leads</th><th>Converted</th><th>Rate</th>
                    <th>Meetings</th><th>Interested</th><th>Follow-ups due</th><th>Score</th>
                </tr>
            </thead>
            <tbody id="rpt-rows">
                @forelse(($boot['rows'] ?? []) as $r)
                    <tr>
                        <td>{{ $r['rank'] ?? '—' }}</td>
                        <td>
                            <div class="entity">
                                <span class="avatar sm">{{ strtoupper(substr($r['name'] ?? '?', 0, 1)) }}</span>
                                <strong>
                                    @if(($r['rank'] ?? 99) === 1)🥇 @elseif(($r['rank'] ?? 99) === 2)🥈 @elseif(($r['rank'] ?? 99) === 3)🥉 @endif
                                    {{ $r['name'] }}
                                </strong>
                            </div>
                        </td>
                        <td>{{ $r['leads'] }}</td>
                        <td><strong>{{ $r['converted'] }}</strong></td>
                        <td>{{ $r['conversion_rate'] }}%</td>
                        <td>{{ $r['meetings'] }}</td>
                        <td>{{ $r['interested'] }}</td>
                        <td>{{ $r['follow_ups_due'] }}</td>
                        <td><strong>{{ $r['score'] }}</strong></td>
                    </tr>
                @empty
                    <tr><td colspan="9"><div class="empty">No data in this range.</div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
@push('scripts')
<script>
window.REPORT_BOOT = @json($boot);
window.REPORT_DATA_URL = @json(route('reports.data'));
(function () {
    var charts = {};
    var palette = ['#1d4ed8', '#12b76a', '#f79009', '#7a5af8', '#ef4444', '#0ea5e9', '#64748b', '#d946ef', '#054f31'];
    var range = '30';
    var cdns = [
        'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js',
        'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
        'https://unpkg.com/chart.js@4.4.1/dist/chart.umd.min.js',
        'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js'
    ];

    function ready(fn) {
        if (window.GymLead && GymLead.ready) return GymLead.ready(fn);
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);
        else fn();
    }
    function loadScript(src) {
        return new Promise(function (resolve, reject) {
            var s = document.createElement('script');
            s.src = src;
            s.async = true;
            s.onload = function () { resolve(true); };
            s.onerror = function () { reject(new Error(src)); };
            document.head.appendChild(s);
        });
    }
    function ensureChart() {
        if (typeof window.Chart === 'function') return Promise.resolve(true);
        var i = 0;
        function next() {
            if (typeof window.Chart === 'function') return Promise.resolve(true);
            if (i >= cdns.length) return Promise.resolve(false);
            return loadScript(cdns[i++]).then(function () {
                return typeof window.Chart === 'function' ? true : next();
            }).catch(function () { return next(); });
        }
        return next();
    }
    function destroyChart(id) {
        if (charts[id]) { charts[id].destroy(); delete charts[id]; }
    }
    function makeChart(id, config) {
        destroyChart(id);
        var el = document.getElementById(id);
        if (!el || typeof window.Chart !== 'function') return;
        charts[id] = new window.Chart(el, config);
    }
    function barOpts() {
        return {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false }, ticks: { color: '#667085', font: { size: 11 } } },
                y: { beginAtZero: true, ticks: { precision: 0, color: '#667085' }, grid: { color: '#eef2f6' } }
            }
        };
    }
    function esc(s) {
        return window.GymLead && GymLead.esc ? GymLead.esc(s) : String(s == null ? '' : s);
    }
    function paintKpis(res) {
        var k = res.kpis || {};
        var set = function (id, v) { var el = document.getElementById(id); if (el) el.textContent = v; };
        set('k-leads', k.leads);
        set('k-converted', k.converted);
        set('k-meetings', k.meetings);
        set('k-rate', (k.conversion_rate == null ? '0' : k.conversion_rate) + '%');
        set('k-interested', k.interested);
        set('k-follow', k.follow_ups_due);
        var top = document.getElementById('rpt-top');
        if (top && res.top && res.is_admin) {
            top.hidden = false;
            top.innerHTML = '<span class="avatar teal">' + esc((res.top.name || '?').charAt(0).toUpperCase()) + '</span><div><span class="muted">Top performer</span><strong>' + esc(res.top.name) + '</strong><div class="sub">' + res.top.converted + ' converted · ' + res.top.meetings + ' meetings · ' + res.top.conversion_rate + '% rate</div></div>';
        }
        var body = document.getElementById('rpt-rows');
        var list = res.rows || [];
        var medals = ['🥇', '🥈', '🥉'];
        if (!body) return;
        if (!list.length) {
            body.innerHTML = '<tr><td colspan="9"><div class="empty">No data in this range.</div></td></tr>';
            return;
        }
        body.innerHTML = list.map(function (r) {
            var badge = r.rank && r.rank <= 3 ? medals[r.rank - 1] + ' ' : '';
            var rank = r.rank == null ? '—' : r.rank;
            return '<tr><td>' + rank + '</td><td><div class="entity"><span class="avatar sm">' + esc((r.name || '?').charAt(0).toUpperCase()) + '</span><strong>' + badge + esc(r.name) + '</strong></div></td><td>' + r.leads + '</td><td><strong>' + r.converted + '</strong></td><td>' + r.conversion_rate + '%</td><td>' + r.meetings + '</td><td>' + r.interested + '</td><td>' + r.follow_ups_due + '</td><td><strong>' + r.score + '</strong></td></tr>';
        }).join('');
    }
    function paintCharts(res) {
        if (typeof window.Chart !== 'function') return;
        var staff = res.staff || [];
        var names = staff.map(function (s) { return s.name; });
        makeChart('ch-converted', { type: 'bar', data: { labels: names, datasets: [{ data: staff.map(function (s) { return s.converted; }), backgroundColor: '#12b76a', borderRadius: 6, barPercentage: .65 }] }, options: barOpts() });
        makeChart('ch-meetings', { type: 'bar', data: { labels: names, datasets: [{ data: staff.map(function (s) { return s.meetings; }), backgroundColor: '#1d4ed8', borderRadius: 6, barPercentage: .65 }] }, options: barOpts() });
        makeChart('ch-leads', { type: 'bar', data: { labels: names, datasets: [{ data: staff.map(function (s) { return s.leads; }), backgroundColor: '#7a5af8', borderRadius: 6, barPercentage: .65 }] }, options: barOpts() });
        var rateOpts = barOpts();
        rateOpts.scales.y = { beginAtZero: true, max: 100, ticks: { callback: function (v) { return v + '%'; }, color: '#667085' }, grid: { color: '#eef2f6' } };
        makeChart('ch-rate', { type: 'bar', data: { labels: names, datasets: [{ data: staff.map(function (s) { return s.conversion_rate; }), backgroundColor: '#f79009', borderRadius: 6, barPercentage: .65 }] }, options: rateOpts });
        var pipe = (res.pipeline || []).filter(function (p) { return p.count > 0; });
        if (!pipe.length) pipe = res.pipeline || [];
        makeChart('ch-pipeline', { type: 'doughnut', data: { labels: pipe.map(function (p) { return p.label; }), datasets: [{ data: pipe.map(function (p) { return p.count; }), backgroundColor: palette, borderWidth: 0 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right', labels: { boxWidth: 12, font: { size: 11 } } } } } });
        var src = res.sources || [];
        makeChart('ch-source', { type: 'pie', data: { labels: src.map(function (p) { return p.label; }), datasets: [{ data: src.map(function (p) { return p.count; }), backgroundColor: ['#1d4ed8', '#12b76a', '#f79009'], borderWidth: 0 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right', labels: { boxWidth: 12, font: { size: 11 } } } } } });
        var t = res.trend || { labels: [], new: [], converted: [], meetings: [] };
        makeChart('ch-trend', {
            type: 'line',
            data: {
                labels: t.labels,
                datasets: [
                    { label: 'New leads', data: t.new, borderColor: '#7a5af8', backgroundColor: 'rgba(122,90,248,.12)', fill: true, tension: .35, pointRadius: 0, borderWidth: 2 },
                    { label: 'Converted', data: t.converted, borderColor: '#12b76a', backgroundColor: 'rgba(18,183,106,.12)', fill: true, tension: .35, pointRadius: 0, borderWidth: 2 },
                    { label: 'Meetings', data: t.meetings, borderColor: '#1d4ed8', backgroundColor: 'rgba(29,78,216,.08)', fill: true, tension: .35, pointRadius: 0, borderWidth: 2 }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { position: 'top', labels: { boxWidth: 12, font: { size: 12 } } } },
                scales: {
                    x: { grid: { display: false }, ticks: { maxTicksLimit: 10, color: '#667085', font: { size: 11 } } },
                    y: { beginAtZero: true, ticks: { precision: 0, color: '#667085' }, grid: { color: '#eef2f6' } }
                }
            }
        });
    }
    function apply(res) {
        if (!res) return;
        paintKpis(res);
        paintCharts(res);
    }
    function fetchRange() {
        var url = window.REPORT_DATA_URL + '?range=' + encodeURIComponent(range);
        var get = window.GymLead && GymLead.get
            ? GymLead.get(url)
            : fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' }).then(function (r) { return r.json(); });
        get.then(function (res) {
            if (!res || res.ok === false) return;
            apply(res);
        }).catch(function () {});
    }

    ready(function () {
        apply(window.REPORT_BOOT || {});
        ensureChart().then(function (ok) {
            if (ok) paintCharts(window.REPORT_BOOT || {});
        });
        var seg = document.getElementById('range-seg');
        if (seg) seg.addEventListener('click', function (e) {
            var b = e.target.closest('button');
            if (!b) return;
            range = b.getAttribute('data-range') || '30';
            document.querySelectorAll('#range-seg button').forEach(function (x) { x.classList.toggle('on', x === b); });
            fetchRange();
            ensureChart().then(function () { fetchRange(); });
        });
    });
})();
</script>
@endpush
