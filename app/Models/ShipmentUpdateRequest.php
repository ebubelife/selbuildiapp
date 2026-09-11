<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'shipment_id', 'delivery_agent_id', 'requested_status', 'note', 'photo_path',
    'status', 'reviewed_by', 'reviewed_at',
])]
class ShipmentUpdateRequest extends Model
{
    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function deliveryAgent(): BelongsTo
    {
        return $this->belongsTo(DeliveryAgent::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function requestedStatusLabel(): string
    {
        return str($this->requested_status)->replace('_', ' ')->title();
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
