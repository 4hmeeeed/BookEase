<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        @include('layouts.partials.head', ['title' => $title])
    </head>
    <body class="font-sans antialiased">
        <header @class([
            'z-30',
            'absolute inset-x-0 top-0' => $overlay,
            'sticky top-0 border-b border-sand-200/70 bg-cream/85 backdrop-blur-md' => ! $overlay,
        ])>
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                <x-brand />

                <nav class="flex items-center gap-1 sm:gap-3">
                    <a href="{{ route('cottages.index') }}"
                       @class(['hidden rounded-full px-4 py-2 text-sm font-semibold transition sm:inline-flex',
                               'bg-lagoon-700 text-cream' => request()->routeIs('cottages.*'),
                               'text-gray-700 hover:bg-sand-100 hover:text-lagoon-900' => ! request()->routeIs('cottages.*')])>
                        Cottages
                    </a>

                    @auth
                        <a href="{{ route('dashboard') }}" class="btn-primary">My dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="btn-ghost hidden sm:inline-flex">Log in</a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="btn-coral">Create account</a>
                        @endif
                    @endauth
                </nav>
            </div>
        </header>

        <x-flash />

        <main>
            {{ $slot }}
        </main>

        <footer class="border-t border-sand-200">
            <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-4 px-4 py-8 text-sm text-gray-500 sm:flex-row sm:px-6 lg:px-8">
                <x-brand />
                <nav class="flex gap-5">
                    <a href="{{ route('cottages.index') }}" class="link-underline">Cottages</a>
                    <a href="{{ route('home') }}#how-it-works" class="link-underline">How it works</a>
                    @guest
                        <a href="{{ route('staff.login') }}" class="link-underline">Staff login</a>
                    @endguest
                </nav>
                <p>&copy; {{ date('Y') }} Labnasan Resort · Basey, Samar</p>
            </div>
        </footer>

        <x-chatbot-widget />
    </body>
</html>
