<x-app-layout>
    <x-slot name="header">
        <x-page-header eyebrow="Welcome back" :title="'Maayong adlaw, '.Str::before(Auth::user()->name, ' ').'!'">
            Plan your stay at Labnasan Resort: browse cottages, check open dates, and keep track of your bookings and receipts.
        </x-page-header>
    </x-slot>

    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3" x-reveal.stagger>
            <x-module-card title="Browse cottages" tone="lagoon" status="Coming soon">
                <x-slot name="icon">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 11 12 4l9 7M5 10v10h14V10M10 20v-5h4v5" /></svg>
                </x-slot>
                See every cottage with its capacity, amenities, and current rates, taken straight from the resort's records.
            </x-module-card>

            <x-module-card title="My bookings" tone="coral" status="Coming soon">
                <x-slot name="icon">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><rect x="3.5" y="5" width="17" height="15" rx="2.5" /><path stroke-linecap="round" d="M3.5 10h17M8 3v4M16 3v4" /></svg>
                </x-slot>
                Follow each reservation from <em>pending</em> to <em>confirmed</em>, and pay the balance before check-in.
            </x-module-card>

            <x-module-card title="Payments & receipts" tone="ocean" status="Coming soon">
                <x-slot name="icon">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><rect x="3" y="6" width="18" height="13" rx="2.5" /><path stroke-linecap="round" d="M3 10h18M7 15h4" /></svg>
                </x-slot>
                Pay securely with GCash or Maya and download an official e-receipt after every successful payment.
            </x-module-card>
        </div>
    </div>
</x-app-layout>
