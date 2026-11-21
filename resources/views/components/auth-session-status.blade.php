@props(['status'])

@if ($status)
    <div role="status" {{ $attributes->merge(['class' => 'flex items-start gap-2.5 rounded-md bg-lagoon-50 px-3.5 py-3 text-sm font-medium text-lagoon-900 ring-1 ring-inset ring-lagoon-200 animate-pop-in']) }}>
        <svg class="mt-0.5 h-4 w-4 shrink-0 text-lagoon-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 5 5 9-10" /></svg>
        <span>{{ $status }}</span>
    </div>
@endif
