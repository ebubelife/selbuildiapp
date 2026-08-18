<?php

namespace App\Filament\Resources\CreditAccounts\Pages;

use App\Filament\Resources\CreditAccounts\CreditAccountResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCreditAccounts extends ManageRecords
{
    protected static string $resource = CreditAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
