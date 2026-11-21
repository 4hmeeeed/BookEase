{{-- 410: e.g. an old sandbox checkout closed after a newer one started, or the booking was cancelled. --}}
@extends('errors.layout')

@section('title', 'Link closed')
@section('code', '410')
@section('heading', 'This checkout has closed')
@section('message', 'You may have started a newer payment, or the booking was cancelled. Your booking page always shows the latest status and payment options.')

@section('actions')
    <a href="{{ url('/my/bookings') }}" class="btn-primary">Open my bookings</a>
    <a href="{{ url('/') }}" class="btn-ghost">Back to the resort</a>
@endsection
