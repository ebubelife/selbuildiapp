<?php

namespace App\Http\Controllers;

use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\PaymentVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentWebhookController extends Controller
{
    /**
     * Async, server-to-server notification from the provider - the
     * authoritative confirmation path (unlike the browser callback, this
     * fires even if the customer closes the tab). The payload itself is
     * never trusted for anything beyond "which reference to look up" -
     * verification.confirm() always re-checks with the provider's own API
     * before marking anything paid.
     */
    public function __invoke(Request $request, string $provider, PaymentGatewayManager $gateways, PaymentVerificationService $verification): JsonResponse
    {
        $gateway = $gateways->make($provider);

        if (! $gateway->verifyWebhookSignature($request)) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $reference = $gateway->extractReferenceFromWebhook($request);

        $verification->confirm($provider, $reference);

        return response()->json(['message' => 'ok']);
    }
}
