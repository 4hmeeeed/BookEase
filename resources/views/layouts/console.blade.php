<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('layouts.partials.head')
    </head>
    {{-- The staff/owner console: sidebar on desktop, tab bar on phones. Guests use layouts.app. --}}
    <body class="font-sans antialiased">
        <a href="#main" class="skip-link">Skip to content</a>

        @include('layouts.partials.console-sidebar')

        <div class="relative min-h-screen overflow-x-clip lg:pl-64">
            @include('layouts.partials.console-topbar')

            <x-flash />

            {{-- The page header lives inside <main> so the h1 is part of the main landmark. --}}
            <main id="main" tabindex="-1" class="scroll-mt-16 pb-[calc(4.5rem+env(safe-area-inset-bottom))] focus:outline-none lg:pb-0">
                @isset($header)
                    <header class="relative">
                        <div class="mx-auto max-w-7xl px-4 pb-2 pt-8 animate-fade-up sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                <div class="relative animate-fade-up [animation-delay:80ms]">
                    {{ $slot }}
                </div>
            </main>
        </div>

        @include('layouts.partials.console-tabbar')
    </body>
</html>
