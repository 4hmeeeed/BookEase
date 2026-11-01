<?php

namespace App\Http\Controllers\Staff;

use App\Enums\PaymentMethod;
use App\Exceptions\PaymentNotAllowedException;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\PaymentService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Front-desk money: walk-in/cash payments and refunds.
 */
class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function storeCash(Request $request, Booking $booking): RedirectResponse
    {
        $this->authorize('manage', $booking);
        $amount = $this->amount($request);

        try {
            $payment = $this->payments->recordCash($booking, $request->user(), $amount);
        } catch (PaymentNotAllowedException $e) {
            return back()->withErrors(['amount' => $e->getMessage()])->withInput();
        }

        return back()->with('status', 'Cash payment of '.Money::format($payment->amount)." recorded. Receipt {$payment->reference_no}.");
    }

    public function storeRefund(Request $request, Booking $booking): RedirectResponse
    {
        $this->authorize('manage', $booking);
        $amount = $this->amount($request, 'refund_amount');

        $method = $request->validate([
            'refund_method' => ['required', Rule::enum(PaymentMethod::class)->only([PaymentMethod::GCash, PaymentMethod::Maya, PaymentMethod::Cash])],
        ])['refund_method'];

        try {
            $payment = $this->payments->recordRefund($booking, $request->user(), $amount, PaymentMethod::from($method));
        } catch (PaymentNotAllowedException $e) {
            return back()->withErrors(['refund_amount' => $e->getMessage()])->withInput();
        }

        return back()->with('status', 'Refund of '.Money::format($payment->amount).' recorded.');
    }

    private function amount(Request $request, string $field = 'amount'): string
    {
        $request->merge([$field => preg_replace('/[^\d.]/', '', (string) $request->input($field))]);

        return $request->validate([
            $field => ['required', 'regex:/^\d{1,8}(\.\d{1,2})?$/'],
        ], [
            "{$field}.regex" => 'Enter an amount in pesos, e.g. 1500 or 1500.50.',
        ])[$field];
    }
}
