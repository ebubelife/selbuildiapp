<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGatewayContract;
use App\Models\PaymentGateway;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FapshiGateway implements PaymentGatewayContract
{
    public function __construct(private readonly PaymentGateway $config) {}

    public function initialize(Payment $payment, string $callbackUrl): string
    {
        $response = Http::withHeaders($this->authHeaders())
            ->post($this->baseUrl().'/initiate-pay', [
                'amount' => $payment->amount,
                'externalId' => $payment->reference,
                'redirectUrl' => $callbackUrl,
                'email' => $payment->order->user->email,
            ]);

        $link = $response->json('link');

        if (! $response->successful() || ! $link) {
            throw new RuntimeException('Fapshi did not return a payment link: '.$response->body());
        }

        return $link;
    }

    /**
     * Fapshi's webhook signing isn't as uniformly documented as
     * Flutterwave's/Paystack's, so this is the one gateway where the
     * webhook payload is never trusted at all - it's purely a prompt to
     * make this same authenticated status call, which is what actually
     * decides whether the payment is marked paid.
     */
    public function verify(string $reference): PaymentVerificationResult
    {
        $response = Http::withHeaders($this->authHeaders())
            ->get($this->baseUrl().'/payment-status/'.urlencode($reference));

        $data = $response->json() ?? [];
        $successful = $response->successful() && (($data['status'] ?? null) === 'SUCCESSFUL');

        return new PaymentVerificationResult(
            successful: $successful,
            amount: isset($data['amount']) ? (int) $data['amount'] : null,
            currency: 'XAF',
            raw: $data,
        );
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        // No signature to check - verify() re-confirms via an authenticated
        // server-to-server call before anything is trusted, so an
        // unauthenticated/forged webhook can prompt a lookup but can't
        // itself mark a payment paid.
        return true;
    }

    public function extractReferenceFromWebhook(Request $request): ?string
    {
        return $request->input('externalId');
    }

    private function baseUrl(): string
    {
        return $this->config->mode === 'live'
            ? 'https://live.fapshi.com'
            : 'https://sandbox.fapshi.com';
    }

    /**
     * @return array<string, string>
     */
    private function authHeaders(): array
    {
        return [
            'apiuser' => (string) $this->config->credential('api_user'),
            'apikey' => (string) $this->config->credential('api_key'),
        ];
    }
}
