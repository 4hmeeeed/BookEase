<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Your account">
            @if ($user->isGuest())
                Your name and email appear on your bookings and e-receipts.
            @else
                Your name appears on the bookings and payments you handle.
            @endif
        </x-page-header>
    </x-slot>

    {{-- Settings rows: what it is on the left, the form on the right. --}}
    <div class="mx-auto max-w-7xl divide-y divide-lagoon-950/10 px-4 pb-20 sm:px-6 lg:px-8">
        @include('profile.partials.update-profile-information-form')
        @include('profile.partials.update-password-form')

        {{-- Staff and owner accounts are never deleted (App\Services\AccountDeletion), so only guests see this. --}}
        @if ($user->isGuest())
            @include('profile.partials.delete-user-form')
        @endif
    </div>
</x-app-layout>
