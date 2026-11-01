<x-app-layout>
    {{-- Waits for the signed webhook. This page itself never marks a payment paid. --}}
    <div class="mx-auto max-w-lg px-4 py-16"
         x-data="{
             status: @js($payment->status->value),
             receipt: null,
             tries: 0,
             async poll() {
                 if (this.status !== 'pending' || this.tries++ > 40) return;
                 try {
                     const res = await fetch(@js(route('guest.payments.status', [$booking, $payment])), { headers: { Accept: 'application/json' } });
                     const data = await res.json();
                     this.status = data.status;
                     this.receipt = data.receipt_url;
                 } catch {}
                 if (this.status === 'pending') setTimeout(() => this.poll(), 2500);
             },
         }"
         x-init="poll()">

        <div class="card animate-pop-in p-8 text-center">
            {{-- pending --}}
            <div x-show="status === 'pending'">
                <div class="mx-auto h-16 w-16 animate-spin rounded-full border-4 border-lagoon-100 border-t-lagoon-600"></div>
                <h1 class="mt-6 font-serif text-2xl font-semibold text-lagoon-950">Confirming your payment…</h1>
                <p class="mt-2 text-gray-600">We're waiting for {{ $payment->method->label() }} to confirm. This usually takes a few seconds.</p>
                <p x-show="tries > 40" x-cloak class="mt-4 text-sm text-gray-500">Still waiting. You can safely leave this page. Your booking updates automatically once the payment is confirmed.</p>
            </div>

            {{-- paid --}}
            <div x-show="status === 'paid'" x-cloak>
                <div class="mx-auto grid h-16 w-16 animate-pop-in place-items-center rounded-full bg-lagoon-600 text-cream shadow-lagoon-glow">
                    <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 5 5 9-10" /></svg>
                </div>
                <h1 class="mt-6 font-serif text-2xl font-semibold text-lagoon-950">Payment received!</h1>
                <p class="mt-2 text-gray-600">{{ \App\Support\Money::format($payment->amount) }} for booking {{ $booking->reference_code }}. We've emailed your e-receipt.</p>
                <a :href="receipt ?? @js(route('receipts.show', $payment))" class="btn-primary mt-6">View e-receipt</a>
            </div>

            {{-- failed / expired / cancelled --}}
            <div x-show="['failed', 'expired'].includes(status)" x-cloak>
                <div class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-coral-100 text-coral-600">
                    <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18" /></svg>
                </div>
                <h1 class="mt-6 font-serif text-2xl font-semibold text-lagoon-950">Payment not completed</h1>
                <p class="mt-2 text-gray-600">No money was taken. You can try again from your booking.</p>
            </div>

            <a href="{{ route('guest.bookings.show', $booking) }}" class="link-underline mt-6 inline-block text-sm">Back to booking {{ $booking->reference_code }}</a>
        </div>
    </div>
</x-app-layout>
