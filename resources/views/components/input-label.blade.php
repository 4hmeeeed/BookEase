@props(['value'])

<label {{ $attributes->merge(['class' => 'block text-sm font-semibold text-lagoon-900']) }}>
    {{ $value ?? $slot }}
</label>
