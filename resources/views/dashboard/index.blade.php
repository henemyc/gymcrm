@php
    $user = auth()->user();
    $isAdmin = $user->isAdmin();
    $screen = $screen ?? (request()->routeIs('reports.*') ? 'reports' : ((request()->routeIs('leads.index') || request()->routeIs('leads.create')) ? 'leads' : 'today'));
    $boot = $boot ?? ['kpis' => [], 'rows' => [], 'pipeline' => [], 'staff' => []];
    $k = $boot['kpis'] ?? [];
    $q = trim((string) request('q', ''));
    $stage = (string) request('stage', '');
    $assigned = (string) request('assigned_to', '');
    $staffList = $isAdmin ? \App\Models\User::query()->where('role', 'staff')->where('is_active', true)->orderBy('name')->get() : collect();

    $phoneLinks = function ($phone) {
        $d = preg_replace('/\D+/', '', (string) $phone);
        if (strlen((string) $d) === 10) {
            $d = '91'.$d;
        }
        $ok = strlen((string) $d) >= 10;
        return [
            'tel' => $ok ? 'tel:+'.$d : null,
            'wa' => $ok ? 'https://wa.me/'.$d : null,
        ];
    };
    $stageMeta = function ($status) {
        return match ($status) {
            'new' => ['New', 'badge-new'],
            'meeting_scheduled' => ['Meeting', 'badge-meeting'],
            'converted' => ['Won', 'badge-converted'],
            'not_interested', 'invalid' => ['Lost', 'badge-not'],
            'duplicate' => ['Duplicate', 'badge-duplicate'],
            default => ['In talk', 'badge-contacted'],
        };
    };
@endphp
@extends('layouts.app')
@section('title', $screen === 'reports' ? 'Reports' : ($screen === 'leads' ? 'Leads' : 'Today'))
@section('heading', $screen === 'reports' ? 'Reports' : ($screen === 'leads' ? ($isAdmin ? 'All leads' : 'My leads') : 'Today'))
@section('subhead', $screen === 'reports' ? 'Performance across the team.' : ($screen === 'leads' ? 'Search, filter, call, assign, and update pipeline.' : 'Your work queue for this shift.'))
@section('actions')
    @if($screen === 'leads')
        <button class="btn btn-primary" type="button" id="open-add">New lead</button>
    @else
        <a class="btn btn-ghost" href="{{ route('leads.index') }}">All leads</a>
        <button class="btn btn-primary" type="button" onclick="location.href='{{ route('leads.index') }}#new-lead'">New lead</button>
    @endif
@endsection
@section('content')
@if(session('success'))
    <div class="alert">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="alert err">{{ $errors->first() }}</div>
