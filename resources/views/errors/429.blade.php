{{-- 429: rate limits on sign-up, password resets and the chatbot. --}}
@php
    $retryAfter = (int) ((isset($exception) && method_exists($exception, 'getHeaders')) ? ($exception->getHeaders()['Retry-After'] ?? 0) : 0);
    $wait = match (true) {
        $retryAfter > 90 => (int) ceil($retryAfter / 60).' minutes',
        $retryAfter > 0 => $retryAfter.' '.Str::plural('second', $retryAfter),
        default => 'a minute',
    };
@endphp

@extends('errors.layout')

@section('title', 'Too many tries')
@section('code', '429')
@section('heading', 'Too many tries in a row')
@section('message', 'To keep accounts safe, this is paused for a moment. Please wait '.$wait.', then try again.')

@section('actions')
    <button type="button" onclick="history.length > 1 ? history.back() : location.assign('{{ url('/') }}')" class="btn-primary">Go back</button>
    <a href="{{ url('/') }}" class="btn-ghost">Back to the resort</a>
@endsection
