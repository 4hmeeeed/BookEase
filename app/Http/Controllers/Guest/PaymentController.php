<?php

namespace App\Http\Controllers\Guest;

use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Enums\PaymentRecordStatus;
use App\Exceptions\PaymentNotAllowedException;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    /**
     * POST /my/bookings/{booking}/pay: start a hosted GCash/Maya checkout.
     */
    public function store(Request $request, Booking $booking): RedirectResponse
    {
        $this->authorize('view', $booking);

        $data = $request->validate([
            'kind' => ['required', Rule::enum(PaymentKind::class)->except([PaymentKind::Refund])],
            'method' => ['required', Rule::enum(PaymentMethod::class)->only(PaymentMethod::online())],
        ]);

        try {
            [$payment, $session] = $this->payments->startCheckout(
                $booking,
                $request->user(),
                PaymentKind::from($data['kind']),
                PaymentMethod::from($data['method']),
                fn (Payment $p) => [
                    route('guest.payments.return', [$booking, $p]),
                    route('guest.payments.return', [$booking, $p, 'cancelled' => 1]),
                ],
            );
        } catch (PaymentNotAllowedException $e) {
            return back()->with('error', $e->getMessage());
        } catch (RuntimeException $e) {
            report($e);

            return back()->with('error', 'We couldn’t reach the payment provider. Please try again in a moment.');
        }

        return redirect()->away($session->url);
    }

    /**
     * GET …/payments/{payment}/return: where the gateway sends the guest back.
     * This page NEVER marks anything paid; it waits for the signed webhook.
     */
    public function returned(Request $request, Booking $booking, Payment $payment): View
    {
        $this->authorizePayment($booking, $payment);

        if ($request->boolean('cancelled') && $payment->status === PaymentRecordStatus::Pending) {
            // Guest backed out. Harmless: a later signed "paid" webhook still wins.
            $payment->forceFill(['status' => PaymentRecordStatus::Expired])->save();
        }

        return view('guest.payments.return', [
            'booking' => $booking,
            'payment' => $payment->fresh(),
        ]);
    }

    /**
     * GET …/payments/{payment}/status: polled by the return page.
     */
    public function status(Booking $booking, Payment $payment): JsonResponse
    {
        $this->authorizePayment($booking, $payment);

        return response()->json([
            'status' => $payment->fresh()->status->value,
            'receipt_url' => $payment->isPaid() ? route('receipts.show', $payment) : null,
        ]);
    }

    private function authorizePayment(Booking $booking, Payment $payment): void
    {
        $this->authorize('view', $booking);
        abort_unless($payment->booking_id === $booking->id, 404);
    }
}
