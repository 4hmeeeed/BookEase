@props(['href' => route('home'), 'light' => false])

<a href="{{ $href }}" {{ $attributes->merge(['class' => 'group inline-flex items-center gap-2.5']) }}>
    <x-application-logo @class([
        'h-9 w-9 transition-transform duration-500 ease-spring group-hover:-rotate-6 group-hover:scale-110',
        'text-cream' => $light,
        'text-lagoon-700' => ! $light,
    ]) />
    <span class="leading-none">
        <span @class(['block font-display text-xl font-semibold', 'text-cream' => $light, 'text-lagoon-900' => ! $light])>BookEase</span>
        <span @class(['block text-[0.65rem] font-semibold uppercase tracking-[0.22em]', 'text-sand-200' => $light, 'text-sand-600' => ! $light])>Labnasan Resort</span>
    </span>
</a>
