<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('layouts.partials.head')
    </head>
    <body class="font-sans antialiased">
        <div class="relative min-h-screen overflow-x-clip">
            {{-- soft sun-wash in the corner --}}
            <div class="pointer-events-none absolute -right-40 -top-40 h-[28rem] w-[28rem] rounded-full bg-coral-200/40 blur-3xl" aria-hidden="true"></div>
            <div class="pointer-events-none absolute -left-32 top-64 h-80 w-80 rounded-full bg-lagoon-200/30 blur-3xl" aria-hidden="true"></div>

            @include('layouts.navigation')

            <x-flash />

            @isset($header)
                <header class="relative">
                    <div class="mx-auto max-w-7xl px-4 pb-2 pt-8 animate-fade-up sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <main class="relative animate-fade-up [animation-delay:80ms]">
                {{ $slot }}
            </main>
        </div>

        {{-- Guests get the inquiry assistant; staff and the owner review its log instead. --}}
        @if (auth()->user()?->isGuest())
            <x-chatbot-widget />
        @endif
    </body>
</html>
