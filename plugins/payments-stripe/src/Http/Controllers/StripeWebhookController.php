<?php

declare(strict_types=1);

namespace Plugins\PaymentsStripe\Http\Controllers;

use App\Core\Support\PaymentGatewayRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Stripe\Exception\SignatureVerificationException;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        try {
            $gateway = PaymentGatewayRegistry::get('stripe');

            if (! $gateway) {
                return response()->json(['error' => 'Gateway not registered'], 503);
            }

            $gateway->handleWebhook($request);

            return response()->json(['status' => 'ok']);
        } catch (SignatureVerificationException|UnexpectedValueException) {
            // Invalid payload or signature — return 400 so Stripe suppresses retries.
            return response()->json(['error' => 'Invalid signature'], 400);
        }
    }
}
