<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGatewayContract;
use App\Models\PaymentGateway;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;
use RuntimeException;

class PaymentGatewayManager
{
    /**
     * @var array<string, class-string<PaymentGatewayContract>>
     */
    private const DRIVERS = [
        'flutterwave' => FlutterwaveGateway::class,
        'paystack' => PaystackGateway::class,
        'fapshi' => FapshiGateway::class,
    ];

    /**
     * @return Collection<int, PaymentGateway>
     */
    public function enabled(): Collection
    {
        return PaymentGateway::where('is_enabled', true)
            ->whereIn('provider', array_keys(self::DRIVERS))
            ->get();
    }

    public function isEnabled(string $provider): bool
    {
        return PaymentGateway::where('provider', $provider)->where('is_enabled', true)->exists();
    }

    public function make(string $provider): PaymentGatewayContract
    {
        $driver = self::DRIVERS[$provider] ?? null;

        if (! $driver) {
            throw new InvalidArgumentException("Unknown payment provider [{$provider}].");
        }

        $config = PaymentGateway::where('provider', $provider)->first();

        if (! $config || ! $config->is_enabled) {
            throw new RuntimeException("Payment provider [{$provider}] is not enabled.");
        }

        return new $driver($config);
    }
}
