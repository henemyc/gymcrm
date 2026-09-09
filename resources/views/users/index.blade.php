@extends('layouts.app')
@section('title', 'Team')
@section('heading', 'Team')
@section('subhead', 'People, roles, and who is online right now.')
@section('actions')
    <button class="btn btn-primary" type="button" id="open-create">Invite user</button>
@endsection
@section('content')
<style>
.team-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; margin-bottom: 14px; }
.team-stat { background: #fff; border: 1px solid #e6ebf2; border-radius: 12px; padding: 12px 14px; }
.team-stat span { display: block; color: #667085; font-size: 12px; font-weight: 600; }
.team-stat strong { display: block; font-size: 22px; margin-top: 4px; }
.table-shell { position: relative; min-height: 160px; }
.table-loader { display: none; position: absolute; inset: 0; background: rgba(255,255,255,.72); place-items: center; z-index: 3; }
.table-loader.show { display: grid; }
</style>

<div class="team-stats">
    <article class="team-stat"><span>Everyone</span><strong id="ts-total">—</strong></article>
    <article class="team-stat"><span>Admins</span><strong id="ts-admins">—</strong></article>
    <article class="team-stat"><span>Staff</span><strong id="ts-staff">—</strong></article>
    <article class="team-stat"><span>Online now</span><strong id="ts-online">—</strong></article>
    <article class="team-stat"><span>Disabled</span><strong id="ts-disabled">—</strong></article>
</div>

<section class="panel">
    <form class="filters" id="user-filters">
        <input type="search" name="q" id="user-q" placeholder="Search name or email" autocomplete="off">
        <select name="role">
            <option value="">All roles</option>
            @foreach($roles as $r)
                <option value="{{ $r->value }}">{{ $r->label() }}</option>
            @endforeach
        </select>
        <label class="pager-size">Per page
            <select name="per_page" id="user-per-page">
                <option value="10">10</option>
                <option value="15" selected>15</option>
                <option value="25">25</option>
                <option value="50">50</option>
            </select>
        </label>
    </form>
    <div class="table-shell">
        <div class="table-loader" id="user-loader"><div class="spin"></div></div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Person</th>
                        <th>Role</th>
                        <th>Leads</th>
                        <th>Last online</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="user-rows">
                    <tr><td colspan="6"><div class="skeleton" style="height:18px"></div></td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="pager" id="pager"></div>
</section>

<div class="modal" id="user-modal" style="display:none" onclick="if (event.target === this) closeUserModal();">
    <div class="modal-card" onclick="event.stopPropagation();">
        <header class="panel-h">
            <h2 id="modal-title">Add user</h2>
            <button type="button" class="btn btn-ghost" onclick="closeUserModal(); return false;">Close</button>
        </header>
        <form id="user-form" class="form-grid">
            <input type="hidden" name="id">
            <label class="span-2">Name <input name="name" required></label>
            <label>Email <input type="email" name="email" required></label>
            <label>Phone <input name="phone"></label>
            <label>Role
                <select name="role" required>
                    <option value="staff">Staff</option>
                    <option value="admin">Admin</option>
                </select>
            </label>
            <label>Daily lead cap <input type="number" name="daily_lead_limit" min="0" value="0"></label>
            <label>Password <input type="password" name="password" minlength="8"></label>
            <label class="check span-2"><input type="checkbox" name="is_active" value="1" checked> Active</label>
            <div class="span-2" style="display:flex;gap:8px">
                <button class="btn btn-primary" type="submit">Save</button>
                <button class="btn btn-ghost" type="button" onclick="closeUserModal(); return false;">Cancel</button>
            </div>
        </form>
    </div>
</div>
@endsection
@push('scripts')
<script>
GymLead.ready(function () {
    var page = 1, timer = null;
    function load() {
        document.getElementById('user-loader').classList.add('show');
        var q = new URLSearchParams(new FormData(document.getElementById('user-filters')));
        q.set('page', page);
        GymLead.get('{{ route('users.datatable') }}?' + q.toString()).then(function (res) {
            document.getElementById('user-loader').classList.remove('show');
            if (!res.ok) return;
            if (res.stats) {
                document.getElementById('ts-total').textContent = res.stats.total;
                document.getElementById('ts-admins').textContent = res.stats.admins;
                document.getElementById('ts-staff').textContent = res.stats.staff;
                document.getElementById('ts-online').textContent = res.stats.online;
                document.getElementById('ts-disabled').textContent = res.stats.disabled;
            }
            document.getElementById('user-rows').innerHTML = res.data.map(function (u) {
                var initial = GymLead.esc((u.name || '?').charAt(0).toUpperCase());
                var online = u.is_online
                    ? '<span class="dot on"></span>Online now'
                    : '<span class="dot"></span>' + GymLead.esc(u.last_online || 'Never');
                return '<tr>' +
                    '<td><div class="entity"><span class="avatar sm">' + initial + '</span><span><strong>' + GymLead.esc(u.name) + '</strong><div class="sub">' + GymLead.esc(u.email) + '</div></span></div></td>' +
                    '<td><span class="badge">' + GymLead.esc(u.role) + '</span></td>' +
                    '<td>' + u.assigned_leads_count + '</td>' +
                    '<td>' + online + '</td>' +
                    '<td>' + (u.is_active ? '<span class="badge badge-converted">Active</span>' : '<span class="badge badge-not">Disabled</span>') + '</td>' +
                    '<td><button class="linkish edit" data-id="' + u.id + '">Edit</button> · <button class="linkish toggle" data-id="' + u.id + '">' + (u.is_active ? 'Disable' : 'Enable') + '</button></td></tr>';
            }).join('') || '<tr><td colspan="6" class="muted">No users</td></tr>';
            GymLead.pager('pager', res.meta, function (p) { page = p; load(); });
        }).catch(function () {
            document.getElementById('user-loader').classList.remove('show');
        });
    }
    document.getElementById('user-q').addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(function () { page = 1; load(); }, 280);
    });
    document.getElementById('user-filters').addEventListener('change', function () { page = 1; load(); });
    document.getElementById('user-filters').addEventListener('submit', function (e) { e.preventDefault(); page = 1; load(); });
    document.getElementById('open-create').addEventListener('click', function () {
        document.getElementById('user-form').reset();
        document.querySelector('[name=id]').value = '';
        document.getElementById('modal-title').textContent = 'Add user';
        openUserModal();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeUserModal();
    });
    document.getElementById('user-rows').addEventListener('click', function (e) {
        var ed = e.target.closest('.edit');
        var tg = e.target.closest('.toggle');
        if (ed) {
            GymLead.get('{{ url('/users') }}/' + ed.dataset.id).then(function (res) {
                var u = res.user;
                var f = document.getElementById('user-form');
                f.id.value = u.id;
                f.name.value = u.name;
                f.email.value = u.email;
                f.phone.value = u.phone || '';
                f.role.value = u.role;
                f.daily_lead_limit.value = u.daily_lead_limit;
                f.is_active.checked = !!u.is_active;
                document.getElementById('modal-title').textContent = 'Edit user';
                openUserModal();
            });
        }
        if (tg) {
            GymLead.post('{{ url('/users') }}/' + tg.dataset.id + '/toggle', {}).then(function (res) {
                GymLead.toast(res.message); load();
            });
        }
    });
    document.getElementById('user-form').addEventListener('submit', function (e) {
        e.preventDefault();
        var data = GymLead.formData(this);
        data.is_active = this.is_active.checked ? 1 : 0;
        var id = data.id;
        delete data.id;
        var url = id ? '{{ url('/users') }}/' + id : '{{ route('users.store') }}';
        GymLead.send(id ? 'PUT' : 'POST', url, data).then(function (res) {
            if (!res.ok) return;
            GymLead.toast(res.message); closeUserModal(); load();
        });
    });
    load();
});
</script>
@endpush
