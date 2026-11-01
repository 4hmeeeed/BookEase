<x-app-layout>
    <x-slot name="header">
        <x-page-header eyebrow="Owner console" title="Resort overview">
            Manage cottages, rates, staff accounts, and chatbot logs, and see how the resort is doing.
        </x-page-header>
    </x-slot>

    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3" x-reveal.stagger>
            <x-module-card title="Cottages & rates" tone="lagoon" :href="route('admin.cottages.index')">
                <x-slot name="icon">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 11 12 4l9 7M5 10v10h14V10M10 20v-5h4v5" /></svg>
                </x-slot>
                Add and edit cottages, capacities, photos, and nightly rates.
            </x-module-card>

            <x-module-card title="Staff accounts" tone="sand" status="Step 7">
                <x-slot name="icon">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="8" r="3.5" /><path stroke-linecap="round" d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.5a3.5 3.5 0 0 1 0 7M18 14.5a6.5 6.5 0 0 1 3.5 5.5" /></svg>
                </x-slot>
                Create, deactivate, and review front-desk staff accounts.
            </x-module-card>

            <x-module-card title="Reports" tone="ocean" status="Step 7">
                <x-slot name="icon">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 20V10M10 20V4M16 20v-7M22 20H2" /></svg>
                </x-slot>
                Occupancy, revenue, and booking trends.
            </x-module-card>

            <x-module-card title="Chatbot audit log" tone="coral" status="Step 6">
                <x-slot name="icon">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16v11H9l-5 4V5Z" /><path stroke-linecap="round" d="M8 9h8M8 12h5" /></svg>
                </x-slot>
                Every guest question with the context and answer the assistant gave.
            </x-module-card>

            <x-module-card title="Front desk" tone="lagoon" :href="route('staff.dashboard')">
                <x-slot name="icon">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6" /></svg>
                </x-slot>
                Jump into the staff view to approve bookings or cover the desk.
            </x-module-card>
        </div>
    </div>
</x-app-layout>
