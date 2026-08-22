<?php

namespace Database\Seeders;

use App\Models\PaymentGateway;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PaymentGatewaySeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the three payment providers as disabled, credential-less rows -
     * an admin fills in real keys and switches each on from the panel once
     * they have them. Never overwrites credentials/enabled state an admin
     * has already set.
     */
    public function run(): void
    {
        $gateways = [
            ['provider' => 'flutterwave', 'display_name' => 'Flutterwave'],
            ['provider' => 'paystack', 'display_name' => 'Paystack'],
            ['provider' => 'fapshi', 'display_name' => 'Fapshi (MTN/Orange Money)'],
        ];

        foreach ($gateways as $gateway) {
            PaymentGateway::firstOrCreate(
                ['provider' => $gateway['provider']],
                [
                    'display_name' => $gateway['display_name'],
                    'is_enabled' => false,
                    'mode' => 'test',
                    'credentials' => [],
                ]
            );
        }
    }
}
