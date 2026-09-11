<?php

namespace App\Filament\Resources\DeliveryAgents\Pages;

use App\Filament\Resources\DeliveryAgents\DeliveryAgentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDeliveryAgents extends ManageRecords
{
    protected static string $resource = DeliveryAgentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
