<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'name', 'email', 'phone', 'phone_2', 'vehicle_id', 'document_path', 'photo_path', 'is_active'])]
class DeliveryAgent extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    /**
     * The shipment this agent is actively carrying right now, if any -
     * anything not yet in a terminal state counts as "in progress".
     */
    public function currentShipment(): ?Shipment
    {
        return $this->shipments()
            ->whereNotIn('status', ['delivered', 'cancelled', 'refunded'])
            ->latest('created_at')
            ->first();
    }

    /**
     * Approved: is_active but not currently carrying anything.
     * Engaged: is_active and carrying a shipment.
     * Not Approved: awaiting admin approval (self-registered, not yet activated).
     */
    public function engagementStatus(): string
    {
        if (! $this->is_active) {
            return 'not_approved';
        }

        return $this->currentShipment() ? 'engaged' : 'free';
    }
}
