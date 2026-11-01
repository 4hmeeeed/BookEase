@props(['disabled' => false])

@php
    // Flag the field as invalid when the validator returned an error for it,
    // which triggers the coral border + nudge animation in app.css.
    $name = $attributes->get('name');
    $invalid = $name && isset($errors) && $errors->has($name);
@endphp

<input @disabled($disabled) @if($invalid) aria-invalid="true" @endif {{ $attributes->merge(['class' => 'field']) }}>
