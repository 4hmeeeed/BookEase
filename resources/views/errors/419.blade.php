@extends('errors.layout')

@section('title', 'Page expired')
@section('code', '419')
@section('heading', 'This page expired')
@section('message', 'For your security, forms expire after a while. Go back, refresh the page, and send it again.')

@section('actions')
    <button type="button" onclick="history.length > 1 ? history.back() : location.assign('{{ url('/') }}')" class="btn-primary">Go back</button>
    <a href="{{ url('/') }}" class="btn-ghost">Back to the resort</a>
@endsection
