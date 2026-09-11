<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Services\ExchangeRateFetcher;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Approximate emergency fallbacks (XAF cost of 1 unit), used only if
     * the live rate fetch fails at seed time - e.g. no network access in
     * a sandboxed environment. An admin can refresh from the live source
     * for real numbers at any time afterward; this just means the app
     * never ships with a currency missing a rate entirely.
     */
    private const FALLBACK_RATES = [
        'NGN' => 0.42,
        'USD' => 605,
        'EUR' => 656,
        'GBP' => 763,
        'ZAR' => 33,
        'KES' => 4.4,
        'GHS' => 49,
    ];

    public function run(): void
    {
        $live = app(ExchangeRateFetcher::class)->fetch();

        $currencies = [
            ['code' => 'NGN', 'name' => 'Nigerian Naira', 'symbol' => '₦', 'country_label' => 'Nigeria', 'is_supported' => true, 'sort_order' => 1],
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'country_label' => 'United States', 'is_supported' => true, 'sort_order' => 2],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'country_label' => 'Europe', 'is_supported' => true, 'sort_order' => 3],
            ['code' => 'GBP', 'name' => 'British Pound', 'symbol' => '£', 'country_label' => 'United Kingdom', 'is_supported' => true, 'sort_order' => 4],
            ['code' => 'ZAR', 'name' => 'South African Rand', 'symbol' => 'R', 'country_label' => 'South Africa', 'is_supported' => true, 'sort_order' => 5],
            ['code' => 'KES', 'name' => 'Kenyan Shilling', 'symbol' => 'KSh', 'country_label' => 'Kenya', 'is_supported' => true, 'sort_order' => 6],
            ['code' => 'GHS', 'name' => 'Ghanaian Cedi', 'symbol' => 'GH₵', 'country_label' => 'Ghana', 'is_supported' => true, 'sort_order' => 7],
        ];

        foreach ($currencies as $currency) {
            $rate = $live[$currency['code']] ?? self::FALLBACK_RATES[$currency['code']];

            Currency::firstOrCreate(
                ['code' => $currency['code']],
                [
                    ...$currency,
                    'rate_to_xaf' => $rate,
                    'rate_source' => isset($live[$currency['code']]) ? 'live' : 'manual',
                    'rate_fetched_at' => isset($live[$currency['code']]) ? now() : null,
                ]
            );
        }
    }
}
