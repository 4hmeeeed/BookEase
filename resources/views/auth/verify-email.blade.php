<x-guest-layout>
    <div class="mb-8 short:mb-5">
        <h1 class="font-display text-[2.6rem] leading-[1.05] text-lagoon-950">Check your email</h1>
        <p class="mt-3 text-sm leading-relaxed text-gray-600 short:mt-2">
            We sent a verification link to <span class="font-semibold text-lagoon-950">{{ auth()->user()->email }}</span>.
            Open it to finish setting up your account.
        </p>
    </div>

    @if (session('status') == 'verification-link-sent')
        <x-auth-session-status class="mb-5" status="A new link is on its way. Check your inbox and spam folder." />
    @endif

    <form method="POST" action="{{ route('verification.send') }}">
        @csrf
        <x-primary-button class="w-full py-3">Send the link again</x-primary-button>
    </form>

    <x-slot name="footer">
        <div class="mt-8 flex flex-wrap items-center justify-between gap-x-6 gap-y-2 border-t border-lagoon-950/10 pt-6 text-sm text-gray-600 short:mt-5 short:pt-4">
            <a href="{{ route('home') }}" class="link-underline">&larr; Back to the resort</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="link-underline font-semibold text-lagoon-900">Log out</button>
            </form>
        </div>
    </x-slot>
</x-guest-layout>
