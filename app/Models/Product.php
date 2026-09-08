<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'supplier_profile_id', 'category_id', 'brand_id', 'name', 'slug', 'sku', 'description', 'specification', 'unit',
    'price', 'compare_at_price', 'min_order_qty', 'weight_kg', 'is_active', 'is_featured',
])]
class Product extends Model
{
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'compare_at_price' => 'integer',
            'weight_kg' => 'decimal:2',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    public function supplierProfile(): BelongsTo
    {
        return $this->belongsTo(SupplierProfile::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function formattedPrice(): string
    {
        return number_format($this->price).' XAF';
    }

    /**
     * Uses the already-loaded `inventories` relation when present (list
     * pages eager-load it to avoid an N+1 per card); falls back to a fresh
     * query otherwise so this is still safe to call anywhere.
     */
    public function isInStock(): bool
    {
        return $this->relationLoaded('inventories')
            ? $this->inventories->sum('quantity_available') > 0
            : $this->inventories()->sum('quantity_available') > 0;
    }

    /**
     * Three-tier stock label for customer-facing display. The "low stock"
     * cutoff (10 units) is a sensible default, not a value Sir George has
     * specified - easy to move to a config value if he wants a different
     * number, unlike the logistics "delayed" threshold (#4 in the
     * tracker), which is genuinely undefined and deliberately left alone.
     */
    public function stockStatus(): string
    {
        $available = $this->relationLoaded('inventories')
            ? $this->inventories->sum('quantity_available')
            : $this->inventories()->sum('quantity_available');

        return match (true) {
            $available <= 0 => 'out_of_stock',
            $available < 10 => 'low_stock',
            default => 'in_stock',
        };
    }

    public function stockStatusLabel(): string
    {
        return match ($this->stockStatus()) {
            'out_of_stock' => 'Out of Stock',
            'low_stock' => 'Low Stock',
            default => 'In Stock',
        };
    }
}
