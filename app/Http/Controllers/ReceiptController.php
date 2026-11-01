<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\View\View;

/**
 * GET /receipts/{payment}: printable e-receipt for a settled payment.
 * Visible to the booking's guest and to staff/owner.
 */
class ReceiptController extends Controller
{
    public function __invoke(Payment $payment): View
    {
        $payment->load(['booking.cottage', 'booking.user', 'recordedBy']);

        $this->authorize('view', $payment->booking);
        abort_unless($payment->isPaid(), 404);

        return view('receipts.show', ['payment' => $payment, 'booking' => $payment->booking]);
    }
}
