<?php

namespace App\Filament\Resources\SupplierProfiles\Pages;

use App\Filament\Resources\SupplierProfiles\SupplierProfileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSupplierProfiles extends ManageRecords
{
    protected static string $resource = SupplierProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
