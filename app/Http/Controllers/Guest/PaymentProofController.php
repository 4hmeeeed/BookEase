<?php

namespace App\Http\Controllers\Guest;

use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Exceptions\PaymentNotAllowedException;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\PaymentProof;
use App\Services\WalletTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The guest's side of a GCash/Maya transfer to the resort's wallet: send the
 * receipt, and see the screenshot they sent.
 */
class PaymentProofController extends Controller
{
    public function __construct(private readonly WalletTransferService $transfers) {}

    public function store(Request $request, Booking $booking): RedirectResponse
    {
        abort_unless($booking->user_id === $request->user()->id, 403);

        $request->merge(['reference_no' => WalletTransferService::normalizeReference((string) $request->input('reference_no'))]);

        $data = $request->validate([
            'method' => ['required', Rule::enum(PaymentMethod::class)->only(PaymentMethod::online())],
            'kind' => ['required', Rule::enum(PaymentKind::class)->except([PaymentKind::Refund])],
            'reference_no' => ['required', 'string', 'min:6', 'max:30'],
            'screenshot' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'reference_no.required' => 'Enter the reference number shown on your receipt.',
            'reference_no.min' => 'That reference number looks too short. Copy it from your receipt.',
            'screenshot.required' => 'Attach a screenshot of your receipt.',
            'screenshot.image' => 'The screenshot must be a picture (JPG, PNG or WebP).',
            'screenshot.mimes' => 'The screenshot must be a picture (JPG, PNG or WebP).',
            'screenshot.max' => 'The screenshot must be 5 MB or smaller.',
        ]);

        try {
            $this->transfers->submit(
                $booking,
                $request->user(),
                PaymentMethod::from($data['method']),
                PaymentKind::from($data['kind']),
                $data['reference_no'],
                $request->file('screenshot'),
            );
        } catch (PaymentNotAllowedException $e) {
            return back()->withErrors(['transfer' => $e->getMessage()])->withInput();
        }

        return redirect()->route('guest.bookings.show', $booking)
            ->with('status', 'Thanks! The resort will check your payment and email you a receipt once it’s confirmed.');
    }

    public function screenshot(Request $request, Booking $booking, PaymentProof $proof): StreamedResponse
    {
        abort_unless($booking->user_id === $request->user()->id && $proof->booking_id === $booking->id, 404);

        return Storage::disk(PaymentProof::DISK)->response($proof->screenshot_path, headers: [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
