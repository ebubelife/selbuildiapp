<?php

namespace App\Services\Payments;

class PaymentVerificationResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly bool $successful,
        public readonly ?int $amount = null,
        public readonly ?string $currency = null,
        public readonly array $raw = [],
    ) {}
}
