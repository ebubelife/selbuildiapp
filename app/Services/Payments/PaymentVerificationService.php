<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Services\OrderFulfillmentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PaymentVerificationService
{
    public function __construct(
        private readonly PaymentGatewayManager $gateways,
        private readonly OrderFulfillmentService $fulfillment,
    ) {}

    /**
     * The single path both the browser callback and the async webhook
     * funnel through. Always re-verifies against the provider's own API
     * rather than trusting whatever triggered the call - a callback query
     * string and a webhook body are both just "go check," never proof by
     * themselves. Safe to call more than once for the same reference.
     */
    public function confirm(string $provider, ?string $reference): void
    {
        if (blank($reference)) {
            return;
        }

        $payment = Payment::where('provider', $provider)->where('reference', $reference)->first();

        if (! $payment) {
            Log::warning('Payment verification requested for an unknown reference', [
                'provider' => $provider,
                'reference' => $reference,
            ]);

            return;
        }

        if ($payment->status === 'paid') {
            return;
        }

        Log::info('Verifying payment', [
            'provider' => $provider,
            'reference' => $reference,
            'order_id' => $payment->order_id,
            'expected_amount' => $payment->amount,
        ]);

        try {
            $result = $this->gateways->make($provider)->verify($reference);
        } catch (Throwable $e) {
            // A network/API failure talking to the provider is transient,
            // not a verdict - leave the payment as-is (still 'pending')
            // rather than marking it failed, so a retry (another webhook
            // delivery, or the customer revisiting) can still succeed once
            // the provider is reachable again.
            Log::error('Payment verification request failed', [
                'provider' => $provider,
                'reference' => $reference,
                'order_id' => $payment->order_id,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        if (! $result->successful) {
            Log::warning('Payment reported as unsuccessful by provider', [
                'provider' => $provider,
                'reference' => $reference,
                'order_id' => $payment->order_id,
            ]);
            $payment->update(['status' => 'failed']);

            return;
        }

        // The provider's own reported amount must match what we expected
        // to be paid - without this check, a customer could in principle
        // initialize a large order, pay a smaller amount some other way
        // reference-swapping, and still have it marked paid.
        if ($result->amount !== null && $result->amount !== (int) $payment->amount) {
            Log::warning('Payment amount mismatch on verification', [
                'provider' => $provider,
                'reference' => $reference,
                'order_id' => $payment->order_id,
                'expected' => $payment->amount,
                'reported' => $result->amount,
            ]);
            $payment->update(['status' => 'failed']);

            return;
        }

        DB::transaction(function () use ($payment) {
            $payment->update(['status' => 'paid', 'paid_at' => now()]);

            $order = $payment->order;
            $order->update(['payment_status' => 'paid']);

            if ($order->status === 'pending') {
                $this->fulfillment->advanceOrderStatus($order, 'confirmed', 'Payment confirmed via '.$payment->provider.'.');
            }
        });

        Log::info('Payment confirmed', [
            'provider' => $provider,
            'reference' => $reference,
            'order_id' => $payment->order_id,
            'amount' => $payment->amount,
        ]);
    }
}
