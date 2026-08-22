<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGatewayContract;
use App\Models\PaymentGateway;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FlutterwaveGateway implements PaymentGatewayContract
{
    private const BASE_URL = 'https://api.flutterwave.com/v3';

    public function __construct(private readonly PaymentGateway $config) {}

    public function initialize(Payment $payment, string $callbackUrl): string
    {
        $order = $payment->order;

        $response = Http::withToken($this->secretKey())
            ->post(self::BASE_URL.'/payments', [
                'tx_ref' => $payment->reference,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'redirect_url' => $callbackUrl,
                'customer' => [
                    'email' => $order->user->email,
                    'name' => $order->user->name,
                    'phonenumber' => $order->user->phone,
                ],
                'customizations' => [
                    'title' => 'Selbuildi Order '.$order->order_number,
                ],
            ]);

        $link = $response->json('data.link');

        if (! $response->successful() || ! $link) {
            throw new RuntimeException('Flutterwave did not return a payment link: '.$response->body());
        }

        return $link;
    }

    public function verify(string $reference): PaymentVerificationResult
    {
        $response = Http::withToken($this->secretKey())
            ->get(self::BASE_URL.'/transactions/verify_by_reference', [
                'tx_ref' => $reference,
            ]);

        $data = $response->json('data', []);
        $successful = $response->successful() && (($data['status'] ?? null) === 'successful');

        return new PaymentVerificationResult(
            successful: $successful,
            amount: isset($data['amount']) ? (int) $data['amount'] : null,
            currency: $data['currency'] ?? null,
            raw: $data,
        );
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        $hash = $this->config->credential('webhook_hash');

        if (blank($hash)) {
            // No secret hash configured - can't verify authenticity, so
            // don't trust this webhook as a trigger. verify() being the
            // sole source of truth for *marking paid* still protects
            // against a forged webhook doing real damage, but without a
            // hash we shouldn't even bother re-verifying on its say-so.
            return false;
        }

        return hash_equals($hash, (string) $request->header('verif-hash'));
    }

    public function extractReferenceFromWebhook(Request $request): ?string
    {
        return $request->input('data.tx_ref') ?? $request->input('txRef');
    }

    private function secretKey(): string
    {
        return (string) $this->config->credential('secret_key');
    }
}
