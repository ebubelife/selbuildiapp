<?php

namespace App\Contracts;

use App\Models\Payment;
use App\Services\Payments\PaymentVerificationResult;
use Illuminate\Http\Request;

interface PaymentGatewayContract
{
    /**
     * Starts a hosted-checkout transaction for this payment and returns the
     * URL to send the customer's browser to.
     */
    public function initialize(Payment $payment, string $callbackUrl): string;

    /**
     * Asks the provider directly (authenticated server-to-server call) for
     * the current status of a transaction - the only result that's ever
     * trusted to mark a payment paid, never a webhook payload or query
     * string alone.
     */
    public function verify(string $reference): PaymentVerificationResult;

    /**
     * Confirms an inbound webhook request actually came from the provider.
     * Providers without a documented signing scheme (e.g. Fapshi) can
     * return true here and rely on verify() as the authoritative check
     * instead - the webhook is only ever a trigger to re-verify, never
     * itself the source of truth.
     */
    public function verifyWebhookSignature(Request $request): bool;

    /**
     * Pulls the payment reference out of a webhook payload, so the caller
     * knows which Payment row to re-verify and update.
     */
    public function extractReferenceFromWebhook(Request $request): ?string;
}
