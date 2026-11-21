{{--
    Shared layout for error pages (404, 419, 429, 500…). Kept independent of the app
    layouts: no session, CSRF or auth, so it renders even when those are what failed.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#faf7f1">
    <meta name="robots" content="noindex">
    <title>@yield('title') · BookEase — Labnasan Resort</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=libre-caslon-display:400|libre-caslon-text:400|hanken-grotesk:400,500,600&display=swap" rel="stylesheet" />
    @php
        // The asset pipeline may be the thing that broke; never let that hide the error page.
        try { echo app(\Illuminate\Foundation\Vite::class)(['resources/css/app.css']); } catch (\Throwable) {}
    @endphp
    <style>body{margin:0;background:#faf7f1;color:#25211d;font-family:"Hanken Grotesk",system-ui,sans-serif}</style>
</head>
<body class="font-sans antialiased">
    <main class="relative grid min-h-dvh place-items-center overflow-hidden px-6 py-16">
        <div class="graticule pointer-events-none absolute inset-0 text-lagoon-950/[0.035]" aria-hidden="true"></div>
        <div class="neatline text-lagoon-950/15" aria-hidden="true"></div>

        <div class="relative w-full max-w-xl animate-fade-up text-center">
            <x-brand :href="url('/')" class="justify-center" />

            <p class="sounding mt-14 text-coral-600">Error @yield('code')</p>
            <h1 class="mt-3 font-display text-[clamp(2.5rem,7vw,3.9rem)] leading-[1.03] text-lagoon-950">@yield('heading')</h1>
            <p class="mx-auto mt-5 max-w-md leading-relaxed text-gray-600">@yield('message')</p>

            <div class="mt-9 flex flex-wrap justify-center gap-3">
                @yield('actions')
            </div>
        </div>
    </main>
</body>
</html>
