<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'order_id', 'supplier_profile_id', 'status', 'carrier', 'tracking_reference',
    'dispatched_at', 'expected_delivery_at', 'delivered_at', 'proof_of_delivery_note', 'notes',
])]
class Shipment extends Model
{
    protected function casts(): array
    {
        return [
            'dispatched_at' => 'datetime',
            'expected_delivery_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function supplierProfile(): BelongsTo
    {
        return $this->belongsTo(SupplierProfile::class);
    }

    public function statusLabel(): string
    {
        return str($this->status)->replace('_', ' ')->title();
    }

    /**
     * Whether this shipment arrived on time, based purely on the dates
     * actually recorded (delivered_at vs. expected_delivery_at) - not a
     * fixed SLA threshold. Null until both a delivery and an expected date
     * exist, since "on time" is meaningless before then. Deliberately not
     * a hardcoded time-based "delayed" flag (see #4 in the stakeholder
     * tracker - that concrete threshold is still an open decision).
     */
    public function isOnTime(): ?bool
    {
        if (! $this->delivered_at || ! $this->expected_delivery_at) {
            return null;
        }

        return $this->delivered_at->lessThanOrEqualTo($this->expected_delivery_at);
    }
}
