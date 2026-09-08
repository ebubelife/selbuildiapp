<?php

namespace App\Filament\Resources\Shipments\Pages;

use App\Filament\Resources\Shipments\ShipmentResource;
use Filament\Resources\Pages\ManageRecords;

class ManageShipments extends ManageRecords
{
    protected static string $resource = ShipmentResource::class;

    protected function getHeaderActions(): array
    {
        // Shipments are only ever created automatically at checkout (one
        // per supplier on the order) - there's no legitimate "admin
        // creates a shipment by hand" workflow.
        return [];
    }
}
