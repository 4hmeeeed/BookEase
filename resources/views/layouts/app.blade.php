<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('layouts.partials.head')
    </head>
    <body class="font-sans antialiased">
        <a href="#main" class="skip-link">Skip to content</a>

        <div class="relative min-h-screen overflow-x-clip">

            @include('layouts.navigation')

            <x-flash />

            {{-- The page header lives inside <main> so the h1 is part of the main landmark. --}}
            <main id="main" tabindex="-1" class="scroll-mt-16 focus:outline-none">
                @isset($header)
                    <header class="relative">
                        <div class="mx-auto max-w-7xl px-4 pb-2 pt-8 animate-fade-up sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                <div @class(['relative animate-fade-up [animation-delay:80ms]', 'pb-24' => auth()->user()?->isGuest()])>
                    {{ $slot }}
                </div>
            </main>
        </div>

        {{-- Guests get the inquiry assistant; staff and the owner review its log instead. --}}
        @if (auth()->user()?->isGuest())
            <x-chatbot-widget />
        @endif
    </body>
</html>
