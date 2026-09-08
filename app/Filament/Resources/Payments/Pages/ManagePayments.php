<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use Filament\Resources\Pages\ManageRecords;

class ManagePayments extends ManageRecords
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        // Payments are only ever created by the checkout/webhook flow -
        // there's no legitimate "admin creates a payment by hand" workflow.
        return [];
    }
}
