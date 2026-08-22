<?php

namespace App\Filament\Resources\PaymentGateways\Pages;

use App\Filament\Resources\PaymentGateways\PaymentGatewayResource;
use Filament\Resources\Pages\ManageRecords;

class ManagePaymentGateways extends ManageRecords
{
    protected static string $resource = PaymentGatewayResource::class;

    // Exactly three fixed provider rows are seeded (PaymentGatewaySeeder) -
    // admins configure them, they don't create/remove providers.
    protected function getHeaderActions(): array
    {
        return [];
    }
}
