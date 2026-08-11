<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'order_number', 'user_id', 'project_id', 'status', 'subtotal', 'shipping_fee',
    'tax', 'discount', 'total', 'currency', 'payment_status', 'payment_method',
    'shipping_address_id', 'placed_at',
])]
class Order extends Model
{
    public const STATUSES = [
        'pending', 'confirmed', 'processing', 'shipped',
        'out_for_delivery', 'delivered', 'cancelled', 'refunded',
    ];

    protected function casts(): array
    {
        return [
            'placed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function shippingAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'shipping_address_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Order items grouped by supplier, since each order can span multiple
     * suppliers who fulfill their portion independently.
     *
     * @return \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, OrderItem>>
     */
    public function itemsBySupplier()
    {
        return $this->items->load('supplierProfile')->groupBy('supplier_profile_id');
    }

    public function statusLabel(): string
    {
        return str($this->status)->replace('_', ' ')->title();
    }

    public function formattedTotal(): string
    {
        return number_format($this->total).' '.$this->currency;
    }

    public static function generateOrderNumber(): string
    {
        return 'SB-'.now()->format('ymd').'-'.strtoupper(str()->random(4));
    }
}
