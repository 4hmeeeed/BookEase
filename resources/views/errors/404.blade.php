@extends('errors.layout')

@section('title', 'Page not found')
@section('code', '404')
@section('heading', 'Not on the chart')
@section('message', 'We couldn’t find that page. It may have moved, or the link may have a typo.')

@section('actions')
    <a href="{{ url('/') }}" class="btn-primary">Back to the resort</a>
    <a href="{{ url('/cottages') }}" class="btn-ghost">Browse cottages</a>
@endsection
