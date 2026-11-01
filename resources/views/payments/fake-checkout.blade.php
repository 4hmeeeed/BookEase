<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.head', ['title' => 'Sandbox checkout'])
</head>
<body class="font-sans antialiased">
    <div class="min-h-screen bg-gray-100 px-4 py-10">
        <div class="mx-auto max-w-md">
            <div class="mb-4 rounded-2xl border-2 border-dashed border-coral-400 bg-coral-50 p-3 text-center text-sm font-semibold text-coral-800">
                SANDBOX: simulated {{ $payment->method->label() }} checkout. No real money moves.
            </div>

            <div class="card animate-pop-in overflow-hidden bg-white">
                <div @class(['px-6 py-5 text-white', 'bg-ocean-700' => $payment->method->value === 'gcash', 'bg-lagoon-700' => $payment->method->value !== 'gcash'])>
                    <p class="text-sm opacity-80">Pay with {{ $payment->method->label() }} (simulated)</p>
                    <p class="mt-1 font-display text-4xl font-semibold">{{ \App\Support\Money::format($payment->amount) }}</p>
                </div>

                <dl class="space-y-2 px-6 py-5 text-sm">
                    <div class="flex justify-between"><dt class="text-gray-500">Merchant</dt><dd class="font-semibold">Labnasan Resort</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">For</dt><dd class="font-semibold">{{ $payment->kind->label() }} · {{ $payment->booking->cottage->name }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Booking</dt><dd class="font-mono">{{ $payment->booking->reference_code }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Reference</dt><dd class="font-mono">{{ $payment->reference_no }}</dd></div>
                </dl>

                <form method="POST" action="{{ route('payments.fake.complete', $session) }}" class="grid gap-3 border-t border-gray-200 px-6 py-5">
                    @csrf
                    <input type="hidden" name="success" value="{{ $success }}">
                    <input type="hidden" name="cancel" value="{{ $cancel }}">
                    <button name="outcome" value="pay" class="btn-primary py-3 text-base">Authorize payment</button>
                    <button name="outcome" value="fail" class="btn-ghost">Simulate a failed payment</button>
                    <a href="{{ $cancel }}" class="text-center text-sm text-gray-500 hover:text-gray-800">Cancel and go back</a>
                </form>
            </div>

            <p class="mt-4 text-center text-xs text-gray-500">
                "Authorize" sends a signed webhook through the same verification as the real gateway.
            </p>
        </div>
    </div>
</body>
</html>
