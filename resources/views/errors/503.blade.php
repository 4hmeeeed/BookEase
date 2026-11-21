@extends('errors.layout')

@section('title', 'Back shortly')
@section('code', '503')
@section('heading', 'Back in a few minutes')
@section('message', 'BookEase is being updated. Your bookings and payments are safe. Please check back shortly.')

@section('actions')
    <button type="button" onclick="location.reload()" class="btn-primary">Try again</button>
@endsection
