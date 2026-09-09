@extends('layouts.app')
@section('title', $lead->exists ? 'Edit lead' : 'Add lead')
@section('heading', $lead->exists ? 'Edit lead' : 'New lead')
@section('subhead', $lead->exists ? 'Update contact details and ownership.' : 'Create a record when Maps is not the source.')
@section('content')
<section class="panel form-panel">
    <form method="POST" action="{{ $lead->exists ? route('leads.update', $lead) : route('leads.store') }}" id="lead-form" class="form-grid">
        @csrf
        @if($lead->exists) @method('PUT') @endif
        <label class="span-2">Business / person
            <input name="name" value="{{ old('name', $lead->name) }}" required maxlength="191">
        </label>
        <label>Phone <input name="phone" value="{{ old('phone', $lead->phone) }}"></label>
        <label>Email <input type="email" name="email" value="{{ old('email', $lead->email) }}"></label>
        <label>Website <input name="website" value="{{ old('website', $lead->website) }}"></label>
        <label>Category <input name="category" value="{{ old('category', $lead->category) }}" placeholder="gym"></label>
        <label class="span-2">Address <input name="address" value="{{ old('address', $lead->address) }}"></label>
        <label>City <input name="city" value="{{ old('city', $lead->city) }}"></label>
        <label>State <input name="state" value="{{ old('state', $lead->state) }}"></label>
        <label>Country <input name="country" value="{{ old('country', $lead->country) }}"></label>
        <label>Status
            <select name="status">
                @foreach($statuses as $st)
                    <option value="{{ $st->value }}" @selected(old('status', $lead->status) === $st->value)>{{ $st->label() }}</option>
                @endforeach
            </select>
        </label>
        @if(auth()->user()->isAdmin())
            <label>Assign to
                <select name="assigned_to">
                    <option value="">Unassigned</option>
                    @foreach($staff as $s)
                        <option value="{{ $s->id }}" @selected((string) old('assigned_to', $lead->assigned_to) === (string) $s->id)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </label>
        @endif
        <label>Follow up
            <input type="date" name="follow_up_at" value="{{ old('follow_up_at', optional($lead->follow_up_at)->format('Y-m-d')) }}">
        </label>
        <label class="span-2">Notes
            <textarea name="notes" rows="4">{{ old('notes', $lead->notes) }}</textarea>
        </label>
        <div class="span-2">
            <button class="btn btn-primary" type="submit">{{ $lead->exists ? 'Save changes' : 'Create lead' }}</button>
            <a class="btn btn-ghost" href="{{ $lead->exists ? route('leads.show', $lead) : route('leads.index') }}">Cancel</a>
        </div>
    </form>
</section>
@endsection
