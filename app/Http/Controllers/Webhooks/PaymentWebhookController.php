<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Payments\InvalidWebhookException;
use App\Payments\PaymentGateway;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * POST /webhooks/payments: the gateway's signed callback.
 * CSRF-exempt (the HMAC signature is the authentication).
 */
class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentGateway $gateway, PaymentService $payments): JsonResponse
    {
        try {
            // Raw bytes: re-encoding the JSON would break the signature.
            $event = $gateway->parseWebhook($request->getContent(), $request->header('Paymongo-Signature'));
        } catch (InvalidWebhookException $e) {
            Log::warning('Rejected payment webhook', ['reason' => $e->getMessage(), 'ip' => $request->ip()]);

            return response()->json(['error' => 'invalid webhook'], 400);
        }

        $outcome = $payments->handleWebhook($event);

        Log::info('Payment webhook processed', ['type' => $event->type, 'checkout' => $event->checkoutId, 'outcome' => $outcome]);

        // 2xx for everything verified, so the gateway stops retrying.
        return response()->json(['received' => true, 'outcome' => $outcome]);
    }
}
