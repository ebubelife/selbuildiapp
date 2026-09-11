<?php

namespace Tests;

use App\Models\Country;
use App\Models\Currency;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Mirrors real seeded state: Cameroon is the one country actually
        // enabled for checkout in production today (see the
        // checkout_enabled migration on countries). Without this, every
        // existing address/checkout test that submits "Cameroon" would
        // fail the new checkout-country validation on a fresh, unseeded
        // test database.
        if (Schema::hasTable('countries')) {
            Country::firstOrCreate(
                ['code' => 'CM'],
                ['name' => 'Cameroon', 'checkout_enabled' => true]
            );
        }

        // Same reasoning for currencies: XAF exists from the migration
        // itself, but NGN/USD/EUR/GBP/ZAR/KES/GHS only exist via
        // CurrencySeeder - which makes a live HTTP call, too slow/flaky
        // to run on every single test. Static fallback rates here (the
        // same ones CurrencySeeder itself falls back to) are enough for
        // any test that just needs these currencies to exist and be
        // selectable - nothing here asserts on the exact rate value.
        if (Schema::hasTable('currencies')) {
            foreach ([
                ['code' => 'NGN', 'name' => 'Nigerian Naira', 'symbol' => '₦', 'country_label' => 'Nigeria', 'rate_to_xaf' => 0.42],
                ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'country_label' => 'United States', 'rate_to_xaf' => 605],
                ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'country_label' => 'Europe', 'rate_to_xaf' => 656],
                ['code' => 'GBP', 'name' => 'British Pound', 'symbol' => '£', 'country_label' => 'United Kingdom', 'rate_to_xaf' => 763],
                ['code' => 'ZAR', 'name' => 'South African Rand', 'symbol' => 'R', 'country_label' => 'South Africa', 'rate_to_xaf' => 33],
                ['code' => 'KES', 'name' => 'Kenyan Shilling', 'symbol' => 'KSh', 'country_label' => 'Kenya', 'rate_to_xaf' => 4.4],
                ['code' => 'GHS', 'name' => 'Ghanaian Cedi', 'symbol' => 'GH₵', 'country_label' => 'Ghana', 'rate_to_xaf' => 49],
            ] as $currency) {
                Currency::firstOrCreate(['code' => $currency['code']], [...$currency, 'is_supported' => true]);
            }
        }
    }
}
