<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'session_id'])]
class Cart extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function totalQuantity(): int
    {
        return $this->items->sum('quantity');
    }

    public function subtotal(): int
    {
        return $this->items->sum(fn (CartItem $item) => $item->unit_price_snapshot * $item->quantity);
    }

    /**
     * Cart items grouped by supplier, since fulfillment/shipping splits per supplier.
     *
     * @return \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, CartItem>>
     */
    public function itemsBySupplier()
    {
        return $this->items->load('product.supplierProfile')->groupBy('product.supplier_profile_id');
    }
}
