@extends('layouts.app')
@section('title', auth()->user()->isAdmin() ? 'Leads' : 'My leads')
@section('heading', auth()->user()->isAdmin() ? 'Leads' : 'My leads')
@section('subhead', 'Search, call, log the outcome. Existing leads are unchanged.')
@section('actions')
    <button class="btn btn-primary" type="button" id="open-lead-modal">New lead</button>
@endsection
@section('content')
@php
    if (! isset($leads)) {
        $leads = \App\Models\Lead::query()->ownedBy(auth()->user())->latest()->paginate(15);
    }
    if (! isset($staff)) {
        $staff = auth()->user()->isAdmin() ? \App\Models\User::query()->where('role', 'staff')->orderBy('name')->get() : collect();
    }
    if (! isset($sources)) {
        $sources = \App\Enums\LeadSource::cases();
    }
@endphp
<style>
.lead-stats { display:grid; grid-template-columns: repeat(auto-fit, minmax(130px,1fr)); gap:10px; margin-bottom:14px; }
.lead-stat { background:#fff; border:1px solid #e6ebf2; border-radius:12px; padding:12px 14px; cursor:pointer; }
.lead-stat.on, .lead-stat:hover { border-color:#93c5fd; box-shadow:0 0 0 3px rgba(37,99,235,.12); }
.lead-stat span { display:block; color:#667085; font-size:12px; font-weight:600; }
.lead-stat strong { display:block; font-size:22px; margin-top:4px; }
.btn-call { background:#067647; color:#fff !important; }
.btn-wa { background:#128C7E; color:#fff !important; }
.out { border:1px solid #d5dce6; background:#fff; border-radius:8px; padding:5px 8px; font-size:11px; font-weight:650; cursor:pointer; }
.more-filters { display:none; flex-wrap:wrap; gap:8px; margin-top:8px; }
.more-filters.show { display:flex; }
@media (max-width:860px) {
    .table thead { display:none; }
    .table, .table tbody, .table tr, .table td { display:block; width:100%; }
    .table tr { background:#fff; border:1px solid #e6ebf2; border-radius:12px; padding:12px; margin-bottom:8px; }
    .table td { border:0; padding:4px 0; }
}
</style>
<div class="leads-page">
<div class="lead-stats" id="lead-stats">
    <article class="lead-stat on" data-stage=""><span>All</span><strong id="st-total">—</strong></article>
    <article class="lead-stat" data-stage="call"><span>Call now</span><strong id="st-call">—</strong></article>
    <article class="lead-stat" data-stage="talk"><span>In talk</span><strong id="st-talk">—</strong></article>
    <article class="lead-stat" data-stage="meeting"><span>Meetings</span><strong id="st-meet">—</strong></article>
    <article class="lead-stat" data-stage="won"><span>Won</span><strong id="st-won">—</strong></article>
    @if(auth()->user()->isAdmin())
        <article class="lead-stat" data-assigned="unassigned"><span>Unassigned</span><strong id="st-unassigned">—</strong></article>
    @endif
</div>

<section class="panel">
    <form class="filters" id="lead-filters">
        <input type="hidden" name="stage" id="filter-stage" value="">
        <input type="search" name="q" id="lead-q" placeholder="Search name or phone" autocomplete="off">
        @if(auth()->user()->isAdmin())
            <select name="assigned_to" id="filter-assigned">
                <option value="">Everyone</option>
                <option value="unassigned">Unassigned</option>
                @foreach($staff as $s)
                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                @endforeach
            </select>
        @endif
        <button class="btn btn-ghost" type="button" id="more-btn">More</button>
        <div class="more-filters" id="more-filters">
            <select name="source">
                <option value="">All sources</option>
                @foreach($sources as $src)
                    <option value="{{ $src->value }}">{{ $src->label() }}</option>
                @endforeach
            </select>
            <label class="pager-size">Per page
                <select name="per_page"><option value="15" selected>15</option><option value="25">25</option><option value="50">50</option></select>
            </label>
        </div>
    </form>

    @if(auth()->user()->isAdmin())
    <div class="bulkbar" id="bulkbar" hidden>
        <span id="bulk-count">0 selected</span>
        <select id="bulk-staff">
            <option value="">Assign to…</option>
            @foreach($staff as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
        </select>
        <button class="btn btn-primary" type="button" id="bulk-assign">Assign</button>
    </div>
    @endif

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    @if(auth()->user()->isAdmin())<th class="col-check"><input type="checkbox" id="check-all"></th>@endif
                    <th>Lead</th>
                    <th>Phone</th>
                    <th>Stage</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="lead-rows"></tbody>
        </table>
    </div>
    <div class="pager" id="pager"></div>
</section>

<div class="modal" id="lead-modal" style="display:none" onclick="if(event.target===this) this.style.display='none'">
    <div class="modal-card" onclick="event.stopPropagation()">
        <header class="panel-h"><h2>New lead</h2></header>
        <form id="lead-form" class="stack" novalidate>
            <label>Name <input name="name" required></label>
            <label>Phone <input name="phone"></label>
            <label>City <input name="city"></label>
            <label>Notes <textarea name="notes" rows="2"></textarea></label>
            @if(auth()->user()->isAdmin())
                <label>Assign to
                    <select name="assigned_to">
                        <option value="">Unassigned</option>
                        @foreach($staff as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                    </select>
                </label>
            @endif
            <div style="display:flex;gap:8px">
                <button class="btn btn-primary" type="submit" id="lead-save">Save</button>
                <button class="btn btn-ghost" type="button" onclick="document.getElementById('lead-modal').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>
</div>
</div>
@endsection
@push('scripts')
<script>
GymLead.ready(function () {
    var isAdmin = {{ auth()->user()->isAdmin() ? 'true' : 'false' }};
    var page = 1, selected = {}, timer = null;
    var tableUrl = @json(route('leads.datatable'));
    var storeUrl = @json(route('leads.store'));
    var leadsBase = @json(url('/leads'));

    function params() {
        var fd = new FormData(document.getElementById('lead-filters'));
        var q = new URLSearchParams();
        fd.forEach(function (v, k) { if (v) q.set(k, v); });
        q.set('page', page);
        return q.toString();
    }
    function load() {
        GymLead.get(tableUrl + '?' + params()).then(function (res) {
            if (!res || res.ok === false) {
                if (window.GymLead && GymLead.toast) GymLead.toast(res && res.message ? res.message : 'Could not refresh list', 'err');
                return;
            }
            if (!res.data) return;
            var s = res.stats || {};
            document.getElementById('st-total').textContent = s.total;
            document.getElementById('st-call').textContent = s.call_now;
            document.getElementById('st-talk').textContent = s.talk;
            document.getElementById('st-meet').textContent = s.meeting;
            document.getElementById('st-won').textContent = s.won;
            var u = document.getElementById('st-unassigned');
            if (u) u.textContent = s.unassigned;
            var tb = document.getElementById('lead-rows');
            if (!res.data.length) {
                tb.innerHTML = '<tr><td colspan="5"><div class="empty">Nothing in this list. Try All, or add a lead.</div></td></tr>';
            } else {
                tb.innerHTML = res.data.map(function (l) {
                    var tel = l.tel ? '<a class="btn btn-sm btn-call" href="' + l.tel + '">Call</a>' : '';
                    var wa = l.wa ? '<a class="btn btn-sm btn-wa" href="' + l.wa + '" target="_blank" rel="noopener">WA</a>' : '';
                    var check = isAdmin ? '<td class="col-check"><input type="checkbox" class="row-check" value="' + l.id + '"></td>' : '';
                    return '<tr>' + check +
                        '<td><a href="' + l.url + '"><strong>' + GymLead.esc(l.name) + '</strong></a><div class="sub">' + GymLead.esc(l.city || '') + '</div></td>' +
                        '<td>' + GymLead.esc(l.phone || '—') + '</td>' +
                        '<td><span class="badge ' + l.badge + '">' + GymLead.esc(l.status_label) + '</span></td>' +
                        '<td><div class="act-btns">' + tel + wa +
                        '<button type="button" class="out" data-id="' + l.id + '" data-o="no_answer">No answer</button>' +
                        '<button type="button" class="out" data-id="' + l.id + '" data-o="callback">Call back</button>' +
                        '<button type="button" class="out" data-id="' + l.id + '" data-o="won">Won</button>' +
                        '<button type="button" class="out" data-id="' + l.id + '" data-o="lost">Lost</button></div></td></tr>';
                }).join('');
            }
            GymLead.pager('pager', res.meta, function (p) { page = p; load(); });
            document.querySelectorAll('.row-check').forEach(function (c) {
                c.addEventListener('change', function () {
                    selected[c.value] = c.checked;
                    if (!c.checked) delete selected[c.value];
                    var n = Object.keys(selected).filter(function (k) { return selected[k]; }).length;
                    var bar = document.getElementById('bulkbar');
                    if (bar) { bar.hidden = n === 0; document.getElementById('bulk-count').textContent = n + ' selected'; }
                });
            });
        });
    }
    document.getElementById('lead-stats').addEventListener('click', function (e) {
        var card = e.target.closest('.lead-stat');
        if (!card) return;
        document.querySelectorAll('.lead-stat').forEach(function (c) { c.classList.remove('on'); });
        card.classList.add('on');
        var stage = card.getAttribute('data-stage') || '';
        document.getElementById('filter-stage').value = stage === 'call' ? '' : stage;
        var as = document.getElementById('filter-assigned');
        if (as) as.value = card.getAttribute('data-assigned') || '';
        if (stage === 'call') {
            var hid = document.getElementById('filter-stage');
            hid.name = 'queue';
            hid.value = 'call';
        } else {
            var hid = document.getElementById('filter-stage');
            hid.name = 'stage';
            hid.value = stage;
        }
        page = 1; load();
    });
    document.getElementById('lead-q').addEventListener('input', function () {
        clearTimeout(timer); timer = setTimeout(function () { page = 1; load(); }, 280);
    });
    document.getElementById('lead-filters').addEventListener('change', function () { page = 1; load(); });
    document.getElementById('more-btn').addEventListener('click', function () {
        document.getElementById('more-filters').classList.toggle('show');
    });
    document.getElementById('lead-rows').addEventListener('click', function (e) {
        var b = e.target.closest('[data-o]');
        if (!b) return;
        GymLead.post(leadsBase + '/' + b.getAttribute('data-id') + '/outcome', { outcome: b.getAttribute('data-o') }).then(function (res) {
            if (!res.ok) return;
            GymLead.toast(res.message);
            load();
        });
    });
    var assignBtn = document.getElementById('bulk-assign');
    if (assignBtn) assignBtn.addEventListener('click', function () {
        var staff = document.getElementById('bulk-staff').value;
        if (!staff) return GymLead.toast('Pick a staff member.', 'err');
        var ids = Object.keys(selected).filter(function (k) { return selected[k]; });
        GymLead.post(@json(route('leads.bulk.assign')), { ids: ids, assigned_to: staff }).then(function (res) {
            if (!res.ok) return;
            GymLead.toast(res.message); selected = {}; load();
        });
    });
    var checkAll = document.getElementById('check-all');
    if (checkAll) checkAll.addEventListener('change', function () {
        document.querySelectorAll('.row-check').forEach(function (c) {
            c.checked = checkAll.checked;
            if (c.checked) selected[c.value] = true; else delete selected[c.value];
        });
        var n = Object.keys(selected).filter(function (k) { return selected[k]; }).length;
        var bar = document.getElementById('bulkbar');
        if (bar) { bar.hidden = n === 0; document.getElementById('bulk-count').textContent = n + ' selected'; }
    });
    document.getElementById('open-lead-modal').addEventListener('click', function () {
        document.getElementById('lead-form').reset();
        document.getElementById('lead-modal').style.display = 'grid';
    });
    document.getElementById('lead-form').addEventListener('submit', function (e) {
        e.preventDefault();
        GymLead.post(storeUrl, GymLead.formData(this)).then(function (res) {
            if (!res.ok) return;
            GymLead.toast(res.message);
            document.getElementById('lead-modal').style.display = 'none';
            load();
        });
    });
    if (new URLSearchParams(location.search).get('new') === '1') {
        document.getElementById('lead-modal').style.display = 'grid';
    }
    load();
});
</script>
@endpush
