@extends('errors.layout')

@section('title', 'No access')
@section('code', '403')
@section('heading', 'This page isn’t part of your account')
@section('message', 'Your account doesn’t have access to this page. Your dashboard has everything you can open.')

@section('actions')
    <a href="{{ url('/dashboard') }}" class="btn-primary">Go to my dashboard</a>
    <a href="{{ url('/') }}" class="btn-ghost">Back to the resort</a>
@endsection
