@extends('layouts.app')
@section('title', 'Today')
@section('heading', 'Today')
@section('subhead', $isAdmin ? 'Who to assign, who to chase, meetings on the calendar.' : 'Call this list. Log what happened. That’s the job.')
@section('actions')
    <a class="btn btn-primary" href="{{ route('leads.index') }}?new=1">New lead</a>
@endsection
@section('content')
<style>
.piles { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 10px; margin-bottom: 16px; }
.pile { background:#fff; border:1px solid #e6ebf2; border-radius:12px; padding:12px 14px; cursor:pointer; }
.pile.on, .pile:hover { border-color:#93c5fd; box-shadow:0 0 0 3px rgba(37,99,235,.12); }
.pile span { display:block; color:#667085; font-size:12px; font-weight:600; }
.pile strong { display:block; font-size:22px; margin-top:4px; }
.lead-card { background:#fff; border:1px solid #e6ebf2; border-radius:14px; padding:14px; margin-bottom:10px; }
.lead-card h3 { font-size:16px; margin:0 0 4px; }
.qc { display:flex; flex-wrap:wrap; gap:6px; margin-top:10px; }
.qc .btn { height:34px; }
.btn-call { background:#067647; color:#fff !important; border:0; }
.btn-wa { background:#128C7E; color:#fff !important; border:0; }
.out { border:1px solid #d5dce6; background:#fff; border-radius:8px; padding:6px 10px; font-size:12px; font-weight:650; cursor:pointer; }
.out:hover { background:#eef3ff; border-color:#bfdbfe; }
</style>

<div class="piles" id="piles">
    <article class="pile on" data-pile="call_now"><span>Call now</span><strong id="c-call">—</strong></article>
    <article class="pile" data-pile="meetings"><span>Meetings</span><strong id="c-meet">—</strong></article>
    @if($isAdmin)
        <article class="pile" data-pile="unassigned"><span>Unassigned</span><strong id="c-unassigned">—</strong></article>
    @endif
    <article class="pile" data-pile="won"><span>Won today</span><strong id="c-won">—</strong></article>
</div>

<section class="panel">
    <header class="panel-h">
        <h2 id="pile-title">Call now</h2>
        <small id="pile-hint">New leads and follow-ups due. Call, then tap what happened.</small>
    </header>
    <div id="pile-list"><div class="sk sk-line" style="height:48px"></div></div>
</section>

@if($isAdmin)
<div class="modal" id="assign-one" style="display:none" onclick="if(event.target===this) this.style.display='none'">
    <div class="modal-card" onclick="event.stopPropagation()" style="max-width:420px">
        <header class="panel-h"><h2>Assign lead</h2></header>
        <label>Staff
            <select id="assign-staff">
                <option value="">Select staff</option>
                @foreach($staff as $s)
                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                @endforeach
            </select>
        </label>
        <div style="display:flex;gap:8px;margin-top:14px;justify-content:flex-end">
            <button type="button" class="btn btn-ghost" onclick="document.getElementById('assign-one').style.display='none'">Cancel</button>
            <button type="button" class="btn btn-primary" id="assign-go">Assign</button>
        </div>
    </div>
</div>
@endif
@endsection
@push('scripts')
<script>
GymLead.ready(function () {
    var isAdmin = {{ $isAdmin ? 'true' : 'false' }};
    var outcomeUrl = @json(url('/leads'));
    var assignUrl = @json(route('leads.bulk.assign'));
    var data = { call_now: [], meetings: [], unassigned: [] };
    var pile = 'call_now';
    var assignId = null;
    var titles = {
        call_now: ['Call now', 'New leads and follow-ups due.'],
        meetings: ['Meetings', 'Booked meetings. Confirm or mark won/lost.'],
        unassigned: ['Unassigned', 'Give these to a closer.'],
        won: ['Won today', 'Already converted today.']
    };

    function card(l) {
        var tel = l.tel ? '<a class="btn btn-call" href="' + l.tel + '">Call</a>' : '';
        var wa = l.wa ? '<a class="btn btn-wa" href="' + l.wa + '" target="_blank" rel="noopener">WhatsApp</a>' : '';
        var extra = isAdmin && pile === 'unassigned'
            ? '<button type="button" class="btn btn-primary" data-assign="' + l.id + '">Assign</button>'
            : '<button type="button" class="out" data-id="' + l.id + '" data-o="no_answer">No answer</button>' +
              '<button type="button" class="out" data-id="' + l.id + '" data-o="callback">Call back</button>' +
              '<button type="button" class="out" data-id="' + l.id + '" data-o="meeting">Meeting</button>' +
              '<button type="button" class="out" data-id="' + l.id + '" data-o="won">Won</button>' +
              '<button type="button" class="out" data-id="' + l.id + '" data-o="lost">Lost</button>';
        var meta = [l.phone || 'No phone', l.city, l.follow_up_at ? 'Due ' + l.follow_up_at : '', l.meeting_at || '', l.assignee || ''].filter(Boolean).join(' · ');
        return '<article class="lead-card" id="lc-' + l.id + '">' +
            '<div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start">' +
            '<div><h3><a href="' + l.url + '">' + GymLead.esc(l.name) + '</a></h3><div class="sub">' + GymLead.esc(meta) + '</div></div>' +
            '<span class="badge ' + GymLead.esc(l.badge) + '">' + GymLead.esc(l.stage_label) + '</span></div>' +
            '<div class="qc">' + tel + wa + extra + '</div></article>';
    }
    function render() {
        var t = titles[pile] || titles.call_now;
        document.getElementById('pile-title').textContent = t[0];
        document.getElementById('pile-hint').textContent = t[1];
        var list = pile === 'won' ? [] : (data[pile] || []);
        var box = document.getElementById('pile-list');
        if (pile === 'won') {
            box.innerHTML = '<div class="empty">Won count is on the card. Keep calling the other lists.</div>';
            return;
        }
        box.innerHTML = list.length ? list.map(card).join('') : '<div class="empty">Nothing here. Nice — pick another list or add a lead.</div>';
    }
    function load() {
        GymLead.get(@json(route('today.data'))).then(function (res) {
            if (!res.ok) return;
            data = res;
            document.getElementById('c-call').textContent = res.counts.call_now;
            document.getElementById('c-meet').textContent = res.counts.meetings;
            var u = document.getElementById('c-unassigned');
            if (u) u.textContent = res.counts.unassigned;
            document.getElementById('c-won').textContent = res.counts.won_today;
            render();
        });
    }
    document.getElementById('piles').addEventListener('click', function (e) {
        var p = e.target.closest('.pile');
        if (!p) return;
        pile = p.getAttribute('data-pile');
        document.querySelectorAll('.pile').forEach(function (x) { x.classList.toggle('on', x === p); });
        render();
    });
    document.getElementById('pile-list').addEventListener('click', function (e) {
        var as = e.target.closest('[data-assign]');
        if (as) {
            assignId = as.getAttribute('data-assign');
            document.getElementById('assign-one').style.display = 'grid';
            return;
        }
        var b = e.target.closest('[data-o]');
        if (!b) return;
        var id = b.getAttribute('data-id');
        var o = b.getAttribute('data-o');
        b.disabled = true;
        GymLead.post(outcomeUrl + '/' + id + '/outcome', { outcome: o }).then(function (res) {
            if (!res.ok) { b.disabled = false; return; }
            GymLead.toast(res.message);
            var cardEl = document.getElementById('lc-' + id);
            if (cardEl) cardEl.remove();
            load();
        }).catch(function () { b.disabled = false; });
    });
    var go = document.getElementById('assign-go');
    if (go) go.addEventListener('click', function () {
        var staff = document.getElementById('assign-staff').value;
        if (!staff) return GymLead.toast('Pick a staff member.', 'err');
        GymLead.post(assignUrl, { ids: [assignId], assigned_to: staff }).then(function (res) {
            if (!res.ok) return;
            GymLead.toast(res.message);
            document.getElementById('assign-one').style.display = 'none';
            load();
        });
    });
    load();
});
</script>
@endpush
