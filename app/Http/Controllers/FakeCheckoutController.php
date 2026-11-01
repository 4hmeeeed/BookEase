<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Payments\FakeGateway;
use App\Payments\PaymentGateway;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Sandbox stand-in for the gateway's hosted checkout page (driver = fake).
 *
 * "Pay" here does NOT mark the payment paid directly: it builds a signed
 * PayMongo-shaped webhook and pushes it through the same verification and
 * processing as a real callback.
 */
class FakeCheckoutController extends Controller
{
    public function __construct(private readonly PaymentGateway $gateway) {}

    public function show(Request $request, string $session): View
    {
        $this->ensureEnabled();
        $payment = $this->payment($session);

        return view('payments.fake-checkout', [
            'payment' => $payment->load('booking.cottage'),
            'session' => $session,
            'success' => $this->safeUrl($request->query('success')),
            'cancel' => $this->safeUrl($request->query('cancel')),
        ]);
    }

    public function complete(Request $request, string $session, PaymentService $payments): RedirectResponse
    {
        $this->ensureEnabled();
        $payment = $this->payment($session);
        $paid = $request->input('outcome') === 'pay';

        /** @var FakeGateway $gateway */
        $gateway = $this->gateway;
        $webhook = $gateway->makeWebhook($payment, paid: $paid);
        $payments->handleWebhook($gateway->parseWebhook($webhook['body'], $webhook['signature']));

        return redirect()->to($this->safeUrl($paid ? $request->input('success') : $request->input('cancel')));
    }

    private function ensureEnabled(): void
    {
        abort_unless($this->gateway instanceof FakeGateway && ! app()->isProduction(), 404);
    }

    private function payment(string $session): Payment
    {
        return Payment::where('provider_payment_id', $session)->firstOrFail();
    }

    /** Only allow redirects back into this app (no open redirect). */
    private function safeUrl(?string $url): string
    {
        $base = rtrim(url('/'), '/');

        return is_string($url) && ($url === $base || str_starts_with($url, $base.'/')) ? $url : route('dashboard');
    }
}
