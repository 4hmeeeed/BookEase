<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ config('app.name') }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
@php
    // Times come from config (BOOKEASE_CHECK_IN_TIME / _OUT_TIME); noon reads "12:00 NN" as at the resort.
    $clock = function (?string $time, string $fallback): string {
        try {
            $t = \Illuminate\Support\Carbon::createFromFormat('H:i', $time ?: $fallback);
        } catch (\Throwable) {
            $t = \Illuminate\Support\Carbon::createFromFormat('H:i', $fallback);
        }

        return $t->format('H:i') === '12:00' ? '12:00 NN' : $t->format('g:i A');
    };
@endphp
Labnasan Resort · Barangay Bacubac, Basey, Samar
<br>
Check-in from {{ $clock(config('bookease.check_in_time'), '14:00') }} · Check-out by {{ $clock(config('bookease.check_out_time'), '12:00') }}
<br>
© {{ date('Y') }} {{ config('app.name') }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
