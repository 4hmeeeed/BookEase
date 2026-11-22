{{-- Owner-only server checks and maintenance (stands in for SSH on InfinityFree).
     Plain first version from the backend session; the UI session restyles it. --}}
@php
    $ok = fn (bool $pass) => $pass ? 'text-lagoon-800' : 'font-semibold text-coral-700';
    $mark = fn (bool $pass) => $pass ? 'OK' : 'Missing';
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header eyebrow="Owner console" title="System">
            Checks that this server can run BookEase, plus the maintenance tasks that would otherwise need a command line.
        </x-page-header>
    </x-slot>

    <div class="mx-auto max-w-5xl space-y-8 px-4 py-8 sm:px-6">
        <section class="rounded-2xl border border-lagoon-950/10 bg-white p-6">
            <h2 class="font-serif text-xl text-lagoon-950">Server</h2>
            <dl class="mt-4 grid gap-x-8 gap-y-2 text-sm sm:grid-cols-2">
                <dt>PHP version</dt>
                <dd class="{{ $ok($checks['php_ok']) }}">{{ $checks['php'] }} {{ $checks['php_ok'] ? '' : '(needs 8.2 or newer)' }}</dd>
                @foreach ($checks['extensions'] as $extension => $loaded)
                    <dt>PHP extension {{ $extension }}</dt>
                    <dd class="{{ $ok($loaded) }}">{{ $mark($loaded) }}</dd>
                @endforeach
                @foreach ($checks['writable'] as $folder => $writable)
                    <dt>{{ $folder }}</dt>
                    <dd class="{{ $ok($writable) }}">{{ $writable ? 'Writable' : 'Not writable' }}</dd>
                @endforeach
            </dl>
        </section>

        <section class="rounded-2xl border border-lagoon-950/10 bg-white p-6">
            <h2 class="font-serif text-xl text-lagoon-950">This visit</h2>
            <dl class="mt-4 grid gap-x-8 gap-y-2 text-sm sm:grid-cols-2">
                <dt>Your address as the app sees it</dt>
                <dd>{{ $checks['visitor_ip'] }}</dd>
                <dt>Connecting address (REMOTE_ADDR)</dt>
                <dd>{{ $checks['remote_addr'] }}</dd>
                <dt>X-Forwarded-For header</dt>
                <dd>{{ $checks['forwarded_for'] ?? 'none' }}</dd>
                <dt>Secure connection (HTTPS)</dt>
                <dd class="{{ $ok($checks['https']) }}">{{ $checks['https'] ? 'Yes' : 'No' }}</dd>
            </dl>
            <p class="mt-3 text-sm text-sand-700">
                If "your address" shows the hosting company's proxy rather than your own internet address, set
                <code>TRUSTED_PROXIES=*</code> in <code>.env</code>, so that rate limits apply to each visitor separately.
            </p>
        </section>

        <section class="rounded-2xl border border-lagoon-950/10 bg-white p-6">
            <h2 class="font-serif text-xl text-lagoon-950">Settings in use</h2>
            <dl class="mt-4 grid gap-x-8 gap-y-2 text-sm sm:grid-cols-2">
                <dt>Environment</dt>
                <dd class="{{ $ok($checks['environment'] === 'production' || ! $checks['debug']) }}">{{ $checks['environment'] }}{{ $checks['debug'] ? ' · debug ON (turn off in production)' : '' }}</dd>
                <dt>Site address (APP_URL)</dt>
                <dd>{{ $checks['url'] }}</dd>
                <dt>Online payments</dt>
                <dd>{{ $checks['payments_online'] ? 'On ('.$checks['payment_driver'].')' : 'Off · guests pay at the front desk' }}</dd>
                @foreach ($checks['drivers'] as $what => $driver)
                    <dt>{{ ucfirst($what) }} driver</dt>
                    <dd>{{ $driver }}</dd>
                @endforeach
                <dt>Database updates waiting</dt>
                <dd class="{{ $ok($checks['pending_migrations'] === []) }}">
                    {{ $checks['pending_migrations'] === null ? 'Could not read the database' : (count($checks['pending_migrations']) ?: 'None') }}
                </dd>
                <dt>Daily clean-up</dt>
                <dd>{{ $checks['cleanup_on_requests'] ? 'Runs from page visits' : 'Left to the scheduler (cron)' }}{{ $checks['last_cleanup'] ? ' · last run '.\Illuminate\Support\Carbon::parse($checks['last_cleanup'])->diffForHumans() : '' }}</dd>
            </dl>
        </section>

        <section class="rounded-2xl border border-lagoon-950/10 bg-white p-6">
            <h2 class="font-serif text-xl text-lagoon-950">Maintenance</h2>
            <p class="mt-1 text-sm text-sand-700">Each button asks for your password again.</p>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                @foreach ([
                    ['system.test-email', 'Send a test email', 'Sends a test message to your own address to prove email works.'],
                    ['system.migrate', 'Run database updates', 'Applies database changes that came with an update. Back up the database first.'],
                    ['system.clear-caches', 'Clear caches', 'Use after uploading new files, if pages look out of date.'],
                    ['system.cleanup', 'Run clean-up now', 'Deletes staff alerts read more than 90 days ago, or left unread for a year.'],
                ] as [$route, $label, $help])
                    <form method="POST" action="{{ route('admin.'.$route) }}" class="rounded-xl border border-lagoon-950/10 p-4">
                        @csrf
                        <p class="text-sm text-sand-700">{{ $help }}</p>
                        <x-secondary-button type="submit" class="mt-3">{{ $label }}</x-secondary-button>
                    </form>
                @endforeach
            </div>
        </section>
    </div>
</x-app-layout>
