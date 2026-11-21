<?php

namespace App\Http\Controllers\Staff;

use App\Exceptions\PaymentNotAllowedException;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\PaymentProof;
use App\Services\WalletTransferService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The desk's side of a GCash/Maya transfer: look at the receipt, check the
 * wallet app, then verify (credit it) or reject (tell the guest why).
 */
class PaymentProofController extends Controller
{
    public function __construct(private readonly WalletTransferService $transfers) {}

    public function screenshot(Booking $booking, PaymentProof $proof): StreamedResponse
    {
        $this->authorizeProof($booking, $proof);

        return Storage::disk(PaymentProof::DISK)->response($proof->screenshot_path, headers: [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function verify(Request $request, Booking $booking, PaymentProof $proof): RedirectResponse
    {
        $this->authorizeProof($booking, $proof);

        $request->merge(['verified_amount' => preg_replace('/[^\d.]/', '', (string) $request->input('verified_amount'))]);
        $amount = $request->validate([
            'verified_amount' => ['required', 'regex:/^\d{1,8}(\.\d{1,2})?$/'],
        ], [
            'verified_amount.regex' => 'Enter the amount you received, e.g. 1500 or 1500.50.',
        ])['verified_amount'];

        try {
            $payment = $this->transfers->verify($proof, $request->user(), $amount);
        } catch (PaymentNotAllowedException $e) {
            return back()->withErrors(['proof' => $e->getMessage()]);
        }

        return back()->with('status', $proof->method->label().' payment of '.Money::format($payment->amount)." verified. Receipt {$payment->reference_no} sent to the guest.");
    }

    public function reject(Request $request, Booking $booking, PaymentProof $proof): RedirectResponse
    {
        $this->authorizeProof($booking, $proof);

        $note = $request->validate([
            'review_note' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'review_note.required' => 'Tell the guest why, e.g. “No transfer with this reference number arrived.”',
        ])['review_note'];

        try {
            $this->transfers->reject($proof, $request->user(), $note);
        } catch (PaymentNotAllowedException $e) {
            return back()->withErrors(['proof' => $e->getMessage()]);
        }

        return back()->with('status', 'Payment marked as not received. The guest was emailed the reason.');
    }

    private function authorizeProof(Booking $booking, PaymentProof $proof): void
    {
        $this->authorize('manage', $booking);
        abort_unless($proof->booking_id === $booking->id, 404);
    }
}
