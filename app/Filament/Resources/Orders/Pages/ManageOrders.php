<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Resources\Pages\ManageRecords;

class ManageOrders extends ManageRecords
{
    protected static string $resource = OrderResource::class;

    // Orders only ever come from checkout - never hand-created here, since
    // that would skip inventory decrements, notifications, and trust score
    // events. No create action on purpose.
    protected function getHeaderActions(): array
    {
        return [];
    }
}
