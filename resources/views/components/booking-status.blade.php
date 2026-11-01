@props(['status'])

<span {{ $attributes->merge(['class' => 'badge '.$status->badgeClass()]) }}>
    <span @class(['badge-dot', 'is-live' => in_array($status->value, ['pending', 'checked_in'], true)])></span>{{ $status->label() }}
</span>
