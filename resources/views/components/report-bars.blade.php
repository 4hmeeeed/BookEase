@props([
    'title',
    'caption' => null,
    // list of ['label', 'title', 'value' (tooltip/table text), 'height' (0–100), 'muted' (bool)]
    'bars' => [],
    'ticks' => ['100%', '50%', '0'],
    'valueHeading' => 'Value',
])

@php
    $count = count($bars);
    $labelEvery = $count > 16 ? (int) ceil($count / 8) : 1;
@endphp

<figure {{ $attributes->merge(['class' => 'card p-5 sm:p-6']) }}>
    <figcaption class="flex flex-wrap items-baseline justify-between gap-2">
        <span class="font-serif text-lg font-semibold text-lagoon-950">{{ $title }}</span>
        @if ($caption)
            <span class="text-xs text-gray-500">{{ $caption }}</span>
        @endif
    </figcaption>

    @if ($count === 0)
        <p class="mt-6 text-sm text-gray-500">No data for this period.</p>
    @else
        <div class="mt-5 grid grid-cols-[auto_1fr] gap-x-2">
            {{-- y-axis ticks (recessive) --}}
            <div class="flex h-44 flex-col justify-between text-right text-[10px] tabular-nums text-gray-400" aria-hidden="true">
                @foreach ($ticks as $tick)
                    <span class="-translate-y-1/2 first:translate-y-0 last:translate-y-0">{{ $tick }}</span>
                @endforeach
            </div>

            <div class="relative h-44">
                {{-- grid lines --}}
                <div class="pointer-events-none absolute inset-0 flex flex-col justify-between" aria-hidden="true">
                    @foreach ($ticks as $tick)
                        <span @class(['block h-px', 'bg-gray-300' => $loop->last, 'bg-sand-200/70' => ! $loop->last])></span>
                    @endforeach
                </div>

                <ol class="relative flex h-full items-end gap-[2px]" role="list" aria-label="{{ $title }}">
                    @foreach ($bars as $bar)
                        <li class="group relative flex h-full flex-1 items-end justify-center outline-none" tabindex="0"
                            aria-label="{{ $bar['title'] }}: {{ $bar['value'] }}">
                            <span @class([
                                    'block w-full max-w-[2.5rem] origin-bottom rounded-t-[4px] transition-[filter,height] duration-500 ease-out-soft group-hover:brightness-110 group-focus-visible:ring-2 group-focus-visible:ring-lagoon-400',
                                    'bg-lagoon-600' => empty($bar['muted']),
                                    'bg-lagoon-400' => ! empty($bar['muted']),
                                  ])
                                  style="height: {{ max(0, min(100, (float) $bar['height'])) }}%; {{ (float) $bar['height'] > 0 ? 'min-height: 2px;' : '' }}"></span>

                            {{-- tooltip --}}
                            <span class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-2 w-max max-w-[12rem] -translate-x-1/2 translate-y-1 rounded-xl bg-lagoon-950 px-3 py-2 text-xs text-cream opacity-0 shadow-lift transition-[opacity,transform] duration-200 ease-out-soft group-hover:translate-y-0 group-hover:opacity-100 group-focus:translate-y-0 group-focus:opacity-100">
                                <span class="block text-lagoon-200">{{ $bar['title'] }}</span>
                                <span class="block font-semibold tabular-nums">{{ $bar['value'] }}</span>
                            </span>
                        </li>
                    @endforeach
                </ol>
            </div>

            {{-- x-axis labels --}}
            <span></span>
            <div class="mt-1.5 flex gap-[2px] text-[10px] tabular-nums text-gray-500" aria-hidden="true">
                @foreach ($bars as $bar)
                    <span class="flex-1 text-center">{{ $loop->index % $labelEvery === 0 ? $bar['label'] : '' }}</span>
                @endforeach
            </div>
        </div>

        <details class="mt-4 text-sm print:hidden">
            <summary class="cursor-pointer text-xs font-semibold text-lagoon-700 hover:text-lagoon-900">Show as table</summary>
            <div class="mt-2 max-h-64 overflow-auto rounded-xl border border-sand-200">
                <table class="w-full text-left text-xs">
                    <thead class="sticky top-0 bg-sand-50 text-gray-600">
                        <tr><th class="px-3 py-2 font-semibold">Period</th><th class="px-3 py-2 text-right font-semibold">{{ $valueHeading }}</th></tr>
                    </thead>
                    <tbody class="divide-y divide-sand-100">
                        @foreach ($bars as $bar)
                            <tr><td class="px-3 py-1.5">{{ $bar['title'] }}</td><td class="px-3 py-1.5 text-right tabular-nums">{{ $bar['value'] }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    @endif
</figure>
