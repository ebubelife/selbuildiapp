<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pulls live rates from a free, no-API-key source
 * (https://www.exchangerate-api.com/docs/free) as a starting point for an
 * admin to review - never applied automatically. The admin UI only ever
 * uses this to pre-fill a rate field (or, for the bulk refresh action,
 * to update rows the admin explicitly triggered it for) - nothing here
 * runs on a schedule.
 */
class ExchangeRateFetcher
{
    private const ENDPOINT = 'https://open.er-api.com/v6/latest/XAF';

    /**
     * @return array<string, float>|null  currency code => XAF cost of 1 unit, or null on failure
     */
    public function fetch(): ?array
    {
        try {
            $response = Http::timeout(10)->get(self::ENDPOINT);
        } catch (Throwable $e) {
            Log::error('Exchange rate fetch failed', ['error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful() || $response->json('result') !== 'success') {
            Log::error('Exchange rate fetch returned an unsuccessful response', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        // The API gives "1 XAF = X of this currency" (base XAF) - invert
        // to "1 unit of this currency = how many XAF", which is the
        // convention currencies.rate_to_xaf stores.
        $rates = $response->json('rates', []);

        return collect($rates)
            ->filter(fn ($rate) => is_numeric($rate) && $rate > 0)
            ->map(fn ($rate) => round(1 / $rate, 6))
            ->all();
    }

    public function fetchOne(string $code): ?float
    {
        return $this->fetch()[$code] ?? null;
    }
}
