<x-app-layout>
    <x-slot name="header">
        <x-page-header eyebrow="Front desk" title="Today at Labnasan">
            Approve reservations, verify payments, and handle check-ins and check-outs.
        </x-page-header>
    </x-slot>

    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4" x-reveal.stagger>
            <x-module-card title="Pending requests" tone="sand" status="Step 4">
                <x-slot name="icon">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="8.5" /><path stroke-linecap="round" d="M12 7.5V12l3 2" /></svg>
                </x-slot>
                New reservations waiting for approval or rejection.
            </x-module-card>

            <x-module-card title="Arrivals" tone="lagoon" status="Step 4">
                <x-slot name="icon">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M14 4h4a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-4M10 16l4-4-4-4M14 12H4" /></svg>
                </x-slot>
                Confirmed guests checking in today (from 2:00 PM).
            </x-module-card>

            <x-module-card title="Departures" tone="coral" status="Step 4">
                <x-slot name="icon">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M10 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h4M16 16l4-4-4-4M20 12H10" /></svg>
                </x-slot>
                Guests due to check out by 12:00 NN.
            </x-module-card>

            <x-module-card title="Payments" tone="ocean" status="Step 5">
                <x-slot name="icon">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><rect x="3" y="6" width="18" height="13" rx="2.5" /><path stroke-linecap="round" d="M3 10h18M7 15h4" /></svg>
                </x-slot>
                GCash and Maya payments confirmed by the gateway.
            </x-module-card>
        </div>
    </div>
</x-app-layout>
