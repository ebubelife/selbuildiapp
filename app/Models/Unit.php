<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A unit of sale for a product ("bag", "ton", "sheet", ...). products.unit
 * stores the name string directly (not a foreign key), so existing
 * products keep working and this table is only consulted to build the
 * dropdown + validation list.
 */
#[Fillable(['name', 'sort_order'])]
class Unit extends Model
{
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /**
     * Joined on the name string rather than an id - products.unit is a
     * plain string column, not a foreign key.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'unit', 'name');
    }

    public function label(): string
    {
        return ucfirst($this->name);
    }

    /**
     * @return array<string, string>  name => display label
     */
    public static function options(): array
    {
        return static::orderBy('sort_order')->orderBy('name')->get()
            ->mapWithKeys(fn (Unit $unit) => [$unit->name => $unit->label()])
            ->all();
    }
}
