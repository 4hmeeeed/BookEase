@props(['messages'])

{{-- Field error: chart coral with an icon, so it never relies on colour alone. --}}
@if ($messages)
    <ul {{ $attributes->merge(['class' => 'space-y-1 text-sm font-medium text-coral-700']) }}>
        @foreach ((array) $messages as $message)
            <li class="flex items-start gap-1.5 animate-fade-up [animation-duration:.35s]">
                <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-8-5a.75.75 0 0 1 .75.75v4.5a.75.75 0 0 1-1.5 0v-4.5A.75.75 0 0 1 10 5Zm0 10a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" /></svg>
                <span>{{ $message }}</span>
            </li>
        @endforeach
    </ul>
@endif
