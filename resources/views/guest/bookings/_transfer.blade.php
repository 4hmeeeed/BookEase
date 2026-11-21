{{-- GCash/Maya straight to the resort's wallet: send, then upload the receipt for the front desk to verify. --}}
@use('App\Support\Money')

@php
    $firstKind = array_key_first($transferOptions);
    $firstWallet = array_key_first($wallets);
@endphp

<section class="card overflow-hidden" aria-labelledby="transfer-heading"
         x-data="{ kind: @js(old('kind', $firstKind)), wallet: @js(old('method', $firstWallet)), sending: false, fileName: '' }">
    <div class="border-b border-sand-200 bg-gradient-to-r from-lagoon-800 to-lagoon-700 px-6 py-4 text-cream">
        <h2 id="transfer-heading" class="font-serif text-xl font-semibold">Pay with GCash or Maya</h2>
        <p class="text-sm text-lagoon-100">Send the amount to the resort’s wallet, then upload your receipt. The front desk confirms it and emails your e-receipt.</p>
    </div>

    <form method="POST" action="{{ route('guest.proofs.store', $booking) }}" enctype="multipart/form-data" data-no-busy class="space-y-6 p-6" @submit="sending = true">
        @csrf

        <x-input-error :messages="$errors->get('transfer')" role="alert" />

        {{-- 1. Amount --}}
        <fieldset>
            <legend class="text-sm font-semibold text-lagoon-900"><span class="tabular text-gray-500">1.</span> Choose the amount</legend>
            <div class="mt-2 grid gap-3 sm:grid-cols-2">
                @foreach ($transferOptions as $kind => $amount)
                    <label class="cursor-pointer">
                        <input type="radio" name="kind" value="{{ $kind }}" x-model="kind" class="peer sr-only">
                        <span class="block rounded-lg border-2 border-lagoon-950/10 bg-white p-4 transition-[border-color,background-color,transform] duration-200 ease-out-soft hover:border-lagoon-950/25 active:scale-[.98]
                                     peer-checked:border-lagoon-700 peer-checked:bg-lagoon-50 peer-focus-visible:ring-2 peer-focus-visible:ring-lagoon-400 peer-focus-visible:ring-offset-2">
                            <span class="block text-sm font-semibold text-gray-700">
                                @switch($kind)
                                    @case('down') Down payment ({{ $downPercent }}%) @break
                                    @case('full') Pay in full @break
                                    @default Remaining balance
                                @endswitch
                            </span>
                            <span class="mt-1 block text-2xl font-semibold tabular-nums tracking-tight text-lagoon-950">{{ Money::format($amount) }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        {{-- 2. Wallet + where to send --}}
        <fieldset>
            <legend class="text-sm font-semibold text-lagoon-900"><span class="tabular text-gray-500">2.</span> Send it to the resort</legend>
            <div class="mt-2 grid grid-cols-2 gap-3">
                @foreach ($wallets as $value => $wallet)
                    <label class="cursor-pointer">
                        <input type="radio" name="method" value="{{ $value }}" x-model="wallet" class="peer sr-only">
                        <span class="flex items-center gap-3 rounded-lg border-2 border-lagoon-950/10 bg-white px-4 py-3 font-semibold text-gray-800 transition-[border-color,background-color,transform] duration-200 ease-out-soft hover:border-lagoon-950/25 active:scale-[.98]
                                     peer-checked:border-lagoon-700 peer-checked:bg-lagoon-50 peer-focus-visible:ring-2 peer-focus-visible:ring-lagoon-400 peer-focus-visible:ring-offset-2">
                            <span @class(['grid h-8 w-8 place-items-center rounded-full text-xs font-bold text-white', 'bg-ocean-600' => $value === 'gcash', 'bg-lagoon-600' => $value !== 'gcash'])>{{ Str::substr($wallet['label'], 0, 1) }}</span>
                            {{ $wallet['label'] }}
                        </span>
                    </label>
                @endforeach
            </div>

            @foreach ($wallets as $value => $wallet)
                <div x-show="wallet === @js($value)" @if ($value !== $firstWallet) x-cloak @endif
                     class="mt-3 flex flex-col gap-4 rounded-lg border border-sand-200 bg-sand-50 p-4 sm:flex-row sm:items-center">
                    @if ($wallet['qr'])
                        <img src="{{ $wallet['qr'] }}" alt="{{ $wallet['label'] }} QR code for Labnasan Resort" class="h-36 w-36 shrink-0 rounded-md border border-sand-200 bg-white object-contain p-1" loading="lazy">
                    @endif
                    <dl class="grid gap-2 text-sm">
                        <div><dt class="text-gray-500">{{ $wallet['label'] }} number</dt><dd class="font-mono text-lg font-semibold tracking-wider text-lagoon-950">{{ $wallet['number'] }}</dd></div>
                        @if ($wallet['name'])
                            <div><dt class="text-gray-500">Account name</dt><dd class="font-semibold text-gray-800">{{ $wallet['name'] }}</dd></div>
                        @endif
                        <div><dt class="text-gray-500">Amount to send</dt>
                            <dd class="font-semibold text-coral-700 tabular-nums">
                                @foreach ($transferOptions as $kind => $amount)
                                    <span x-show="kind === @js($kind)" @if ($kind !== $firstKind) x-cloak @endif>{{ Money::format($amount) }}</span>
                                @endforeach
                            </dd>
                        </div>
                    </dl>
                </div>
            @endforeach
        </fieldset>

        {{-- 3. Receipt --}}
        <fieldset class="space-y-4">
            <legend class="text-sm font-semibold text-lagoon-900"><span class="tabular text-gray-500">3.</span> Upload your receipt</legend>

            <div>
                <x-input-label for="reference_no" value="Reference number (Ref No. on your receipt)" />
                <x-text-input id="reference_no" name="reference_no" class="mt-1.5 block w-full font-mono tracking-wider" :value="old('reference_no')" required inputmode="text" autocomplete="off" spellcheck="false" maxlength="40" placeholder="e.g. 1234 567 891011" />
                <x-input-error :messages="$errors->get('reference_no')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="screenshot" value="Screenshot of the receipt" />
                <label for="screenshot" class="mt-1.5 flex cursor-pointer items-center gap-3 rounded-lg border-2 border-dashed border-lagoon-950/15 bg-white px-4 py-4 text-sm text-gray-600 transition-colors duration-200 hover:border-lagoon-700/40 focus-within:ring-2 focus-within:ring-lagoon-400">
                    <svg class="h-6 w-6 shrink-0 text-lagoon-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16.5V19a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-2.5M12 4v11m0-11-4 4m4-4 4 4" /></svg>
                    <span x-text="fileName || 'Choose a picture (JPG, PNG or WebP, up to 5 MB)'">Choose a picture (JPG, PNG or WebP, up to 5 MB)</span>
                    <input id="screenshot" name="screenshot" type="file" accept="image/jpeg,image/png,image/webp" required class="sr-only"
                           @change="fileName = $event.target.files[0]?.name || ''">
                </label>
                <x-input-error :messages="$errors->get('screenshot')" class="mt-2" />
            </div>
        </fieldset>

        <button class="btn-coral w-full py-3 text-base" :disabled="sending">
            <svg x-show="sending" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" stroke-dasharray="40 20" /></svg>
            <span x-text="sending ? 'Sending…' : 'Send receipt to the resort'">Send receipt to the resort</span>
        </button>

        <p class="text-center text-xs text-gray-500">
            Never share your MPIN or one-time code. The resort only needs the receipt. Prefer cash? You can still pay at the front desk.
        </p>
    </form>
</section>
