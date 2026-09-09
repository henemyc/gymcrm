@extends('layouts.app')
@section('title', 'Forbidden')
@section('heading', 'Access denied')
@section('subhead', 'This page is outside your role.')
@section('content')
<section class="panel">
    <p>You do not have permission to open this page. Staff can only work leads assigned to them.</p>
    <p><a class="btn btn-primary" href="{{ route('dashboard') }}">Back to dashboard</a></p>
</section>
@endsection