@endif
<style>
.chip-row { display:flex; flex-wrap:wrap; gap:6px; margin-bottom:12px; }
.chip-row a, .chip-row button { border:1px solid #d5dce6; background:#fff; border-radius:999px; padding:6px 12px; font-weight:650; font-size:12px; color:#0f172a; }
.chip-row a.on, .chip-row a:hover { background:#1d4ed8; color:#fff; border-color:#1d4ed8; }
.btn-call { background:#067647 !important; color:#fff !important; }
.btn-wa { background:#128C7E !important; color:#fff !important; }
.out { border:1px solid #d5dce6; background:#fff; border-radius:8px; padding:6px 10px; font-size:12px; font-weight:650; cursor:pointer; }
.mini-bar { height:8px; background:#eef2f6; border-radius:99px; overflow:hidden; }
.mini-bar i { display:block; height:100%; background:#1d4ed8; }
.lead-actions { display:flex; flex-wrap:wrap; gap:6px; }
</style>

@if($screen === 'reports')
    @php
        $pipe = $boot['pipeline'] ?? [];
        $maxP = 1;
        foreach ($pipe as $p) { $maxP = max($maxP, (int) ($p['count'] ?? 0)); }
        $staffRows = $boot['staff'] ?? $boot['rows'] ?? [];
        $maxC = 1;
        foreach ($staffRows as $r) { $maxC = max($maxC, (int) ($r['converted'] ?? 0)); }
    @endphp
    <div class="stat-grid">
        <article class="stat"><div><span>Leads in range</span><strong>{{ $k['leads'] ?? 0 }}</strong></div></article>
        <article class="stat"><div><span>Converted</span><strong>{{ $k['converted'] ?? 0 }}</strong></div></article>
        <article class="stat"><div><span>Meetings</span><strong>{{ $k['meetings'] ?? 0 }}</strong></div></article>
        <article class="stat"><div><span>Conversion rate</span><strong>{{ $k['conversion_rate'] ?? 0 }}%</strong></div></article>
        <article class="stat"><div><span>Interested</span><strong>{{ $k['interested'] ?? 0 }}</strong></div></article>
    </div>
    <div class="chip-row">
        <a href="{{ route('reports.index', ['range' => '7']) }}" class="{{ request('range') === '7' ? 'on' : '' }}">7 days</a>
        <a href="{{ route('reports.index', ['range' => '30']) }}" class="{{ request('range', '30') === '30' ? 'on' : '' }}">30 days</a>
        <a href="{{ route('reports.index', ['range' => '90']) }}" class="{{ request('range') === '90' ? 'on' : '' }}">90 days</a>
        <a href="{{ route('reports.index', ['range' => 'all']) }}" class="{{ request('range') === 'all' ? 'on' : '' }}">All time</a>
    </div>
    <div class="grid-2">
        <section class="panel">
            <header class="panel-h"><h2>Pipeline mix</h2></header>
            @foreach($pipe as $p)
                <div class="pipe" style="margin-bottom:10px">
                    <div class="pipe-meta"><span>{{ $p['label'] }}</span><b>{{ $p['count'] }}</b></div>
                    <div class="mini-bar"><i style="width:{{ $maxP ? round(($p['count'] / $maxP) * 100) : 0 }}%"></i></div>
                </div>
            @endforeach
        </section>
        <section class="panel">
            <header class="panel-h"><h2>Conversions by staff</h2></header>
            @forelse($staffRows as $r)
                @if(($r['id'] ?? 1) !== 0)
                <div class="pipe" style="margin-bottom:10px">
                    <div class="pipe-meta"><span>{{ $r['name'] }}</span><b>{{ $r['converted'] }} won</b></div>
                    <div class="mini-bar"><i style="width:{{ $maxC ? round(($r['converted'] / $maxC) * 100) : 0 }}%;background:#12b76a"></i></div>
                </div>
                @endif
            @empty
                <p class="muted">No staff data.</p>
            @endforelse
        </section>
    </div>
    <section class="panel">
        <header class="panel-h"><h2>Leaderboard</h2><small>Score = converted × 5 + meetings × 3 + interested × 2 + contacted</small></header>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>#</th><th>Staff</th><th>Leads</th><th>Converted</th><th>Rate</th><th>Meetings</th><th>Interested</th><th>Score</th></tr></thead>
                <tbody>
                @foreach(($boot['rows'] ?? []) as $r)
                    <tr>
                        <td>{{ $r['rank'] ?? '—' }}</td>
                        <td><strong>{{ $r['name'] }}</strong></td>
                        <td>{{ $r['leads'] }}</td>
                        <td>{{ $r['converted'] }}</td>
                        <td>{{ $r['conversion_rate'] }}%</td>
                        <td>{{ $r['meetings'] }}</td>
                        <td>{{ $r['interested'] }}</td>
                        <td><strong>{{ $r['score'] }}</strong></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>

@elseif($screen === 'leads')
    @php
        $map = [
            'new' => ['new'],
            'talk' => ['contacted', 'follow_up', 'interested'],
            'meeting' => ['meeting_scheduled'],
            'won' => ['converted'],
            'lost' => ['not_interested', 'invalid'],
        ];
        $list = \App\Models\Lead::query()->with('assignee')->ownedBy($user);
        if ($q !== '') {
            $list->where(function ($qq) use ($q) {
                $qq->where('name', 'like', '%'.$q.'%')->orWhere('phone', 'like', '%'.$q.'%')->orWhere('city', 'like', '%'.$q.'%')->orWhere('email', 'like', '%'.$q.'%');
            });
        }
        if ($stage && isset($map[$stage])) {
            $list->whereIn('status', $map[$stage]);
        }
        if ($isAdmin && $assigned === 'unassigned') {
            $list->whereNull('assigned_to');
        } elseif ($isAdmin && $assigned !== '') {
            $list->where('assigned_to', (int) $assigned);
        }
        $leadList = $list->latest()->paginate(25)->withQueryString();
        $scope = \App\Models\Lead::query()->ownedBy($user);
        $counts = [
            'all' => (clone $scope)->count(),
            'new' => (clone $scope)->where('status', 'new')->count(),
            'talk' => (clone $scope)->whereIn('status', $map['talk'])->count(),
            'meeting' => (clone $scope)->where('status', 'meeting_scheduled')->count(),
            'won' => (clone $scope)->where('status', 'converted')->count(),
            'lost' => (clone $scope)->whereIn('status', $map['lost'])->count(),
            'unassigned' => $isAdmin ? \App\Models\Lead::query()->whereNull('assigned_to')->count() : 0,
        ];
    @endphp

    <div class="stat-grid">
        <article class="stat"><div><span>Total</span><strong>{{ $counts['all'] }}</strong></div></article>
        <article class="stat"><div><span>New</span><strong>{{ $counts['new'] }}</strong></div></article>
        <article class="stat"><div><span>In talk</span><strong>{{ $counts['talk'] }}</strong></div></article>
        <article class="stat"><div><span>Meetings</span><strong>{{ $counts['meeting'] }}</strong></div></article>
        <article class="stat"><div><span>Won</span><strong>{{ $counts['won'] }}</strong></div></article>
        @if($isAdmin)<article class="stat"><div><span>Unassigned</span><strong>{{ $counts['unassigned'] }}</strong></div></article>@endif
    </div>

    <form method="GET" action="{{ route('leads.index') }}" class="filters" id="lead-filters">
        <input type="search" name="q" id="live-q" value="{{ $q }}" placeholder="Live search name, phone, city…" autocomplete="off">
        <select name="stage">
            <option value="">All stages</option>
            <option value="new" @selected($stage==='new')>New</option>
            <option value="talk" @selected($stage==='talk')>In talk</option>
            <option value="meeting" @selected($stage==='meeting')>Meeting</option>
            <option value="won" @selected($stage==='won')>Won</option>
            <option value="lost" @selected($stage==='lost')>Lost</option>
        </select>
        @if($isAdmin)
            <select name="assigned_to">
                <option value="">All owners</option>
                <option value="unassigned" @selected($assigned==='unassigned')>Unassigned</option>
                @foreach($staffList as $s)
                    <option value="{{ $s->id }}" @selected($assigned == (string) $s->id)>{{ $s->name }}</option>
                @endforeach
            </select>
        @endif
        <button class="btn btn-primary" type="submit">Apply</button>
        <a class="btn btn-ghost" href="{{ route('leads.index') }}">Reset</a>
        @if($isAdmin)
            <a class="btn btn-ghost" href="{{ route('leads.export') }}">Export CSV</a>
        @endif
    </form>

    <div class="chip-row">
        <a class="{{ $stage==='' && $assigned==='' ? 'on' : '' }}" href="{{ route('leads.index') }}">All {{ $counts['all'] }}</a>
        <a class="{{ $stage==='new' ? 'on' : '' }}" href="{{ route('leads.index', ['stage'=>'new']) }}">New {{ $counts['new'] }}</a>
        <a class="{{ $stage==='talk' ? 'on' : '' }}" href="{{ route('leads.index', ['stage'=>'talk']) }}">In talk {{ $counts['talk'] }}</a>
        <a class="{{ $stage==='meeting' ? 'on' : '' }}" href="{{ route('leads.index', ['stage'=>'meeting']) }}">Meetings {{ $counts['meeting'] }}</a>
        <a class="{{ $stage==='won' ? 'on' : '' }}" href="{{ route('leads.index', ['stage'=>'won']) }}">Won {{ $counts['won'] }}</a>
        @if($isAdmin)
            <a class="{{ $assigned==='unassigned' ? 'on' : '' }}" href="{{ route('leads.index', ['assigned_to'=>'unassigned']) }}">Unassigned {{ $counts['unassigned'] }}</a>
        @endif
    </div>

    <section class="panel">
        <header class="panel-h"><h2>{{ $leadList->total() }} matching</h2></header>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        @if($isAdmin)<th class="col-check"><input type="checkbox" id="check-all"></th>@endif
                        <th>Lead</th>
                        <th>Contact</th>
                        <th>Stage</th>
                        @if($isAdmin)<th>Owner</th>@endif
                        <th>Follow-up</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="lead-rows">
                @forelse($leadList as $lead)
                    @php
                        $p = $phoneLinks($lead->phone);
                        $sm = $stageMeta($lead->status);
                    @endphp
                    <tr data-id="{{ $lead->id }}">
                        @if($isAdmin)<td class="col-check"><input type="checkbox" class="row-check" value="{{ $lead->id }}"></td>@endif
                        <td>
                            <a href="{{ route('leads.show', $lead) }}"><strong>{{ $lead->name }}</strong></a>
                            <div class="sub">{{ $lead->city }} {{ $lead->category }}</div>
                        </td>
                        <td>
                            {{ $lead->phone ?: '—' }}
                            @if($lead->email)<div class="sub">{{ $lead->email }}</div>@endif
                        </td>
                        <td><span class="badge {{ $sm[1] }}">{{ $sm[0] }}</span></td>
                        @if($isAdmin)<td>{{ $lead->assignee->name ?? 'Unassigned' }}</td>@endif
                        <td class="muted">{{ optional($lead->follow_up_at)->format('d M Y') ?: '—' }}</td>
                        <td>
                            <div class="lead-actions">
                                @if($p['tel'])<a class="btn btn-sm btn-call" href="{{ $p['tel'] }}">Call</a>@endif
                                @if($p['wa'])<a class="btn btn-sm btn-wa" href="{{ $p['wa'] }}" target="_blank" rel="noopener">WA</a>@endif
                                <a class="btn btn-sm btn-ghost" href="{{ route('leads.show', $lead) }}">Open</a>
                                <form method="POST" action="{{ url('/leads/'.$lead->id.'/outcome') }}">@csrf<input type="hidden" name="outcome" value="callback"><button class="out" type="submit">Call back</button></form>
                                <form method="POST" action="{{ url('/leads/'.$lead->id.'/outcome') }}">@csrf<input type="hidden" name="outcome" value="won"><button class="out" type="submit">Won</button></form>
                                <form method="POST" action="{{ url('/leads/'.$lead->id.'/outcome') }}">@csrf<input type="hidden" name="outcome" value="lost"><button class="out" type="submit">Lost</button></form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6"><div class="empty">No leads match. Reset filters.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="pager" id="lead-pager" style="display:flex;gap:8px;align-items:center;margin-top:12px">
            @if(! $leadList->onFirstPage())<a class="btn btn-ghost" href="{{ $leadList->previousPageUrl() }}">Prev</a>@endif
            <span class="muted" id="pager-info">Page {{ $leadList->currentPage() }} / {{ max(1, $leadList->lastPage()) }} · {{ $leadList->total() }} leads</span>
            @if($leadList->hasMorePages())<a class="btn btn-ghost" href="{{ $leadList->nextPageUrl() }}">Next</a>@endif
        </div>
        @if($isAdmin)
        <div class="bulkbar" id="bulkbar" hidden style="margin-top:12px">
            <span id="bulk-count">0 selected</span>
            <select id="bulk-staff">
                <option value="">Assign to…</option>
                @foreach($staffList as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
            </select>
            <button class="btn btn-primary" type="button" id="bulk-assign">Assign</button>
            <button class="btn btn-danger" type="button" id="bulk-delete">Delete selected</button>
        </div>
        @endif
    </section>

    <div class="modal" id="add-modal" style="display:{{ request('new')==='1' ? 'grid' : 'none' }}" onclick="if(event.target===this)this.style.display='none'">
        <div class="modal-card" onclick="event.stopPropagation()">
            <header class="panel-h"><h2>New lead</h2><button type="button" class="btn btn-ghost" onclick="document.getElementById('add-modal').style.display='none'">Close</button></header>
            <form method="POST" action="{{ route('leads.store') }}" class="stack">
                @csrf
                <label>Name <input name="name" required></label>
                <label>Phone <input name="phone"></label>
                <label>Email <input type="email" name="email"></label>
                <label>City <input name="city"></label>
                <label>Address <input name="address"></label>
                <label>Notes <textarea name="notes" rows="2"></textarea></label>
                @if($isAdmin)
                    <label>Assign to
                        <select name="assigned_to">
                            <option value="">Unassigned</option>
                            @foreach($staffList as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                        </select>
                    </label>
                @endif
                <div style="display:flex;gap:8px"><button class="btn btn-primary" type="submit">Save lead</button></div>
            </form>
        </div>
    </div>
    <div class="modal" id="del-modal" style="display:none" onclick="if(event.target===this)this.style.display='none'">
        <div class="modal-card" onclick="event.stopPropagation()" style="max-width:420px">
            <header class="panel-h"><h2>Delete lead?</h2></header>
            <p class="muted" id="del-copy">This cannot be undone.</p>
            <form id="del-form" method="POST">
                @csrf @method('DELETE')
                <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:14px">
                    <button type="button" class="btn btn-ghost" onclick="document.getElementById('del-modal').style.display='none'">Cancel</button>
                    <button class="btn btn-danger" type="submit">Delete</button>
                </div>
            </form>
        </div>
    </div>

    <section class="panel" id="new-lead">
        <header class="panel-h"><h2>Add a lead</h2></header>
        <form method="POST" action="{{ route('leads.store') }}" class="form-grid">
            @csrf
            <label>Name <input name="name" required></label>
            <label>Phone <input name="phone"></label>
            <label>Email <input type="email" name="email"></label>
            <label>City <input name="city"></label>
            <label class="span-2">Address <input name="address"></label>
            <label class="span-2">Notes <textarea name="notes" rows="2"></textarea></label>
            @if($isAdmin)
                <label>Assign to
                    <select name="assigned_to">
                        <option value="">Unassigned</option>
                        @foreach($staffList as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                    </select>
                </label>
            @endif
            <div class="span-2"><button class="btn btn-primary" type="submit">Save lead</button></div>
        </form>
    </section>

@else
    @php
        $base = \App\Models\Lead::query()->ownedBy($user);
        $lost = ['converted','not_interested','invalid','duplicate'];
        $callNow = (clone $base)->whereNotIn('status', $lost)->where(function ($qq) {
            $qq->where('status', 'new')->orWhere(function ($qq) {
                $qq->whereNotNull('follow_up_at')->whereDate('follow_up_at', '<=', now()->toDateString());
            });
        })->orderBy('follow_up_at')->limit(40)->get();
        $meetings = (clone $base)->where('status', 'meeting_scheduled')->limit(40)->get();
        $unassigned = $isAdmin ? \App\Models\Lead::query()->whereNull('assigned_to')->whereNotIn('status', $lost)->latest()->limit(30)->get() : collect();
        $wonToday = (clone $base)->where('status', 'converted')->whereDate('updated_at', now()->toDateString())->count();
        $total = (clone $base)->count();
    @endphp
    <div class="stat-grid">
        <article class="stat"><div><span>Call now</span><strong>{{ $callNow->count() }}</strong></div></article>
        <article class="stat"><div><span>Meetings</span><strong>{{ $meetings->count() }}</strong></div></article>
        @if($isAdmin)<article class="stat"><div><span>Unassigned</span><strong>{{ $unassigned->count() }}</strong></div></article>@endif
        <article class="stat"><div><span>Won today</span><strong>{{ $wonToday }}</strong></div></article>
        <article class="stat"><div><span>Pipeline</span><strong>{{ $total }}</strong></div></article>
    </div>

    <div class="grid-2">
        <section class="panel">
            <header class="panel-h"><h2>Call now</h2><small>New + follow-ups due</small></header>
            @forelse($callNow as $lead)
                @php $p = $phoneLinks($lead->phone); $sm = $stageMeta($lead->status); @endphp
                <div class="rowlink" style="align-items:flex-start;flex-wrap:wrap">
                    <a class="entity" href="{{ route('leads.show', $lead) }}">
                        <span class="avatar sm">{{ strtoupper(substr($lead->name,0,1)) }}</span>
                        <span><strong>{{ $lead->name }}</strong><div class="sub">{{ $lead->phone }} · {{ $lead->city }} · {{ $sm[0] }}</div></span>
                    </a>
                    <span class="lead-actions">
                        @if($p['tel'])<a class="btn btn-sm btn-call" href="{{ $p['tel'] }}">Call</a>@endif
                        @if($p['wa'])<a class="btn btn-sm btn-wa" href="{{ $p['wa'] }}" target="_blank" rel="noopener">WA</a>@endif
                        <form method="POST" action="{{ url('/leads/'.$lead->id.'/outcome') }}">@csrf<input type="hidden" name="outcome" value="no_answer"><button class="out" type="submit">No answer</button></form>
                        <form method="POST" action="{{ url('/leads/'.$lead->id.'/outcome') }}">@csrf<input type="hidden" name="outcome" value="callback"><button class="out" type="submit">Call back</button></form>
                        <form method="POST" action="{{ url('/leads/'.$lead->id.'/outcome') }}">@csrf<input type="hidden" name="outcome" value="meeting"><button class="out" type="submit">Meeting</button></form>
                        <form method="POST" action="{{ url('/leads/'.$lead->id.'/outcome') }}">@csrf<input type="hidden" name="outcome" value="won"><button class="out" type="submit">Won</button></form>
                        <form method="POST" action="{{ url('/leads/'.$lead->id.'/outcome') }}">@csrf<input type="hidden" name="outcome" value="lost"><button class="out" type="submit">Lost</button></form>
                    </span>
                </div>
            @empty
                <div class="empty">Queue is clear. Work All leads or find gyms.</div>
            @endforelse
        </section>
        <div>
            <section class="panel">
                <header class="panel-h"><h2>Meetings</h2></header>
                @forelse($meetings as $lead)
                    <a class="rowlink" href="{{ route('leads.show', $lead) }}">
                        <strong>{{ $lead->name }}</strong>
                        <small>{{ optional($lead->meeting_at)->format('d M H:i') ?: 'Scheduled' }}</small>
                    </a>
                @empty
                    <p class="muted">No meetings in this list.</p>
                @endforelse
            </section>
            @if($isAdmin)
            <section class="panel">
                <header class="panel-h"><h2>Unassigned</h2><a href="{{ route('leads.index', ['assigned_to'=>'unassigned']) }}">View all</a></header>
                @forelse($unassigned as $lead)
                    <div class="rowlink">
                        <a href="{{ route('leads.show', $lead) }}"><strong>{{ $lead->name }}</strong></a>
                        <form method="POST" action="{{ route('leads.bulk.assign') }}" style="display:flex;gap:6px">
                            @csrf
                            <input type="hidden" name="ids[]" value="{{ $lead->id }}">
                            <select name="assigned_to" required style="width:140px">
                                <option value="">Assign…</option>
                                @foreach($staffList as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                            </select>
                            <button class="btn btn-sm btn-primary" type="submit">Go</button>
                        </form>
                    </div>
                @empty
                    <p class="muted">Every lead has an owner.</p>
                @endforelse
            </section>
            @endif
        </div>
    </div>
@endif
@endsection
@if($screen === 'leads')
@push('scripts')
<script>
(function () {
    var qEl = document.getElementById('live-q');
    var tbody = document.getElementById('lead-rows');
    var pager = document.getElementById('lead-pager');
    var isAdmin = {{ $isAdmin ? 'true' : 'false' }};
    var liveUrl = @json(route('leads.live'));
    var csrf = document.querySelector('meta[name="csrf-token"]').content;
    var timer = null, page = 1;
    var stage = @json($stage);
    var assigned = @json($assigned);

    function esc(s) {
        return String(s == null ? '' : s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }
    function rowHtml(l) {
        var tel = l.tel ? '<a class="btn btn-sm btn-call" href="'+l.tel+'">Call</a>' : '';
        var wa = l.wa ? '<a class="btn btn-sm btn-wa" href="'+l.wa+'" target="_blank" rel="noopener">WA</a>' : '';
        var check = isAdmin ? '<td class="col-check"><input type="checkbox" class="row-check" value="'+l.id+'"></td>' : '';
        var owner = isAdmin ? '<td>'+esc(l.assignee || 'Unassigned')+'</td>' : '';
        var del = isAdmin ? '<button type="button" class="btn btn-sm btn-danger del-one" data-id="'+l.id+'" data-name="'+esc(l.name)+'">Delete</button>' : '';
        return '<tr data-id="'+l.id+'">'+check+
            '<td><a href="'+l.url+'"><strong>'+esc(l.name)+'</strong></a><div class="sub">'+esc(l.city||'')+'</div></td>'+
            '<td>'+esc(l.phone||'—')+(l.email?'<div class="sub">'+esc(l.email)+'</div>':'')+'</td>'+
            '<td><span class="badge '+esc(l.badge)+'">'+esc(l.status_label)+'</span></td>'+
            owner+
            '<td class="muted">'+esc(l.follow_up_at||'—')+'</td>'+
            '<td><div class="lead-actions">'+tel+wa+
            '<a class="btn btn-sm btn-ghost" href="'+l.url+'">Open</a>'+del+'</div></td></tr>';
    }
    function paint(res) {
        if (!res || !res.data) return;
        tbody.innerHTML = res.data.length ? res.data.map(rowHtml).join('') :
            '<tr><td colspan="7"><div class="empty">No leads match.</div></td></tr>';
        var m = res.meta || {};
        var html = '';
        if (m.current_page > 1) html += '<button type="button" class="btn btn-ghost" data-p="'+(m.current_page-1)+'">Prev</button>';
        html += '<span class="muted" id="pager-info">Page '+m.current_page+' / '+m.last_page+' · '+m.total+' leads</span>';
        if (m.current_page < m.last_page) html += '<button type="button" class="btn btn-ghost" data-p="'+(m.current_page+1)+'">Next</button>';
        pager.innerHTML = html;
        pager.querySelectorAll('[data-p]').forEach(function (b) {
            b.addEventListener('click', function () { page = parseInt(b.getAttribute('data-p'), 10); load(); });
        });
        bindBulk();
    }
    function load() {
        var u = liveUrl+'?q='+encodeURIComponent(qEl ? qEl.value : '')+'&page='+page+'&per_page=25';
        if (stage) u += '&stage='+encodeURIComponent(stage);
        if (assigned) u += '&assigned_to='+encodeURIComponent(assigned);
        fetch(u, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(paint)
            .catch(function () {});
    }
    function bindBulk() {
        var bar = document.getElementById('bulkbar');
        function refresh() {
            var n = document.querySelectorAll('.row-check:checked').length;
            if (bar) { bar.hidden = n === 0; var c = document.getElementById('bulk-count'); if (c) c.textContent = n+' selected'; }
        }
        document.querySelectorAll('.row-check').forEach(function (c) { c.onchange = refresh; });
        var all = document.getElementById('check-all');
        if (all) all.onchange = function () {
            document.querySelectorAll('.row-check').forEach(function (c) { c.checked = all.checked; });
            refresh();
        };
        refresh();
    }
    if (qEl) qEl.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(function () { page = 1; load(); }, 280);
    });
    var openAdd = document.getElementById('open-add');
    if (openAdd) openAdd.addEventListener('click', function () {
        document.getElementById('add-modal').style.display = 'grid';
    });
    document.addEventListener('click', function (e) {
        var d = e.target.closest('.del-one');
        if (!d) return;
        document.getElementById('del-copy').textContent = 'Delete “'+(d.getAttribute('data-name')||'this lead')+'”? This cannot be undone.';
        document.getElementById('del-form').action = '/leads/'+d.getAttribute('data-id');
        document.getElementById('del-modal').style.display = 'grid';
    });
    var ba = document.getElementById('bulk-assign');
    if (ba) ba.addEventListener('click', function () {
        var staff = document.getElementById('bulk-staff').value;
        var ids = Array.prototype.map.call(document.querySelectorAll('.row-check:checked'), function (c) { return c.value; });
        if (!staff || !ids.length) return;
        fetch(@json(route('leads.bulk.assign')), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: JSON.stringify({ ids: ids, assigned_to: staff })
        }).then(function (r) { return r.json(); }).then(function () { location.reload(); });
    });
    var bd = document.getElementById('bulk-delete');
    if (bd) bd.addEventListener('click', function () {
        var ids = Array.prototype.map.call(document.querySelectorAll('.row-check:checked'), function (c) { return c.value; });
        if (!ids.length) return;
        document.getElementById('del-copy').textContent = 'Delete '+ids.length+' selected lead(s)? This cannot be undone.';
        document.getElementById('del-modal').style.display = 'grid';
        document.getElementById('del-form').onsubmit = function (e) {
            e.preventDefault();
            fetch(@json(route('leads.bulk.delete')), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                body: JSON.stringify({ ids: ids })
            }).then(function () { location.reload(); });
        };
    });
    bindBulk();
})();
</script>
@endpush
@endif
