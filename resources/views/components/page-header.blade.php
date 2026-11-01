{{-- `eyebrow` is accepted for older call sites but intentionally not shown: the heading speaks for itself. --}}
@props(['eyebrow' => null, 'title'])

<div class="flex flex-col gap-4 border-b border-lagoon-950/10 pb-6 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <h1 class="font-display text-[2.1rem] leading-[1.05] text-lagoon-950 sm:text-5xl">{{ $title }}</h1>
        @if ($slot->isNotEmpty())
            <p class="mt-3 max-w-2xl text-gray-600">{{ $slot }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex flex-wrap gap-3">{{ $actions }}</div>
    @endisset
</div>
