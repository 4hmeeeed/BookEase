{{-- Online payment card: choose amount + GCash/Maya, then go to the gateway's hosted checkout. --}}
@use('App\Support\Money')

@php $first = array_key_first($paymentOptions); @endphp

<section class="card overflow-hidden" x-data="{ kind: @js($first), method: 'gcash', sending: false }">
    <div class="border-b border-sand-200 bg-gradient-to-r from-lagoon-800 to-lagoon-700 px-6 py-4 text-cream">
        <h2 class="font-display text-xl font-semibold">Pay securely online</h2>
        <p class="text-sm text-lagoon-100">Your wallet details are entered only on the payment provider's page. BookEase never sees them.</p>
    </div>

    <form method="POST" action="{{ route('guest.payments.store', $booking) }}" class="space-y-5 p-6" @submit="sending = true">
        @csrf

        <fieldset>
            <legend class="text-sm font-semibold text-lagoon-900">Amount</legend>
            <div class="mt-2 grid gap-3 sm:grid-cols-2">
                @foreach ($paymentOptions as $kind => $amount)
                    <label class="cursor-pointer">
                        <input type="radio" name="kind" value="{{ $kind }}" x-model="kind" class="peer sr-only">
                        <span class="block rounded-2xl border-2 border-sand-200 bg-white p-4 transition-all duration-200 ease-out-soft hover:border-lagoon-300 active:scale-[.98]
                                     peer-checked:border-lagoon-600 peer-checked:bg-lagoon-50 peer-checked:shadow-lagoon-glow peer-focus-visible:ring-2 peer-focus-visible:ring-lagoon-400">
                            <span class="block text-sm font-semibold text-gray-700">
                                @switch($kind)
                                    @case('down') Down payment ({{ $downPercent }}%) @break
                                    @case('full') Pay in full @break
                                    @default Remaining balance
                                @endswitch
                            </span>
                            <span class="mt-1 block font-display text-2xl font-semibold text-coral-600">{{ Money::format($amount) }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <fieldset>
            <legend class="text-sm font-semibold text-lagoon-900">Pay with</legend>
            <div class="mt-2 grid grid-cols-2 gap-3">
                @foreach (['gcash' => ['GCash', 'bg-ocean-600'], 'maya' => ['Maya', 'bg-lagoon-600']] as $value => [$label, $dot])
                    <label class="cursor-pointer">
                        <input type="radio" name="method" value="{{ $value }}" x-model="method" class="peer sr-only">
                        <span class="flex items-center gap-3 rounded-2xl border-2 border-sand-200 bg-white px-4 py-3 font-semibold text-gray-800 transition-all duration-200 ease-out-soft hover:border-lagoon-300 active:scale-[.98]
                                     peer-checked:border-lagoon-600 peer-checked:bg-lagoon-50 peer-focus-visible:ring-2 peer-focus-visible:ring-lagoon-400">
                            <span class="grid h-8 w-8 place-items-center rounded-full text-xs font-bold text-white {{ $dot }}">{{ Str::substr($label, 0, 1) }}</span>
                            {{ $label }}
                        </span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <button class="btn-coral w-full py-3 text-base" :disabled="sending">
            <svg x-show="sending" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" stroke-dasharray="40 20" /></svg>
            <span x-text="sending ? 'Opening secure checkout…' : 'Continue to secure checkout'">Continue to secure checkout</span>
        </button>

        <p class="text-center text-xs text-gray-500">
            Your payment is confirmed automatically once the provider notifies us. You'll get an e-receipt right after.
            @unless (config('bookease.payments.allow_live_mode'))
                <span class="mt-1 block font-semibold text-sand-700">Sandbox mode: no real money moves.</span>
            @endunless
        </p>
    </form>
</section>
