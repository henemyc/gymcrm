@extends('layouts.app')
@section('title', 'Activity')
@section('heading', 'Activity')
@section('subhead', 'Audit trail for assignments, scrapes, imports, and record changes.')
@section('content')
<section class="panel">
    <form class="filters" id="act-filters">
        <input type="search" name="q" placeholder="Search actions">
        <label class="pager-size">Per page
            <select name="per_page">
                <option value="10">10</option>
                <option value="20" selected>20</option>
                <option value="50">50</option>
            </select>
        </label>
        <button class="btn btn-ghost" type="submit">Apply</button>
    </form>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Detail</th></tr></thead>
            <tbody id="act-rows"></tbody>
        </table>
    </div>
    <div class="pager" id="pager"></div>
</section>
@endsection
@push('scripts')
<script>
GymLead.ready(function () {
    var page = 1;
    function load() {
        var q = new URLSearchParams(new FormData(document.getElementById('act-filters')));
        q.set('page', page);
        GymLead.get('{{ route('activity.datatable') }}?' + q.toString()).then(function (res) {
            document.getElementById('act-rows').innerHTML = res.data.map(function (a) {
                return '<tr><td>' + GymLead.esc(a.created_at) + '</td><td>' + GymLead.esc(a.user || 'System') +
                    '</td><td>' + GymLead.esc(a.action) + '</td><td>' + GymLead.esc(a.description) + '</td></tr>';
            }).join('');
            GymLead.pager('pager', res.meta, function (p) { page = p; load(); });
        });
    }
    document.getElementById('act-filters').addEventListener('submit', function (e) { e.preventDefault(); page = 1; load(); });
    load();
});
</script>
@endpush
