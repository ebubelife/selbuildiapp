<?php

namespace App\Filament\Resources\SupportTickets\Pages;

use App\Filament\Resources\SupportTickets\SupportTicketResource;
use Filament\Resources\Pages\ManageRecords;

class ManageSupportTickets extends ManageRecords
{
    protected static string $resource = SupportTicketResource::class;

    // Tickets only ever arrive from a customer (general enquiry, "Can't
    // Find What You Need", Request a Quote) - an admin hand-creating one
    // on someone's behalf isn't a real workflow here.
    protected function getHeaderActions(): array
    {
        return [];
    }
}
