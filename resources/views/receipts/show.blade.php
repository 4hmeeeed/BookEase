@use('App\Support\Money')
<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.head', ['title' => 'Receipt '.$payment->reference_no])
    <style>
        @media print {
            body::before { display: none !important; }
            .no-print { display: none !important; }
            .receipt { box-shadow: none !important; border: 1px solid #ccc !important; }
            @page { margin: 12mm; }
        }
    </style>
</head>
<body class="font-sans antialiased">
    <div class="min-h-screen px-4 py-10">
        <div class="no-print mx-auto mb-6 flex max-w-2xl items-center justify-between">
            <a href="{{ Auth::user()->isGuest() ? route('guest.bookings.show', $booking) : route('staff.bookings.show', $booking) }}" class="link-underline text-sm">&larr; Back to booking</a>
            <button type="button" onclick="window.print()" class="btn-primary">Print / Save as PDF</button>
        </div>

        <article class="receipt card mx-auto max-w-2xl animate-pop-in overflow-hidden bg-white">
            <header class="flex flex-wrap items-start justify-between gap-6 border-b border-sand-200 bg-sand-50 px-8 py-7">
                <div>
                    <x-brand />
                    <p class="mt-3 text-sm text-gray-600">Barangay Bacubac, Basey, Samar</p>
                </div>
                <div class="text-right">
                    <p class="eyebrow">{{ $payment->isRefund() ? 'Refund receipt' : 'Electronic receipt' }}</p>
                    <p class="mt-1 font-mono text-lg font-semibold text-lagoon-950">{{ $payment->reference_no }}</p>
                    <p class="text-sm text-gray-600">{{ $payment->paid_at->format('F j, Y · g:i A') }}</p>
                </div>
            </header>

            <div class="grid gap-6 px-8 py-7 sm:grid-cols-2">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-sand-600">Received from</p>
                    <p class="mt-1 font-semibold text-gray-900">{{ $booking->user->name }}</p>
                    <p class="text-sm text-gray-600">{{ $booking->user->email }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-sand-600">Booking</p>
                    <p class="mt-1 font-mono font-semibold text-gray-900">{{ $booking->reference_code }}</p>
                    <p class="text-sm text-gray-600">{{ $booking->cottage->name }}</p>
                    <p class="text-sm text-gray-600">{{ $booking->check_in->format('M j') }} – {{ $booking->check_out->format('M j, Y') }} · {{ $booking->nights() }} {{ Str::plural('night', $booking->nights()) }}</p>
                </div>
            </div>

            <table class="mx-8 mb-2 w-[calc(100%-4rem)] text-sm">
                <tbody class="divide-y divide-sand-200">
                    <tr><td class="py-2 text-gray-600">Description</td><td class="py-2 text-right font-semibold">{{ $payment->kind->label() }}</td></tr>
                    <tr><td class="py-2 text-gray-600">Payment method</td><td class="py-2 text-right font-semibold">{{ $payment->method->label() }}</td></tr>
                    @if ($payment->recordedBy)
                        <tr><td class="py-2 text-gray-600">Recorded by</td><td class="py-2 text-right">{{ $payment->recordedBy->name }}</td></tr>
                    @elseif ($payment->method->isOnline())
                        <tr><td class="py-2 text-gray-600">Confirmed by</td><td class="py-2 text-right">Payment provider (signed callback)</td></tr>
                    @endif
                    <tr><td class="py-2 text-gray-600">Booking total</td><td class="py-2 text-right">{{ Money::format($booking->total_price) }}</td></tr>
                </tbody>
            </table>

            <div class="mx-8 mb-7 mt-4 flex items-end justify-between rounded-2xl bg-lagoon-900 px-6 py-5 text-cream">
                <span class="text-sm">{{ $payment->isRefund() ? 'Amount refunded' : 'Amount received' }}</span>
                <span class="font-display text-4xl font-semibold">{{ Money::format($payment->amount) }}</span>
            </div>

            <footer class="border-t border-dashed border-sand-300 px-8 py-5 text-sm text-gray-600">
                <p>Total paid to date: <strong>{{ Money::format($booking->amount_paid) }}</strong> · Balance: <strong>{{ Money::format($booking->balance()) }}</strong></p>
                <p class="mt-2 text-xs text-gray-500">This is a system-generated electronic receipt from BookEase for Labnasan Resort.</p>
            </footer>
        </article>
    </div>
</body>
</html>
