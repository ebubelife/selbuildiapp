<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['type', 'description', 'subject_type', 'subject_id', 'causer_type', 'causer_id', 'data'])]
class ActivityLog extends Model
{
    /**
     * Every type this log currently records - used to drive the admin
     * "Notifications" tab's filter dropdown. Add a new one here whenever a
     * new call site starts logging, so it's filterable from day one.
     */
    public const TYPES = [
        'user_registered' => 'New Signup',
        'order_placed' => 'Order Placed',
        'payment_received' => 'Payment Received',
        'delivery_update_requested' => 'Delivery Update Requested',
        'delivery_update_approved' => 'Delivery Update Approved',
        'delivery_update_rejected' => 'Delivery Update Rejected',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function causer(): MorphTo
    {
        return $this->morphTo();
    }

    public static function log(string $type, string $description, ?Model $subject = null, ?Model $causer = null, array $data = []): self
    {
        return static::create([
            'type' => $type,
            'description' => $description,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'causer_type' => $causer?->getMorphClass(),
            'causer_id' => $causer?->getKey(),
            'data' => $data,
        ]);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst(str_replace('_', ' ', $this->type));
    }
}
