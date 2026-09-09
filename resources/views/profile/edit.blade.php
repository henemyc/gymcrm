@extends('layouts.app')
@section('title', 'Profile')
@section('heading', 'Profile')
@section('subhead', 'Your name and password for this workspace.')
@section('content')
<div class="grid-2">
    <section class="panel form-panel">
        <header class="panel-h"><h2>Details</h2></header>
        <form id="prof-form" class="stack" novalidate>
            <label>Name <input name="name" value="{{ auth()->user()->name }}" required></label>
            <label>Phone <input name="phone" value="{{ auth()->user()->phone }}"></label>
            <p class="hint">Email: {{ auth()->user()->email }}</p>
            <button class="btn btn-primary" type="submit">Save</button>
        </form>
    </section>
    <section class="panel form-panel">
        <header class="panel-h"><h2>Password</h2></header>
        <form id="pw-form" class="stack" novalidate>
            <label>Current <input type="password" name="current_password" required></label>
            <label>New <input type="password" name="password" minlength="8" required></label>
            <label>Confirm <input type="password" name="password_confirmation" required></label>
            <button class="btn btn-primary" type="submit">Change password</button>
        </form>
    </section>
</div>
@endsection
@push('scripts')
<script>
GymLead.ready(function () {
    document.getElementById('prof-form').addEventListener('submit', function (e) {
        e.preventDefault();
        GymLead.post('{{ route('profile.update') }}', GymLead.formData(this)).then(function (res) {
            if (res.ok) GymLead.toast(res.message || 'Profile updated.');
        });
    });
    document.getElementById('pw-form').addEventListener('submit', function (e) {
        e.preventDefault();
        var form = this;
        GymLead.post('{{ route('profile.password') }}', GymLead.formData(form)).then(function (res) {
            if (!res.ok) return;
            GymLead.toast(res.message || 'Password changed.');
            form.reset();
        });
    });
});
</script>
@endpush
