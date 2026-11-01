<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('layouts.partials.head')
    </head>
    <body class="font-sans antialiased">
        <div class="relative min-h-screen overflow-x-clip">

            @include('layouts.navigation')

            <x-flash />

            @isset($header)
                <header class="relative">
                    <div class="mx-auto max-w-7xl px-4 pb-2 pt-8 animate-fade-up sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <main @class(['relative animate-fade-up [animation-delay:80ms]', 'pb-24' => auth()->user()?->isGuest()])>
                {{ $slot }}
            </main>
        </div>

        {{-- Guests get the inquiry assistant; staff and the owner review its log instead. --}}
        @if (auth()->user()?->isGuest())
            <x-chatbot-widget />
        @endif
    </body>
</html>
