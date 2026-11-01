@props(['href' => route('home'), 'light' => false])

<a href="{{ $href }}" {{ $attributes->merge(['class' => 'group inline-flex items-center gap-3']) }}>
    <x-application-logo @class([
        'h-9 w-9 transition-transform duration-700 ease-expo group-hover:rotate-[30deg]',
        'text-cream' => $light,
        'text-lagoon-900' => ! $light,
    ]) />
    <span class="leading-none">
        <span @class(['block font-serif text-[1.3rem] leading-none tracking-[-0.01em]', 'text-cream' => $light, 'text-lagoon-950' => ! $light])>BookEase</span>
        <span @class(['mt-1 block text-[0.6rem] font-semibold uppercase tracking-[0.26em]', 'text-cream/70' => $light, 'text-lagoon-700' => ! $light])>Labnasan Resort</span>
    </span>
</a>
