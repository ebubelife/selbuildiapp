<?php

namespace App\Http\Controllers;

use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\PaymentVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

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
        Log::info('Payment webhook received', ['provider' => $provider]);

        try {
            $gateway = $gateways->make($provider);
        } catch (RuntimeException $e) {
            // A webhook for a provider that's been disabled since the
            // payment was initiated - not an attack, just a race with an
            // admin toggling it off. Nothing to verify against.
            Log::warning('Payment webhook received for a disabled/unknown provider', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Provider not available.'], 422);
        }

        if (! $gateway->verifyWebhookSignature($request)) {
            Log::warning('Payment webhook signature verification failed', ['provider' => $provider]);

            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $reference = $gateway->extractReferenceFromWebhook($request);

        $verification->confirm($provider, $reference);

        return response()->json(['message' => 'ok']);
    }
}
