@extends('layouts.app')
@php
    $digits = preg_replace('/\D+/', '', (string) $lead->phone);
    if (strlen((string) $digits) === 10) { $digits = '91'.$digits; }
    $tel = strlen((string) $digits) >= 10 ? 'tel:+'.$digits : null;
    $wa = strlen((string) $digits) >= 10 ? 'https://wa.me/'.$digits : null;
    $stage = match ($lead->status) {
        'new' => 'New',
        'contacted', 'follow_up', 'interested' => 'In talk',
        'meeting_scheduled' => 'Meeting',
        'converted' => 'Won',
        'not_interested', 'invalid' => 'Lost',
        default => $lead->status,
    };
    $badge = match ($lead->status) {
        'new' => 'badge-new',
        'meeting_scheduled' => 'badge-meeting',
        'converted' => 'badge-converted',
        'not_interested', 'invalid' => 'badge-not',
        default => 'badge-contacted',
    };
@endphp
@section('title', $lead->name)
@section('heading', $lead->name)
@section('subhead', $stage)
@section('actions')
    <a class="btn btn-ghost" href="{{ route('today') }}">Today</a>
@endsection
@section('content')
<style>
.hero-actions { display:flex; flex-wrap:wrap; gap:8px; margin: 4px 0 16px; }
.btn-call { background:#067647; color:#fff !important; height:42px; padding:0 16px; }
.btn-wa { background:#128C7E; color:#fff !important; height:42px; padding:0 16px; }
.out { border:1px solid #d5dce6; background:#fff; border-radius:10px; padding:10px 12px; font-weight:650; cursor:pointer; }
.out:hover { background:#eef3ff; border-color:#bfdbfe; }
.out-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(120px,1fr)); gap:8px; }
</style>

<section class="panel">
    <div class="entity" style="margin-bottom:12px">
        <span class="avatar">{{ strtoupper(substr($lead->name, 0, 1)) }}</span>
        <div>
            <strong>{{ $lead->name }}</strong>
            <div class="sub">{{ $lead->city ?: $lead->address ?: '—' }}</div>
        </div>
        <span class="badge {{ $badge }}">{{ $stage }}</span>
    </div>
    <div class="hero-actions">
        @if($tel)<a class="btn btn-call" href="{{ $tel }}">Call {{ $lead->phone }}</a>@endif
        @if($wa)<a class="btn btn-wa" href="{{ $wa }}" target="_blank" rel="noopener">WhatsApp</a>@endif
        @if($lead->google_maps_url)<a class="btn btn-ghost" href="{{ $lead->google_maps_url }}" target="_blank" rel="noopener">Maps</a>@endif
    </div>
    @unless($tel)
        <p class="muted">No phone on this lead.</p>
    @endunless
</section>

<section class="panel">
    <header class="panel-h"><h2>What happened?</h2></header>
    <div class="out-grid" id="outcomes">
        <button type="button" class="out" data-o="no_answer">No answer</button>
        <button type="button" class="out" data-o="callback">Call back tomorrow</button>
        <button type="button" class="out" data-o="meeting">Meeting tomorrow 11:00</button>
        <button type="button" class="out" data-o="won">Won</button>
        <button type="button" class="out" data-o="lost">Lost</button>
    </div>
    <form id="note-form" class="stack" style="margin-top:14px">
        <textarea name="body" rows="2" required placeholder="Optional note after the call…"></textarea>
        <button class="btn btn-primary" type="submit">Save note only</button>
    </form>
</section>

<section class="panel">
    <header class="panel-h"><h2>Notes</h2></header>
    <div id="notes" class="notes">
        @forelse($lead->leadNotes as $note)
            <article>
                <strong>{{ $note->user->name ?? 'User' }}</strong>
                <small>{{ $note->created_at->diffForHumans() }}</small>
                <p>{{ $note->body }}</p>
            </article>
        @empty
            <p class="muted" id="no-notes">No notes yet.</p>
        @endforelse
    </div>
</section>

<section class="panel">
    <header class="panel-h"><h2>Details</h2></header>
    <dl class="facts">
        <div><dt>Phone</dt><dd>{{ $lead->phone ?: '—' }}</dd></div>
        <div><dt>Email</dt><dd>{{ $lead->email ?: '—' }}</dd></div>
        <div><dt>Address</dt><dd>{{ $lead->address ?: '—' }}</dd></div>
        <div><dt>Owner</dt><dd>{{ $lead->assignee->name ?? 'Unassigned' }}</dd></div>
        <div><dt>Follow up</dt><dd>{{ optional($lead->follow_up_at)->format('d M Y') ?: '—' }}</dd></div>
        <div><dt>Meeting</dt><dd>{{ optional($lead->meeting_at)->format('d M Y H:i') ?: '—' }}</dd></div>
    </dl>
    @if(auth()->user()->isAdmin())
        <form id="assign-form" class="stack" style="margin-top:14px">
            <label>Owner
                <select name="assigned_to" required>
                    @foreach($staff as $s)
                        <option value="{{ $s->id }}" @selected((int)$lead->assigned_to === (int)$s->id)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </label>
            <button class="btn btn-ghost" type="submit">Assign</button>
        </form>
    @endif
</section>
@endsection
@push('scripts')
<script>
GymLead.ready(function () {
    document.getElementById('outcomes').addEventListener('click', function (e) {
        var b = e.target.closest('[data-o]');
        if (!b) return;
        GymLead.post(@json(route('leads.outcome', $lead)), { outcome: b.getAttribute('data-o') }).then(function (res) {
            if (!res.ok) return;
            GymLead.toast(res.message);
            location.reload();
        });
    });
    document.getElementById('note-form').addEventListener('submit', function (e) {
        e.preventDefault();
        var form = this;
        GymLead.post(@json(route('leads.notes.store', $lead)), GymLead.formData(form)).then(function (res) {
            if (!res.ok) return;
            GymLead.toast(res.message);
            var empty = document.getElementById('no-notes');
            if (empty) empty.remove();
            var art = document.createElement('article');
            art.innerHTML = '<strong>' + GymLead.esc(res.note.user) + '</strong><small> ' + GymLead.esc(res.note.created_at) + '</small><p>' + GymLead.esc(res.note.body) + '</p>';
            document.getElementById('notes').prepend(art);
            form.reset();
        });
    });
    var af = document.getElementById('assign-form');
    if (af) af.addEventListener('submit', function (e) {
        e.preventDefault();
        GymLead.post(@json(route('leads.bulk.assign')), { ids: [{{ $lead->id }}], assigned_to: this.assigned_to.value }).then(function (res) {
            if (!res.ok) return;
            GymLead.toast(res.message);
        });
    });
});
</script>
@endpush
