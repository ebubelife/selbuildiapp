<?php

namespace App\Filament\Resources\CreditTierSettings\Pages;

use App\Filament\Resources\CreditTierSettings\CreditTierSettingResource;
use Filament\Resources\Pages\ManageRecords;

class ManageCreditTierSettings extends ManageRecords
{
    protected static string $resource = CreditTierSettingResource::class;

    protected function getHeaderActions(): array
    {
        // Fixed 4-row config table, seeded by migration - no create action.
        return [];
    }
}
