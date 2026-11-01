<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('layouts.partials.head')
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="grid min-h-screen lg:grid-cols-[1.05fr_1fr]">
            {{-- Scenic panel (desktop) --}}
            <aside class="relative hidden overflow-hidden bg-lagoon-900 lg:block" aria-hidden="true">
                <div class="absolute inset-0 bg-[linear-gradient(180deg,#f7c59f_0%,#f29c7a_22%,#d9776a_38%,#3d8fcb_58%,#1e5756_78%,#0b2324_100%)]"></div>
                <div class="absolute left-1/2 top-[34%] h-40 w-40 -translate-x-1/2 rounded-full bg-[#ffe3b8] opacity-90 blur-[2px] animate-bob"></div>
                <div class="absolute left-1/2 top-[34%] h-72 w-72 -translate-x-1/2 -translate-y-16 rounded-full bg-[#ffd29a]/40 blur-3xl"></div>

                <div class="absolute inset-x-0 top-[52%] text-ocean-700/70"><x-waves /></div>
                <div class="absolute inset-x-0 top-[62%] text-lagoon-800/80"><x-waves /></div>
                <div class="absolute inset-x-0 bottom-0 h-1/3 bg-gradient-to-b from-lagoon-800/80 to-lagoon-950"></div>

                <div class="absolute inset-x-0 bottom-0 p-12 text-cream">
                    <x-brand light class="mb-8" />
                    <p class="max-w-md font-display text-4xl leading-tight">
                        Sea breeze, warm sand, and a cottage that's <em class="text-coral-300">already yours</em>.
                    </p>
                    <p class="mt-4 max-w-sm text-sm text-sand-200/90">Basey, Samar · Book, pay with GCash or Maya, and get your receipt — all in one place.</p>
                </div>
            </aside>

            {{-- Form side --}}
            <main class="relative flex flex-col items-center justify-center px-4 py-10 sm:px-8">
                <div class="pointer-events-none absolute -right-24 top-10 h-72 w-72 rounded-full bg-coral-200/40 blur-3xl" aria-hidden="true"></div>

                <x-brand class="mb-8 lg:hidden" />

                <div class="card relative w-full max-w-md animate-pop-in p-6 sm:p-8">
                    {{ $slot }}
                </div>

                <a href="{{ route('home') }}" class="link-underline mt-6 text-sm">&larr; Back to the resort</a>
            </main>
        </div>
    </body>
</html>
