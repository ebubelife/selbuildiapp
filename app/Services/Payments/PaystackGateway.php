<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGatewayContract;
use App\Models\PaymentGateway;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PaystackGateway implements PaymentGatewayContract
{
    private const BASE_URL = 'https://api.paystack.co';

    public function __construct(private readonly PaymentGateway $config) {}

    public function initialize(Payment $payment, string $callbackUrl): string
    {
        $response = Http::withToken($this->secretKey())
            ->post(self::BASE_URL.'/transaction/initialize', [
                'email' => $payment->order->user->email,
                // Paystack amounts are in the smallest currency unit
                // (kobo/cents) - XAF has no subunit in practice, but this
                // still follows their documented contract for whichever
                // currency the account is actually configured for.
                'amount' => $payment->amount * 100,
                'currency' => $payment->currency,
                'reference' => $payment->reference,
                'callback_url' => $callbackUrl,
            ]);

        $url = $response->json('data.authorization_url');

        if (! $response->successful() || ! $url) {
            throw new RuntimeException('Paystack did not return an authorization URL: '.$response->body());
        }

        return $url;
    }

    public function verify(string $reference): PaymentVerificationResult
    {
        $response = Http::withToken($this->secretKey())
            ->get(self::BASE_URL.'/transaction/verify/'.urlencode($reference));

        $data = $response->json('data', []);
        $successful = $response->successful() && (($data['status'] ?? null) === 'success');

        return new PaymentVerificationResult(
            successful: $successful,
            amount: isset($data['amount']) ? (int) ($data['amount'] / 100) : null,
            currency: $data['currency'] ?? null,
            raw: $data,
        );
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        $signature = (string) $request->header('x-paystack-signature');
        $expected = hash_hmac('sha512', $request->getContent(), $this->secretKey());

        return hash_equals($expected, $signature);
    }

    public function extractReferenceFromWebhook(Request $request): ?string
    {
        return $request->input('data.reference');
    }

    private function secretKey(): string
    {
        return (string) $this->config->credential('secret_key');
    }
}
