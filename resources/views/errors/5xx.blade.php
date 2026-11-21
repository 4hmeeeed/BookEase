{{-- Fallback for any 5xx without its own page. --}}
@extends('errors.layout')

@section('title', 'Something went wrong')
@section('code', isset($exception) && method_exists($exception, 'getStatusCode') ? (string) $exception->getStatusCode() : '5xx')
@section('heading', 'Something went wrong on our side')
@section('message', 'The page couldn’t load. Please try again in a moment. If it keeps happening, contact the resort directly.')

@section('actions')
    <button type="button" onclick="location.reload()" class="btn-primary">Try again</button>
    <a href="{{ url('/') }}" class="btn-ghost">Back to the resort</a>
@endsection
