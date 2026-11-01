<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        @include('layouts.partials.head', ['title' => $title])
    </head>
    <body class="font-sans antialiased">
        <!--
            THESIS: The landing is the resort's own chart of the bay: live cottages and the noon changeover plotted on a 3D plate, refusing the stock "full-bleed photo + three icon cards" resort template.
            OWN-WORLD: chart-paper cream, lagoon-950 ink, buff sand land, teal water, coral only as chart markers; hairline neatlines, graticules, sounding numerals; Libre Caslon Display over Hanken Grotesk; 3-8px drafted corners.
            STORY: a guest sees the shore, sets dates in the dock, sees live cottages and rates, understands the 12 NN / 2 PM changeover and the GCash/Maya down payment, then books or asks the assistant.
            FIRST VIEWPORT: full-bleed looping dusk shoreline under a ticked neatline; Caslon headline about 6rem, lower left, ink on pale sky; booking dock (check-in, check-out, guests, "See open cottages") across the bottom over the foam; coordinates on the frame.
            FORM: nautical chart / tide table, position 4 of 7, seed 449b20b1.
            FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance
        -->
        <header @class([
            'z-30',
            'absolute inset-x-0 top-0' => $overlay,
            'sticky top-0 border-b border-lagoon-950/10 bg-cream/90 backdrop-blur-md' => ! $overlay,
        ])>
            <div @class(['mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8', 'py-6 sm:py-8' => $overlay, 'py-3.5' => ! $overlay])>
                <x-brand />

                <nav class="flex items-center gap-1 sm:gap-2">
                    <x-nav-link :href="route('cottages.index')" :active="request()->routeIs('cottages.*')" class="hidden sm:inline-flex">Cottages</x-nav-link>
                    <x-nav-link :href="route('home').'#changeover'" :active="false" class="hidden md:inline-flex">How booking works</x-nav-link>

                    @auth
                        <a href="{{ route('dashboard') }}" class="btn-coral ml-2">My dashboard</a>
                    @else
                        <x-nav-link :href="route('login')" :active="false">Log in</x-nav-link>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="btn-coral ml-2 hidden sm:inline-flex">Create account</a>
                        @endif
                    @endauth
                </nav>
            </div>
        </header>

        <x-flash />

        <main>
            {{ $slot }}
        </main>

        <footer class="relative overflow-hidden bg-lagoon-950 text-cream/75">
            <div class="neatline text-cream/15" aria-hidden="true"></div>
            <div class="relative mx-auto max-w-7xl px-6 pb-10 pt-14 sm:px-10 lg:px-12">
                <div class="grid gap-10 md:grid-cols-[1.4fr_1fr_1fr]">
                    <div>
                        <x-brand light />
                        <p class="mt-5 max-w-sm text-sm leading-relaxed text-cream/65">
                            Barangay Bacubac, Basey, Samar. Reserve a cottage, pay the down payment with GCash or Maya, and keep your e-receipt.
                        </p>
                    </div>
                    <nav class="flex flex-col gap-2.5 text-sm" aria-label="Footer">
                        <span class="sounding mb-1 text-cream/40">Visit</span>
                        <a href="{{ route('cottages.index') }}" class="link-underline w-fit text-cream/80 hover:text-cream">Cottages &amp; rates</a>
                        <a href="{{ route('home') }}#changeover" class="link-underline w-fit text-cream/80 hover:text-cream">How booking works</a>
                        @guest
                            <a href="{{ route('login') }}" class="link-underline w-fit text-cream/80 hover:text-cream">Guest sign-in</a>
                            <a href="{{ route('staff.login') }}" class="link-underline w-fit text-cream/80 hover:text-cream">Staff login</a>
                        @endguest
                    </nav>
                    <div class="text-sm">
                        <span class="sounding text-cream/40">Times</span>
                        <dl class="tabular mt-3 grid grid-cols-[auto_1fr] gap-x-6 gap-y-2 text-cream/80">
                            <dt>Check-in</dt><dd>2:00 PM</dd>
                            <dt>Check-out</dt><dd>12:00 NN</dd>
                        </dl>
                    </div>
                </div>

                <div class="sounding mt-14 flex flex-col justify-between gap-3 border-t border-cream/10 pt-6 text-cream/40 sm:flex-row">
                    <span>&copy; {{ date('Y') }} Labnasan Resort · Basey, Samar</span>
                    <span>11&deg;17&prime;N &nbsp;125&deg;04&prime;E</span>
                </div>
            </div>
        </footer>

        <x-chatbot-widget />
    </body>
</html>
