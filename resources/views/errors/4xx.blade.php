{{-- Fallback for any 4xx without its own page. --}}
@extends('errors.layout')

@section('title', 'Can’t open this page')
@section('code', isset($exception) && method_exists($exception, 'getStatusCode') ? (string) $exception->getStatusCode() : '4xx')
@section('heading', 'We can’t open this page')
@section('message', 'The link or form didn’t work as expected. Go back and try again, or start over from the resort’s home page.')

@section('actions')
    <button type="button" onclick="history.length > 1 ? history.back() : location.assign('{{ url('/') }}')" class="btn-primary">Go back</button>
    <a href="{{ url('/') }}" class="btn-ghost">Back to the resort</a>
@endsection
