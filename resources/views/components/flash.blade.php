{{-- Session flash toast: slides in, auto-dismisses after 5 s, pauses on hover. --}}
@php
    $message = session('status') ?? session('error');
    $isError = session()->has('error');
@endphp

@if ($message && ! in_array($message, ['profile-updated', 'password-updated', 'verification-link-sent'], true))
    <div x-data="{ show: false, timer: null,
                   start() { this.timer = setTimeout(() => this.show = false, 5000) },
                   stop() { clearTimeout(this.timer) } }"
         x-init="$nextTick(() => { show = true; start() })"
         @mouseenter="stop()" @mouseleave="start()"
         class="pointer-events-none fixed inset-x-4 top-20 z-50 flex justify-end sm:inset-x-auto sm:right-6"
         role="{{ $isError ? 'alert' : 'status' }}">
        <div x-show="show" x-cloak
             x-transition:enter="transition ease-spring duration-500" x-transition:enter-start="opacity-0 translate-x-8 scale-95" x-transition:enter-end="opacity-100 translate-x-0 scale-100"
             x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0 translate-x-4"
             @class([
                 'pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-2xl border p-4 shadow-lift',
                 'border-coral-200 bg-coral-50 text-coral-900' => $isError,
                 'border-lagoon-200 bg-cream text-lagoon-900' => ! $isError,
             ])>
            <span @class(['mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-full text-white', 'bg-coral-500' => $isError, 'bg-lagoon-600' => ! $isError])>
                @if ($isError)
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" d="M12 7v6M12 17h.01" /></svg>
                @else
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 5 5 9-10" /></svg>
                @endif
            </span>
            <p class="flex-1 text-sm font-medium">{{ $message }}</p>
            <button type="button" @click="show = false" class="rounded-md p-1 opacity-60 transition hover:bg-black/5 hover:opacity-100" aria-label="Dismiss">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18" /></svg>
            </button>
        </div>
    </div>
@endif
