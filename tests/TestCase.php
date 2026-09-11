<?php

namespace Tests;

use App\Models\Country;
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
    }
}
