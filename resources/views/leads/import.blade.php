@extends('layouts.app')
@section('title', 'Import leads')
@section('heading', 'Import leads')
@section('subhead', 'Upload a CSV. Duplicates are skipped by phone, Place ID, and name + address.')
@section('actions')
    <a class="btn btn-ghost" href="{{ route('leads.import.template') }}">Download template</a>
@endsection
@section('content')
<section class="panel form-panel">
    <form id="import-form" class="stack" enctype="multipart/form-data">
        <label>CSV file
            <input type="file" name="file" accept=".csv,text/csv" required>
        </label>
        <label>Assign imported rows to
            <select name="assigned_to">
                <option value="">Leave unassigned</option>
                @foreach($staff as $s)
                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                @endforeach
            </select>
        </label>
        <p class="hint">Columns: name, phone, email, website, address, city, state, country, category, notes. Max 5 MB.</p>
        <button class="btn btn-primary" type="submit">Import</button>
    </form>
    <pre id="import-result" class="log" hidden></pre>
</section>
@endsection
@push('scripts')
<script>
GymLead.ready(function () {
    document.getElementById('import-form').addEventListener('submit', function (e) {
        e.preventDefault();
        var fd = new FormData(this);
        GymLead.postForm('{{ route('leads.import') }}', fd).then(function (res) {
            GymLead.toast(res.message);
            var el = document.getElementById('import-result');
            el.hidden = false;
            el.textContent = JSON.stringify(res.result, null, 2);
        });
    });
});
</script>
@endpush
