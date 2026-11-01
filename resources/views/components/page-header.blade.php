@props(['eyebrow' => null, 'title'])

<div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div>
        @if ($eyebrow)
            <p class="eyebrow">{{ $eyebrow }}</p>
        @endif
        <h1 class="mt-2 font-display text-3xl font-semibold text-lagoon-950 sm:text-4xl">{{ $title }}</h1>
        @if ($slot->isNotEmpty())
            <p class="mt-2 max-w-2xl text-gray-600">{{ $slot }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex flex-wrap gap-3">{{ $actions }}</div>
    @endisset
</div>
