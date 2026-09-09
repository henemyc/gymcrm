@extends('layouts.app')
@section('title', 'Settings')
@section('heading', 'Settings')
@section('subhead', 'Workspace limits and the Google Maps key. The key is encrypted at rest.')
@section('content')
<section class="panel form-panel">
    <form id="settings-form" class="form-grid">
        <label class="span-2">Company name
            <input name="company_name" value="{{ $settings['company_name'] }}" required>
        </label>
        <label class="span-2">Google Maps API key
            <input name="google_maps_api_key" value="{{ $settings['google_maps_api_key_masked'] }}" placeholder="Paste a new key to replace" autocomplete="off">
            <span class="hint">{{ $settings['google_maps_api_key_set'] ? 'A key is saved. Leave blank to keep it. Paste a new key to rotate.' : 'No key saved yet. Enable Places API + Geocoding API on this key.' }}</span>
        </label>
        <label>Default keyword <input name="scrape_default_keyword" value="{{ $settings['scrape_default_keyword'] }}"></label>
        <label>Default radius (m) <input type="number" name="scrape_default_radius" value="{{ $settings['scrape_default_radius'] }}" min="50" max="50000"></label>
        <label>Max radius (m) <input type="number" name="scrape_max_radius" value="{{ $settings['scrape_max_radius'] }}" min="100" max="50000"></label>
        <label>Max pages per search (1–3) <input type="number" name="scrape_max_pages" value="{{ $settings['scrape_max_pages'] }}" min="1" max="3"></label>
        <label>Max scrapes / admin / day <input type="number" name="scrape_max_per_day" value="{{ $settings['scrape_max_per_day'] }}" min="1" max="200"></label>
        <label>Leads per page <input type="number" name="leads_per_page" value="{{ $settings['leads_per_page'] }}" min="10" max="50"></label>
        <label>Max CSV import rows <input type="number" name="max_import_rows" value="{{ $settings['max_import_rows'] }}" min="10" max="10000"></label>
        <label class="check span-2"><input type="checkbox" name="enrich_on_assign" value="1" @checked($settings['enrich_on_assign'])> Fetch Place Details (phone/website) when assigning Maps results</label>
        <label class="check span-2"><input type="checkbox" name="duplicate_check_place_id" value="1" @checked($settings['duplicate_check_place_id'])> Block duplicate Google Place IDs</label>
        <label class="check span-2"><input type="checkbox" name="duplicate_check_phone" value="1" @checked($settings['duplicate_check_phone'])> Block duplicate phones</label>
        <label class="check span-2"><input type="checkbox" name="duplicate_check_name_address" value="1" @checked($settings['duplicate_check_name_address'])> Block duplicate name + address + pin</label>
        <label class="check span-2"><input type="checkbox" name="staff_can_export" value="1" @checked($settings['staff_can_export'])> Allow staff to export their leads</label>
        <div class="span-2">
            <button class="btn btn-primary" type="submit">Save settings</button>
            <button class="btn btn-ghost" type="button" id="test-maps">Test Maps key</button>
        </div>
    </form>
</section>
@endsection
@push('scripts')
<script>
GymLead.ready(function () {
    document.getElementById('settings-form').addEventListener('submit', function (e) {
        e.preventDefault();
        var data = GymLead.formData(this);
        ['enrich_on_assign','duplicate_check_place_id','duplicate_check_phone','duplicate_check_name_address','staff_can_export'].forEach(function (k) {
            data[k] = this.querySelector('[name="'+k+'"]').checked ? 1 : 0;
        }.bind(this));
        GymLead.post('{{ route('settings.update') }}', data).then(function (res) {
            GymLead.toast(res.message);
        });
    });
    document.getElementById('test-maps').addEventListener('click', function () {
        GymLead.post('{{ route('settings.test-maps') }}', {}).then(function (res) {
            GymLead.toast(res.message, res.ok ? 'ok' : 'err');
        });
    });
});
</script>
@endpush
