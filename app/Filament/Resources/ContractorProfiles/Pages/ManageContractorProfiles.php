<?php

namespace App\Filament\Resources\ContractorProfiles\Pages;

use App\Filament\Resources\ContractorProfiles\ContractorProfileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageContractorProfiles extends ManageRecords
{
    protected static string $resource = ContractorProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
