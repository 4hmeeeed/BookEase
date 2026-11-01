<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('layouts.partials.head')
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="grid min-h-screen lg:grid-cols-[1.05fr_1fr]">
            {{-- Photo panel (desktop): a framed chart plate --}}
            <aside class="relative hidden overflow-hidden bg-lagoon-950 lg:block" aria-hidden="true">
                <div class="hero-plate absolute inset-0">
                    {{-- Stock placeholder (Unsplash) — see public/images/stock/CREDITS.md --}}
                    <img src="{{ asset('images/stock/banca-dusk.webp') }}" alt="" class="tide h-full w-full object-cover">
                </div>
                <div class="absolute inset-0 bg-gradient-to-t from-lagoon-950 via-lagoon-950/30 to-transparent"></div>
                <div class="neatline text-cream/25"></div>

                <div class="absolute left-10 top-10 sounding text-cream/70">11&deg;17&prime;N &nbsp;125&deg;04&prime;E</div>

                <div class="absolute inset-x-0 bottom-0 p-12 text-cream">
                    <x-brand light class="mb-10" />
                    <p class="max-w-md font-display text-[2.6rem] leading-[1.08]">
                        Sea breeze, warm sand, and a cottage that's already yours.
                    </p>
                    <p class="mt-5 max-w-sm text-sm leading-relaxed text-cream/70">Basey, Samar · Book, pay with GCash or Maya, and get your receipt, all in one place.</p>
                </div>
            </aside>

            {{-- Form side --}}
            <main class="relative flex flex-col items-center justify-center px-4 py-10 sm:px-8">
                <x-brand class="mb-10 lg:hidden" />

                <div class="w-full max-w-md animate-pop-in">
                    {{ $slot }}
                </div>

                <a href="{{ route('home') }}" class="link-underline mt-10 text-sm">&larr; Back to the resort</a>
            </main>
        </div>
    </body>
</html>
